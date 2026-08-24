# PSR-15 Middleware Pipeline Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add `MiddlewarePipeline`, a PSR-15 request handler dispatching middleware instances or lazily container-resolved service ids to a fallback handler.

**Architecture:** One `final` public class `MiddlewarePipeline` in the existing `Http` module plus an `@internal` `PipelineStep` handler (pipeline reference + index) that makes dispatch reentrant. Strings are validated against the container at construction and resolved once on first dispatch.

**Tech Stack:** PHP 8.5, `psr/http-server-handler` ^1.0, `psr/http-server-middleware` ^1.0, PHPUnit, PHPStan max + strict rules.

**Spec:** `docs/superpowers/specs/2026-08-24-psr15-middleware-design.md`

## Global Constraints

- PHP `>=8.5`; PSR interfaces only — the module must not reference `Manychois\PhpStrong\DependencyInjection`.
- Coding standard: `docs/internal/php-coding-standard.md` — interface imports aliased `as IXxx`, `#[Override]` on implementations, `#region implements IInterface` blocks, methods alphabetical within visibility groups, `readonly` properties, PHPDoc on all public methods with a blank line between annotation types.
- Quality gates after every task: `composer phpcbf` → `composer phpcs` → `composer phpstan` → `composer test` (100% coverage expected).
- Tests use the `#[Test]` attribute, `methodName_describesBehaviour` naming, `self::assert*`, and support fakes as extra `final` classes at the bottom of the test file (see `tests/Events/EventDispatcherTest.php`).

---

### Task 1: Core dispatch (instances only)

**Files:**
- Modify: `composer.json` (require + keywords)
- Create: `src/Http/MiddlewarePipeline.php`
- Create: `src/Http/Internal/PipelineStep.php`
- Test: `tests/Http/MiddlewarePipelineTest.php`

**Interfaces:**
- Consumes: `Psr\Http\Server\{RequestHandlerInterface, MiddlewareInterface}`, module's `ServerRequest`/`Response`.
- Produces: `MiddlewarePipeline::__construct(iterable $middlewares, IRequestHandler $fallback, ?IContainer $container = null)`, `handle(IServerRequest): IResponse`, `@internal middlewareAt(int $index): ?IMiddleware`, `@internal fallbackHandler(): IRequestHandler`; `Internal\PipelineStep::__construct(MiddlewarePipeline $pipeline, int $index)`. Tasks 2–3 extend the constructor and `middlewareAt()`.

- [ ] **Step 1: Add the PSR packages**

Run: `composer require psr/http-server-handler:^1.0 psr/http-server-middleware:^1.0`
Then in `composer.json`, add `"psr-15"`, `"middleware"`, `"request-handler"` to `keywords` (keep the existing ordering style: psr numbers first, then topic words) and, if composer reordered `require`, leave it as composer wrote it.

- [ ] **Step 2: Write the failing tests**

Create `tests/Http/MiddlewarePipelineTest.php`:

```php
<?php

declare(strict_types=1);

namespace Manychois\PhpStrongTests\Http;

use Closure;
use Manychois\PhpStrong\Http\MiddlewarePipeline;
use Manychois\PhpStrong\Http\Response;
use Manychois\PhpStrong\Http\ServerRequest;
use Override;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface as IContainer;
use Psr\Http\Message\ResponseInterface as IResponse;
use Psr\Http\Message\ServerRequestInterface as IServerRequest;
use Psr\Http\Server\MiddlewareInterface as IMiddleware;
use Psr\Http\Server\RequestHandlerInterface as IRequestHandler;
use RuntimeException;

final class MiddlewarePipelineTest extends TestCase
{
    #[Test]
    public function handle_runsMiddlewaresInOrderThenTheFallback(): void
    {
        $log = [];
        $pipeline = new MiddlewarePipeline(
            [
                self::logging('a', $log),
                self::logging('b', $log),
            ],
            new FixedResponseHandler(new Response(204), $log),
        );

        $response = $pipeline->handle(new ServerRequest());

        self::assertSame(['a', 'b', 'fallback'], $log);
        self::assertSame(204, $response->getStatusCode());
    }

    #[Test]
    public function handle_middlewareReturningWithoutCallingItsHandlerShortCircuits(): void
    {
        $log = [];
        $short = new CallbackMiddleware(
            static fn (IServerRequest $request, IRequestHandler $handler): IResponse => new Response(403)
        );
        $fallback = new FixedResponseHandler(new Response(200), $log);
        $pipeline = new MiddlewarePipeline([$short, self::logging('after', $log)], $fallback);

        $response = $pipeline->handle(new ServerRequest());

        self::assertSame(403, $response->getStatusCode());
        self::assertSame([], $log);
    }

    #[Test]
    public function handle_emptyMiddlewareListDelegatesToTheFallback(): void
    {
        $log = [];
        $pipeline = new MiddlewarePipeline([], new FixedResponseHandler(new Response(200), $log));

        self::assertSame(200, $pipeline->handle(new ServerRequest())->getStatusCode());
        self::assertSame(['fallback'], $log);
    }

    #[Test]
    public function handle_repeatedCallsStartFromTheFirstMiddleware(): void
    {
        $log = [];
        $pipeline = new MiddlewarePipeline(
            [self::logging('a', $log)],
            new FixedResponseHandler(new Response(200), $log),
        );

        $pipeline->handle(new ServerRequest());
        $pipeline->handle(new ServerRequest());

        self::assertSame(['a', 'fallback', 'a', 'fallback'], $log);
    }

    #[Test]
    public function handle_aMiddlewareMayDispatchASubRequestThroughItsOwnPipeline(): void
    {
        $log = [];
        $pipeline = null;
        $recursing = new CallbackMiddleware(
            static function (IServerRequest $request, IRequestHandler $handler) use (&$log, &$pipeline): IResponse {
                $depth = $request->getAttribute('depth', 0);
                $log[] = 'a' . $depth;
                if ($depth === 0) {
                    \assert($pipeline instanceof MiddlewarePipeline);
                    $pipeline->handle($request->withAttribute('depth', 1));
                }

                return $handler->handle($request);
            }
        );
        $pipeline = new MiddlewarePipeline(
            [$recursing, self::logging('b', $log)],
            new FixedResponseHandler(new Response(200), $log),
        );

        $pipeline->handle(new ServerRequest());

        self::assertSame(['a0', 'a1', 'b', 'fallback', 'b', 'fallback'], $log);
    }

    #[Test]
    public function handle_worksWithForeignResponseAndHandlerDoubles(): void
    {
        $foreignResponse = $this->createStub(IResponse::class);
        $foreignHandler = new class ($foreignResponse) implements IRequestHandler {
            public function __construct(private readonly IResponse $response)
            {
            }

            #[Override]
            public function handle(IServerRequest $request): IResponse
            {
                return $this->response;
            }
        };

        $pipeline = new MiddlewarePipeline([], $foreignHandler);

        self::assertSame($foreignResponse, $pipeline->handle(new ServerRequest()));
    }

    #[Test]
    public function handle_anExceptionFromAMiddlewarePropagatesUnchanged(): void
    {
        $boom = new RuntimeException('boom');
        $throwing = new CallbackMiddleware(
            static fn (IServerRequest $request, IRequestHandler $handler): IResponse => throw $boom
        );
        $pipeline = new MiddlewarePipeline([$throwing], new FixedResponseHandler(new Response(200)));

        $this->expectExceptionObject($boom);
        $pipeline->handle(new ServerRequest());
    }

    #[Test]
    public function handle_anExceptionFromTheFallbackPropagatesUnchanged(): void
    {
        $boom = new RuntimeException('fallback boom');
        $throwingFallback = new class ($boom) implements IRequestHandler {
            public function __construct(private readonly RuntimeException $ex)
            {
            }

            #[Override]
            public function handle(IServerRequest $request): IResponse
            {
                throw $this->ex;
            }
        };
        $pipeline = new MiddlewarePipeline([], $throwingFallback);

        $this->expectExceptionObject($boom);
        $pipeline->handle(new ServerRequest());
    }

    /**
     * Creates a middleware that appends `$name` to `$log` and delegates to its handler.
     *
     * @param string $name The log entry.
     * @param array<string> $log The shared call log, taken by reference.
     *
     * @return IMiddleware The middleware.
     */
    private static function logging(string $name, array &$log): IMiddleware
    {
        return new CallbackMiddleware(
            static function (IServerRequest $request, IRequestHandler $handler) use ($name, &$log): IResponse {
                $log[] = $name;

                return $handler->handle($request);
            }
        );
    }
}

final class CallbackMiddleware implements IMiddleware
{
    /**
     * @param Closure(IServerRequest, IRequestHandler): IResponse $fn The processing logic.
     */
    public function __construct(private readonly Closure $fn)
    {
    }

    #[Override]
    public function process(IServerRequest $request, IRequestHandler $handler): IResponse
    {
        return ($this->fn)($request, $handler);
    }
}

final class FixedResponseHandler implements IRequestHandler
{
    /**
     * @var array<string>
     */
    private array $log;

    /**
     * @param IResponse $response The response returned by every call.
     * @param array<string> $log A shared call log appended with `fallback`, taken by reference.
     */
    public function __construct(private readonly IResponse $response, array &$log = [])
    {
        $this->log = &$log;
    }

    #[Override]
    public function handle(IServerRequest $request): IResponse
    {
        $this->log[] = 'fallback';

        return $this->response;
    }
}
```

Note: `FixedResponseHandler` binds the log with `$this->log = &$log;` because PHP does not allow by-reference promoted constructor properties. The `IContainer` import stays unused until Task 2 — omit it for now and add it there.

- [ ] **Step 3: Run the tests to verify they fail**

Run: `./vendor/bin/phpunit tests/Http/MiddlewarePipelineTest.php`
Expected: Error — `Class "Manychois\PhpStrong\Http\MiddlewarePipeline" not found`.

- [ ] **Step 4: Implement `MiddlewarePipeline` and `PipelineStep`**

Create `src/Http/MiddlewarePipeline.php`:

```php
<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\Http;

use Manychois\PhpStrong\Http\Internal\PipelineStep;
use Override;
use Psr\Container\ContainerInterface as IContainer;
use Psr\Http\Message\ResponseInterface as IResponse;
use Psr\Http\Message\ServerRequestInterface as IServerRequest;
use Psr\Http\Server\MiddlewareInterface as IMiddleware;
use Psr\Http\Server\RequestHandlerInterface as IRequestHandler;

/**
 * A PSR-15 request handler that dispatches middleware in order, ending at a fallback handler.
 */
final class MiddlewarePipeline implements IRequestHandler
{
    /**
     * @var list<IMiddleware|string> Middleware instances; a string is a container service id resolved (and replaced
     * in place) on first dispatch.
     */
    private array $middlewares;
    private readonly IRequestHandler $fallback;
    private readonly ?IContainer $container;

    /**
     * Creates a pipeline over an ordered list of middleware.
     *
     * @param iterable $middlewares Middleware instances, or container service ids resolved on first dispatch.
     * @param IRequestHandler $fallback The handler producing the response when no middleware short-circuits.
     * @param ?IContainer $container The container resolving service ids; required when any id is given.
     *
     * @phpstan-param iterable<mixed> $middlewares
     */
    public function __construct(iterable $middlewares, IRequestHandler $fallback, ?IContainer $container = null)
    {
        $list = [];
        foreach ($middlewares as $middleware) {
            \assert($middleware instanceof IMiddleware);
            $list[] = $middleware;
        }

        $this->middlewares = $list;
        $this->fallback = $fallback;
        $this->container = $container;
    }

    /**
     * Returns the handler that produces the response when the middleware list is exhausted.
     *
     * @return IRequestHandler The fallback handler.
     *
     * @internal
     */
    public function fallbackHandler(): IRequestHandler
    {
        return $this->fallback;
    }

    /**
     * Returns the middleware at a position, or `null` past the end of the list.
     *
     * @param int $index The zero-based position.
     *
     * @return ?IMiddleware The middleware, or `null` when `$index` is past the end.
     *
     * @internal
     *
     * @phpstan-param non-negative-int $index
     */
    public function middlewareAt(int $index): ?IMiddleware
    {
        $middleware = $this->middlewares[$index] ?? null;
        \assert(!\is_string($middleware));

        return $middleware;
    }

    #region implements IRequestHandler

    /**
     * @inheritDoc
     */
    #[Override]
    public function handle(IServerRequest $request): IResponse
    {
        return (new PipelineStep($this, 0))->handle($request);
    }

    #endregion implements IRequestHandler
}
```

Create `src/Http/Internal/PipelineStep.php`:

```php
<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\Http\Internal;

use Manychois\PhpStrong\Http\MiddlewarePipeline;
use Override;
use Psr\Http\Message\ResponseInterface as IResponse;
use Psr\Http\Message\ServerRequestInterface as IServerRequest;
use Psr\Http\Server\RequestHandlerInterface as IRequestHandler;

/**
 * The handler a middleware receives: the rest of its pipeline, starting at a fixed position.
 *
 * @internal
 */
final class PipelineStep implements IRequestHandler
{
    /**
     * @param MiddlewarePipeline $pipeline The pipeline being dispatched.
     * @param int $index The position this step dispatches.
     *
     * @phpstan-param non-negative-int $index
     */
    public function __construct(
        private readonly MiddlewarePipeline $pipeline,
        private readonly int $index,
    ) {
    }

    #region implements IRequestHandler

    /**
     * @inheritDoc
     */
    #[Override]
    public function handle(IServerRequest $request): IResponse
    {
        $middleware = $this->pipeline->middlewareAt($this->index);
        if ($middleware === null) {
            return $this->pipeline->fallbackHandler()->handle($request);
        }

        return $middleware->process($request, new PipelineStep($this->pipeline, $this->index + 1));
    }

    #endregion implements IRequestHandler
}
```

The two `\assert()` calls are Task 1 scaffolding replaced by real validation and resolution in Tasks 2–3.

- [ ] **Step 5: Run the tests to verify they pass**

Run: `./vendor/bin/phpunit tests/Http/MiddlewarePipelineTest.php`
Expected: PASS (8 tests).

- [ ] **Step 6: Quality gates**

Run: `composer phpcbf && composer phpcs && composer phpstan && composer test`
Expected: no style violations, no phpstan errors, all tests green. If phpstan flags the unused `$container` property, silence is NOT the fix — it is used in Task 3; if it blocks the gate, add the property in Task 3 instead of here (move the constructor's `$container` parameter and assignment to Task 3's diff).

- [ ] **Step 7: Commit**

```bash
git add composer.json composer.lock src/Http/MiddlewarePipeline.php src/Http/Internal/PipelineStep.php tests/Http/MiddlewarePipelineTest.php
git commit -m "feat(http): add PSR-15 middleware pipeline core dispatch"
```

---

### Task 2: Constructor validation

**Files:**
- Modify: `src/Http/MiddlewarePipeline.php` (constructor)
- Test: `tests/Http/MiddlewarePipelineTest.php`

**Interfaces:**
- Consumes: Task 1's constructor.
- Produces: the constructor now throws `InvalidArgumentException` for invalid elements and unknown/unbacked service ids; valid strings are kept in the list for Task 3 to resolve.

- [ ] **Step 1: Write the failing tests**

Add to `MiddlewarePipelineTest` (and add `use InvalidArgumentException;` plus the `IContainer` alias import and the `FakeContainer` support class below):

```php
    #[Test]
    public function construct_rejectsAnElementThatIsNeitherMiddlewareNorString(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Middleware 1 must be a middleware instance or a service id, int given.');

        new MiddlewarePipeline(
            [new CallbackMiddleware(static fn (): IResponse => new Response(200)), 123],
            new FixedResponseHandler(new Response(200)),
        );
    }

    #[Test]
    public function construct_rejectsAServiceIdWithoutAContainer(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Middleware 0 is the service id "mw" but no container is given.');

        new MiddlewarePipeline(['mw'], new FixedResponseHandler(new Response(200)));
    }

    #[Test]
    public function construct_rejectsAServiceIdTheContainerDoesNotHave(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Middleware 0 refers to the unknown service "missing".');

        new MiddlewarePipeline(['missing'], new FixedResponseHandler(new Response(200)), new FakeContainer());
    }
```

Support class at the bottom of the file:

```php
final class FakeContainer implements IContainer
{
    /**
     * @var array<string, int> How many times each id was fetched.
     */
    public array $getCalls = [];

    /**
     * @param array<string, mixed> $services Values returned by `get()`, keyed by id.
     */
    public function __construct(private readonly array $services = [])
    {
    }

    #[Override]
    public function get(string $id): mixed
    {
        $this->getCalls[$id] = ($this->getCalls[$id] ?? 0) + 1;

        return $this->services[$id];
    }

    #[Override]
    public function has(string $id): bool
    {
        return \array_key_exists($id, $this->services);
    }
}
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `./vendor/bin/phpunit --filter construct_ tests/Http/MiddlewarePipelineTest.php`
Expected: FAIL — the assert scaffolding throws `AssertionError` (or nothing) instead of `InvalidArgumentException`.

- [ ] **Step 3: Implement the validation**

Replace the Task 1 constructor loop in `MiddlewarePipeline` (add `use InvalidArgumentException;` to the imports):

```php
        $list = [];
        $i = 0;
        foreach ($middlewares as $middleware) {
            if (\is_string($middleware)) {
                if ($container === null) {
                    throw new InvalidArgumentException(
                        \sprintf('Middleware %d is the service id "%s" but no container is given.', $i, $middleware)
                    );
                }

                if (!$container->has($middleware)) {
                    throw new InvalidArgumentException(
                        \sprintf('Middleware %d refers to the unknown service "%s".', $i, $middleware)
                    );
                }
            } elseif (!$middleware instanceof IMiddleware) {
                throw new InvalidArgumentException(
                    \sprintf(
                        'Middleware %d must be a middleware instance or a service id, %s given.',
                        $i,
                        \get_debug_type($middleware)
                    )
                );
            }

            $list[] = $middleware;
            $i++;
        }
```

Extend the constructor PHPDoc with the failure modes (blank line before `@throws`, after the `@param` block):

```php
     * @throws InvalidArgumentException if an element is neither a middleware nor a string, if a service id is given
     * without a container, or if the container does not have a given id.
```

Do not touch `middlewareAt()` yet — its `\assert(!\is_string($middleware))` still holds because no test dispatches a pipeline containing a string until Task 3.

- [ ] **Step 4: Run the tests to verify they pass**

Run: `./vendor/bin/phpunit tests/Http/MiddlewarePipelineTest.php`
Expected: PASS (11 tests).

- [ ] **Step 5: Quality gates**

Run: `composer phpcbf && composer phpcs && composer phpstan && composer test`
Expected: all clean.

- [ ] **Step 6: Commit**

```bash
git add src/Http/MiddlewarePipeline.php tests/Http/MiddlewarePipelineTest.php
git commit -m "feat(http): validate middleware pipeline input at construction"
```

---

### Task 3: Lazy container resolution

**Files:**
- Modify: `src/Http/MiddlewarePipeline.php` (`middlewareAt()`)
- Test: `tests/Http/MiddlewarePipelineTest.php`

**Interfaces:**
- Consumes: Task 2's constructor (valid ids reach the list) and `FakeContainer` (`getCalls` counts `get()` per id).
- Produces: `middlewareAt()` resolves a string entry via the container on first call, caches it in place, and throws `RuntimeException` for a non-middleware service.

- [ ] **Step 1: Write the failing tests**

Add to `MiddlewarePipelineTest` (`RuntimeException` is already imported):

```php
    #[Test]
    public function handle_resolvesAServiceIdOnFirstDispatchAndOnlyOnce(): void
    {
        $log = [];
        $container = new FakeContainer(['mw' => self::logging('lazy', $log)]);
        $pipeline = new MiddlewarePipeline(['mw'], new FixedResponseHandler(new Response(200), $log), $container);

        self::assertSame([], $container->getCalls);

        $pipeline->handle(new ServerRequest());
        $pipeline->handle(new ServerRequest());

        self::assertSame(['mw' => 1], $container->getCalls);
        self::assertSame(['lazy', 'fallback', 'lazy', 'fallback'], $log);
    }

    #[Test]
    public function handle_neverResolvesAServiceIdBehindAShortCircuit(): void
    {
        $short = new CallbackMiddleware(
            static fn (IServerRequest $request, IRequestHandler $handler): IResponse => new Response(403)
        );
        $container = new FakeContainer(['mw' => $short]);
        $pipeline = new MiddlewarePipeline(
            [$short, 'mw'],
            new FixedResponseHandler(new Response(200)),
            $container,
        );

        $pipeline->handle(new ServerRequest());

        self::assertSame([], $container->getCalls);
    }

    #[Test]
    public function handle_throwsWhenAServiceResolvesToANonMiddleware(): void
    {
        $container = new FakeContainer(['mw' => 'not a middleware']);
        $pipeline = new MiddlewarePipeline(['mw'], new FixedResponseHandler(new Response(200)), $container);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Service "mw" is not a middleware, string given.');
        $pipeline->handle(new ServerRequest());
    }
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `./vendor/bin/phpunit --filter '/Service|Resolves|resolves/' tests/Http/MiddlewarePipelineTest.php`
Expected: FAIL — `middlewareAt()`'s `\assert(!\is_string($middleware))` raises `AssertionError`.

- [ ] **Step 3: Implement the resolution**

Replace `middlewareAt()` in `MiddlewarePipeline` (add `use RuntimeException;` to the imports):

```php
    /**
     * Returns the middleware at a position, or `null` past the end of the list.
     *
     * A service id at the position is resolved through the container on the first call and kept, so each id is
     * resolved at most once per pipeline.
     *
     * @param int $index The zero-based position.
     *
     * @return ?IMiddleware The middleware, or `null` when `$index` is past the end.
     *
     * @throws RuntimeException if a service id resolves to anything but a middleware instance.
     *
     * @internal
     *
     * @phpstan-param non-negative-int $index
     */
    public function middlewareAt(int $index): ?IMiddleware
    {
        $middleware = $this->middlewares[$index] ?? null;
        if (!\is_string($middleware)) {
            return $middleware;
        }

        $container = $this->container;
        \assert($container !== null);
        $resolved = $container->get($middleware);
        if (!$resolved instanceof IMiddleware) {
            throw new RuntimeException(
                \sprintf('Service "%s" is not a middleware, %s given.', $middleware, \get_debug_type($resolved))
            );
        }

        $this->middlewares[$index] = $resolved;

        return $resolved;
    }
```

The `\assert($container !== null)` is sound: the Task 2 constructor rejects every string id when `$container` is `null`, so this branch is unreachable with a null container — the assert documents that for PHPStan rather than adding an untestable throw.

- [ ] **Step 4: Run the tests to verify they pass**

Run: `./vendor/bin/phpunit tests/Http/MiddlewarePipelineTest.php`
Expected: PASS (14 tests).

- [ ] **Step 5: Quality gates**

Run: `composer phpcbf && composer phpcs && composer phpstan && composer test`
Expected: all clean, coverage for the two new files at 100%.

- [ ] **Step 6: Commit**

```bash
git add src/Http/MiddlewarePipeline.php tests/Http/MiddlewarePipelineTest.php
git commit -m "feat(http): resolve pipeline middleware lazily from a PSR-11 container"
```

---

### Task 4: Documentation

**Files:**
- Modify: `docs/http.md` (new PSR-15 section after the PSR-18 material)
- Modify: `README.md` (module table row; remove the planned-PSRs line)
- Modify: `CLAUDE.md` (PSR list in Architecture)

**Interfaces:**
- Consumes: the final Task 3 API.
- Produces: user-facing docs; no code.

- [ ] **Step 1: Add the PSR-15 section to `docs/http.md`**

Append after the async example section:

````markdown
## Middleware (PSR-15)

`MiddlewarePipeline` implements `Psr\Http\Server\RequestHandlerInterface`, dispatching
`Psr\Http\Server\MiddlewareInterface` instances in order and ending at a fallback handler.

```php
use Manychois\PhpStrong\Http\MiddlewarePipeline;
use Manychois\PhpStrong\Http\ServerRequest;

$pipeline = new MiddlewarePipeline(
    [
        new TrimTrailingSlash(),   // MiddlewareInterface instances…
        AuthMiddleware::class,     // …and container service ids, resolved on first dispatch
    ],
    fallback: new NotFoundHandler(),
    container: $container,
);

$response = $pipeline->handle(ServerRequest::fromGlobals());
```

| Member | Notes |
| ------ | ----- |
| `__construct(iterable $middlewares, RequestHandlerInterface $fallback, ?ContainerInterface $container = null)` | Each element is a `MiddlewareInterface` instance or a container service id. Anything else, an id without a container, or an id the container's `has()` denies throws `InvalidArgumentException`. An empty list is valid. |
| `handle(ServerRequestInterface $request): ResponseInterface` | Runs the middleware in registration order; each receives a handler representing the rest of the pipeline, and the fallback produces the response when the list is exhausted. A middleware that returns without calling its handler short-circuits the rest. |

- A service id is resolved on the first dispatch that reaches it — never at construction — and at most once per
  pipeline; a middleware that always short-circuits keeps everything behind it unresolved. A service that is not a
  `MiddlewareInterface` throws `RuntimeException` at dispatch.
- The pipeline keeps no cursor: every `handle()` call starts at the first middleware, so one instance serves many
  requests and a middleware may dispatch a sub-request through its own pipeline.
- The return type is `ResponseInterface`, not the concrete `Response` — middlewares may return any implementation.
- Exceptions from middlewares, the fallback, or the container propagate unchanged.
````

- [ ] **Step 2: Update `README.md` and `CLAUDE.md`**

In the README module table, add after the PSR-18 row:

```markdown
| PSR-15 Handlers & Middleware | `Manychois\PhpStrong\Http` | `MiddlewarePipeline`, a request handler dispatching middleware in order to a fallback handler, with optional lazy resolution of middleware service ids from a PSR-11 container. | [docs/http.md](docs/http.md) |
```

Delete the line `More PSRs (15) are planned.` — with PSR-15 done the library covers every accepted PSR that defines an interface (state that in its place: `Every accepted PSR that defines an interface is now covered.`).

In `CLAUDE.md`, extend the depended-on list in the Architecture section: insert `PSR-15 (psr/http-server-handler, psr/http-server-middleware)` between the PSR-14 and PSR-16 entries, keeping the existing backtick style of the neighbouring entries.

- [ ] **Step 3: Quality gates**

Run: `composer phpcbf && composer phpcs && composer phpstan && composer test`
Expected: all clean (docs-only change; this confirms nothing was left broken).

- [ ] **Step 4: Commit**

```bash
git add docs/http.md README.md CLAUDE.md
git commit -m "docs(http): document the PSR-15 middleware pipeline"
```
