# PSR-15 HTTP Server Request Handlers — Design

Date: 2026-08-24
Module: `Manychois\PhpStrong\Http`

## Goal

Ship a concrete, strongly-typed implementation of PSR-15 (`psr/http-server-handler`,
`psr/http-server-middleware`): a middleware pipeline that is itself a request handler. With this, the
library covers every accepted PSR that defines an interface.

## Scope

In scope:

- `MiddlewarePipeline` — implements `Psr\Http\Server\RequestHandlerInterface`; dispatches an ordered list of
  `Psr\Http\Server\MiddlewareInterface` instances, optionally resolved lazily from a PSR-11 container, ending at a
  caller-supplied fallback handler.

Out of scope (deliberately):

- Bundled middlewares (routing, error handling, body parsing) — consumers write their own.
- Closure-to-middleware or closure-to-handler adapters.
- Mutating an existing pipeline (adding or removing middleware after construction).

## Dependencies and layout

- `composer.json`: require `psr/http-server-handler: ^1.0` and `psr/http-server-middleware: ^1.0`; add `psr-15`,
  `middleware` and `request-handler` keywords. `psr/container` is already required; the module depends on the
  PSR-11 *interface* only, never on `Manychois\PhpStrong\DependencyInjection`.
- `src/Http/MiddlewarePipeline.php`; `src/Http/Internal/PipelineStep.php` (`@internal`).
- `tests/Http/MiddlewarePipelineTest.php`.
- `docs/http.md` — new PSR-15 section; `README.md` — new module row; `CLAUDE.md` — add PSR-15 to the depended-on
  list and drop the "More PSRs (15) are planned" line from the README.

Imports follow the project standard: `use Psr\Http\Server\RequestHandlerInterface as IRequestHandler;`,
`MiddlewareInterface as IMiddleware`, `Psr\Http\Message\ServerRequestInterface as IServerRequest`,
`ResponseInterface as IResponse`, `Psr\Container\ContainerInterface as IContainer`.

## `MiddlewarePipeline`

```php
final class MiddlewarePipeline implements IRequestHandler
{
    public function __construct(
        iterable $middlewares,
        IRequestHandler $fallback,
        ?IContainer $container = null,
    );

    public function handle(IServerRequest $request): IResponse;
}
```

Construction:

- `$middlewares` is normalised to a list at construction; each element must be an `IMiddleware` instance or a
  string service id. Anything else throws `InvalidArgumentException` naming the offending position and type.
- A string element requires `$container`; without one, `InvalidArgumentException`. An id the container's `has()`
  denies also throws `InvalidArgumentException` at construction. This is stricter than
  `Events\ListenerProvider`, which skips the `has()` check because `on()` registration may precede container
  population — a pipeline is assembled in one shot at the boundary, so there is no later moment for the id to
  appear.
- An empty `$middlewares` list is valid: `handle()` goes straight to `$fallback`.
- The pipeline holds no other state; properties are `readonly` except the middleware list, which is written by the
  resolution cache below.

Dispatch:

- `handle()` walks the middleware list in registration order. Each middleware receives the request and an
  `IRequestHandler` representing the rest of the pipeline; when the list is exhausted, `$fallback` produces the
  response. A middleware that returns without calling its handler short-circuits the rest, per PSR-15.
- The return type stays `IResponse`, not the concrete `Response` — middlewares may return any `ResponseInterface`,
  so no narrowing is possible.
- Every `handle()` call starts at the first middleware. The pipeline keeps no cursor, so one instance serves many
  requests and nested dispatches (a middleware may send a sub-request through the same pipeline it is part of).
- Exceptions from middlewares, the fallback or the container are not caught; they propagate to the caller.

Lazy resolution:

- A string entry is resolved via `$container->get($id)` the first time dispatch reaches it, then cached in place —
  resolved once per pipeline, never at construction, and skipped entirely when an earlier middleware always
  short-circuits.
- A service that resolves to anything other than an `IMiddleware` throws `RuntimeException` at dispatch, naming
  the id and the actual type — PSR-11 `get()` returns `mixed`, so nothing can be verified earlier.
- Container exceptions from `get()` propagate as they are.

## `Internal\PipelineStep`

```php
/** @internal */
final class PipelineStep implements IRequestHandler
{
    public function __construct(private readonly MiddlewarePipeline $pipeline, private readonly int $index) {}

    public function handle(IServerRequest $request): IResponse;
}
```

- Step `$index` fetches middleware `$index` from the pipeline (triggering lazy resolution) and calls
  `process($request, new PipelineStep($pipeline, $index + 1))`; past the end it delegates to the fallback.
- Steps are immutable and created per dispatch chain, which is what makes the pipeline reentrant. The pipeline
  exposes the middleware accessor and fallback to the step via `@internal` methods.

## Usage

```php
use Manychois\PhpStrong\Http\MiddlewarePipeline;

$pipeline = new MiddlewarePipeline(
    [
        new TrimTrailingSlash(),      // IMiddleware instances…
        AuthMiddleware::class,        // …and container ids, resolved on first dispatch
        RoutingMiddleware::class,
    ],
    fallback: new NotFoundHandler(),
    container: $container,
);

$response = $pipeline->handle(ServerRequest::fromGlobals());
```

## Testing

Unit tests only; middlewares and handlers are small anonymous classes over the module's own `ServerRequest` and
`Response`. Target 100% coverage, matching the other modules.

`MiddlewarePipelineTest`:

- Middlewares run in registration order; each sees the request (possibly modified by its predecessor) and the
  fallback runs last.
- A middleware that returns without calling its handler short-circuits: later middlewares and the fallback never
  run.
- An empty middleware list delegates straight to the fallback.
- Repeated `handle()` calls on one pipeline start from the first middleware each time.
- A middleware dispatching a sub-request through its own pipeline works (reentrancy).
- A string id resolves from the container on first dispatch, not at construction, and only once across two
  dispatches.
- A string id after a short-circuiting middleware is never resolved.
- Constructor errors: a non-middleware, non-string element; a string id without a container; an id `has()` denies
  — all `InvalidArgumentException`.
- A service resolving to a non-middleware throws `RuntimeException` at dispatch.
- An exception thrown by a middleware or the fallback propagates unchanged.
- Works with a foreign `RequestHandlerInterface` fallback and foreign `ResponseInterface` (test doubles).

## Quality gates

`composer phpcbf` → `composer phpcs` → `composer phpstan` → `composer test`, all clean before the work is done.
