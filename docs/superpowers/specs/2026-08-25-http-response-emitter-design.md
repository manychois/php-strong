# HTTP Response Emitter — Design

Date: 2026-08-25
Module: `Manychois\PhpStrong\Http`

## Goal

Send a PSR-7 `Response` to the SAPI: status line, headers, body. Nothing in `src/` does this today, which
means the server role stops one step short of working. A `MiddlewarePipeline` produces a `Response` and a
`CookieBag` applies `Set-Cookie` headers to it, but the result reaches the browser only if the application
hand-writes `header()` calls — the exact code this library exists to replace, and the code most likely to get
the `Set-Cookie` merge rule wrong.

This spec closes the gap left open by the cookies design and completes the server request cycle:

    ServerRequest::fromGlobals() -> MiddlewarePipeline -> CookieBag::applyTo() -> SapiEmitter::emit()

## Scope

In scope:

- `ResponseEmitterInterface` — one method; the seam an application substitutes in a functional test.
- `SapiEmitter` — the concrete implementation writing to PHP's SAPI.
- Test infrastructure making the SAPI calls observable (`tests/Http/sapi-functions.php`, `SapiSpy`).
- Documentation of the complete server request cycle in `docs/http.md`.

Out of scope (deliberately) — see "Non-goals" for the reasoning behind each:

- Output buffer manipulation.
- `Content-Length` management.
- `flush()` and implicit flush control.
- Request `Range` parsing and `206 Partial Content`.
- Changes to `NativeSession`.
- Connection and protocol handling: keep-alive, chunked transfer encoding, HTTP/2 push.

## Dependencies and layout

No new Composer dependencies. No PSR governs response emission, so this is a utility of the `Http` module in
the same sense as `NativeSession` — which is also the naming and structural precedent it follows.

    src/Http/ResponseEmitterInterface.php   (new)
    src/Http/SapiEmitter.php                (new)
    tests/Http/SapiEmitterTest.php          (new)
    tests/Http/SapiSpy.php                  (new)
    tests/Http/sapi-functions.php           (new; registered via autoload-dev.files)
    composer.json                           (modified; autoload-dev.files entry)
    docs/http.md                            (modified; new section)
    README.md                               (modified; Utilities row)

No existing class changes. The emitter reads a `Response`; nothing reads the emitter.

## `ResponseEmitterInterface`

```php
interface ResponseEmitterInterface
{
    public function emit(IResponse $response, ?IRequest $request = null): void;
}
```

The interface exists for one reason: an application under functional test needs to capture a response without
reaching into PHP's global header state. The namespace-shadowing seam described below serves *this library's*
tests; it is not available to a downstream application, whose code lives in a different namespace. One method
is not a speculative abstraction — it is the substitution point, and the module already carries the same shape
in `SessionInterface` / `NativeSession`.

`$request` is typed `Psr\Http\Message\RequestInterface`, which `ServerRequestInterface` extends, so a
`ServerRequest` is accepted directly. It is optional and is read for exactly one purpose: detecting a `HEAD` request, which must receive no
body. Passing `null` asserts "this is not a HEAD request". Nothing else about the request is consulted.

## `SapiEmitter`

```php
final class SapiEmitter implements IResponseEmitter
{
    public function __construct(private readonly int $chunkSize = 8_388_608) { }
}
```

`$chunkSize` is the number of bytes read from the body stream per write. It must be positive; a value below 1
throws `InvalidArgumentException` from the constructor, per the module's fail-at-the-boundary principle. The
default of 8 MiB is large enough that an ordinary HTML or JSON response leaves in a single read, and small
enough that streaming a multi-gigabyte file never approaches `memory_limit`.

`emit()` runs four phases in a fixed order, each a private method. The order is load-bearing and the spec
states it as a requirement, not an implementation detail:

1. **Guard.** `headers_sent($file, $line)` — if true, throw `RuntimeException` naming the reported file and
   line. Running first means a failed emit writes nothing at all, leaving the caller free to emit a different
   response or log the failure. A stray `echo` or a byte-order mark ahead of the front controller is a bug;
   emitting a response with silently discarded headers hides it.
2. **Status line.**
3. **Headers.**
4. **Body.**

### Status line

```php
header(
    sprintf(
        'HTTP/%s %d%s',
        $response->getProtocolVersion(),
        $response->getStatusCode(),
        $reason === '' ? '' : ' ' . $reason,
    ),
    true,
    $response->getStatusCode(),
);
```

`http_response_code()` is not used: it cannot carry a reason phrase, and `Response::withStatus($code,
$reasonPhrase)` deliberately supports custom phrases, so using it would silently discard data the value object
was careful to keep.

The third argument is what actually fixes the status code. The version token in the header line is therefore
advisory — under HTTP/2 the protocol layer discards it rather than emitting a malformed line. A response
carrying an empty reason phrase emits no trailing space.

The status line must precede header emission so that no `header()` call can implicitly fix a different status
code first.

### Headers

Iterate `$response->getHeaders()`. For each name, iterate its values with an index:

```php
$replace = $index === 0 && strcasecmp($name, 'Set-Cookie') !== 0;
header($name . ': ' . $value, $replace);
```

Values are emitted verbatim: no re-folding, no trimming, no normalisation of header-name casing. PSR-7
preserves the caller's casing deliberately, and a value has already been validated by whatever produced it.

**The `Set-Cookie` rule.** `Set-Cookie` passes `$replace = false` for *every* value, including the first. This
is the rule carried over from the cookies design, and it is the reason this class cannot be written casually.
The obvious implementation — replace on the first value of each header — silently deletes any `Set-Cookie` PHP
has already queued, most notably the session cookie written by `session_start()` inside `NativeSession`
(`NativeSession.php:218`). That cookie never appears in the `Response` object, so no amount of inspecting the
`Response` reveals the loss.

The emitter is the single point where PHP's own queued headers and the `Response`'s headers meet. It merges
them; it must never clobber them. The comparison is case-insensitive because PSR-7 does not normalise header
names.

### Body

The body is suppressed entirely when HTTP forbids one:

```php
$code = $response->getStatusCode();
$noBody = $code === 204
    || $code === 304
    || ($code >= 100 && $code < 200)
    || ($request !== null && strtoupper($request->getMethod()) === 'HEAD');
```

RFC 9110 forbids a body on 1xx, 204, and 304, and on any response to `HEAD`. A body on a 304 desynchronises
the connection for the next keep-alive request on it — a failure that appears as a corrupted *subsequent*
response, which is why leaving the rule to every caller is a poor trade.

`strtoupper()` is required: PSR-7 does not normalise the request method, so `head` must suppress exactly as
`HEAD` does.

When suppressed, the body stream is not touched at all — not read, not rewound, not cast to string — so a
lazily-populated body is never realised.

Otherwise:

```php
$body = $response->getBody();
if (!$body->isReadable()) {
    return;
}
if ($body->isSeekable()) {
    $body->rewind();
}
while (!$body->eof()) {
    $chunk = $body->read($this->chunkSize);
    if ($chunk === '') {
        break;
    }

    echo $chunk;
}
```

Three details are requirements rather than defensive habit:

- **A non-readable body emits nothing.** Calling `read()` on a detached or write-only stream throws, and a
  caller error should not become a fatal error halfway through a partially written response.
- **A non-seekable body is not rewound** — reading resumes from its current position, which is the only
  meaningful behaviour for a pipe or a socket.
- **An empty read before `eof()` breaks the loop.** PSR-7 permits a non-blocking stream to return `''` while
  not yet at EOF; the naive loop spins forever on it.

## Testing

PHP resolves an unqualified function call against the calling namespace first and the global namespace second.
`SapiEmitter` calls `header()` and `headers_sent()` unqualified from namespace
`Manychois\PhpStrong\Http`, so a test-only declaration of those functions *in that same namespace* intercepts
them with no indirection in production code. The class that ships is exactly the class that runs.

Three pieces:

**`tests/Http/sapi-functions.php`** declares the functions in `Manychois\PhpStrong\Http` — the namespace
`SapiEmitter` lives in, not the tests' own `Manychois\PhpStrongTests\Http` — each delegating to the spy:

```php
namespace Manychois\PhpStrong\Http;

use Manychois\PhpStrongTests\Http\SapiSpy;

function header(string $header, bool $replace = true, int $response_code = 0): void
{
    SapiSpy::header($header, $replace, $response_code);
}

function headers_sent(?string &$filename = null, ?int &$line = null): bool
{
    return SapiSpy::headersSent($filename, $line);
}
```

Function declarations cannot be PSR-4 autoloaded, so the file is registered under a new `autoload-dev.files`
entry in `composer.json`, requiring a `composer dump-autoload`. This is the only configuration change in the
spec and it is dev-only; the `autoload` section is untouched.

**`tests/Http/SapiSpy.php`** — `Manychois\PhpStrongTests\Http\SapiSpy`, holding static state: a recorded list
of `[header, replace, code]` triples, and a settable `headers_sent` result with its file and line. State is
static because the shadowed functions have no object to reach through. `SapiSpy::reset()` in `setUp()`
restores isolation between tests.

**Body capture** — `ob_start()` / `ob_get_clean()` around the `emit()` call, since the body leaves via `echo`.

### What this does and does not prove

The stubs are defined for the whole test process, so `SapiEmitter` never calls the real `header()` under test.
The suite verifies *which calls are made, with which arguments, in which order* — not that PHP delivers them.

This is an honest limit, and it is narrow because the emitter contains no branching the suite cannot reach:
every decision the class makes is asserted against the recorded call list. The `Set-Cookie` merge rule in
particular is a claim about the `$replace` argument, so it is fully covered.

**Consequent rule:** no other class in `Manychois\PhpStrong\Http` may call `header()` or `headers_sent()`
unqualified, or it will silently hit the stub during tests. Only `SapiEmitter` does today. A class needing the
real function must call it fully qualified as `\header()`.

### Test cases

- Every `Set-Cookie` value emits with `$replace === false`, including the first — asserted against a real
  `CookieBag::applyTo()` result rather than a hand-built response, so the two specs are wired together by an
  actual test rather than by prose.
- A multi-value non-cookie header (`Vary`) replaces on the first value and appends on the rest.
- A custom reason phrase reaches the status line; a status with no phrase emits no trailing space.
- Body suppression for 204, 304, a 1xx status, and lowercase `head` — asserting the stream was never read,
  not merely that no output appeared.
- A body larger than `chunkSize` arrives byte-identical, in more than one `read()`.
- A non-seekable stream is not rewound; a non-readable stream emits nothing.
- A stream returning `''` before `eof()` terminates rather than hanging. Without this the failure surfaces as
  a `defaultTimeLimit="3"` suite timeout, which points nowhere near the cause.
- `headers_sent()` true throws `RuntimeException` naming file and line, with nothing emitted.
- A `chunkSize` below 1 throws `InvalidArgumentException`.

## Documentation

`docs/http.md` gains `## Emitting a response`, placed after `## Middleware (PSR-15)` and before `## Cookies`.
The emitter is the last step of the server role, and the cookies section can then point forward to it instead
of noting a gap. The section is reference plus one short how-to: the front-controller shape wiring
`ServerRequest::fromGlobals()` through the pipeline, the cookie bag, and the emitter. This is the first place
the library documents a complete server request cycle end to end.

`README.md` gains `ResponseEmitterInterface` and `SapiEmitter` in the existing `### Utilities` table, in the
`Manychois\PhpStrong\Http` row beside `NativeSession` and the cookie classes. Not the PSR-15 row: no PSR
governs response emission, and filing it there would assert otherwise.

The cookies spec's "Hand-off to the emitter spec" note gains a closing line pointing at this document, so the
`Set-Cookie` rule has a home on both sides rather than dangling from one.

## Non-goals

- **Output buffer manipulation.** The emitter never opens, flushes, or cleans a buffer. A buffer it did not
  open may hold output the caller intends to keep, and a library is in no position to judge. A dirty buffer
  surfaces through the `headers_sent()` guard, which names the file and line responsible.
- **`Content-Length` management.** Setting it from `getBody()->getSize()` is the most likely "helpful"
  addition someone proposes later, and it is wrong whenever output compression (`ob_gzhandler`, `mod_deflate`)
  rewrites the body after the emitter runs: a stale length truncates the response, and the emitter cannot see
  that from where it sits. The caller sets it or omits it.
- **`flush()` and implicit flush control.** Whether bytes leave the process immediately is a SAPI and
  `output_buffering` concern; forcing it fights FastCGI's own buffering.
- **Request `Range` parsing.** The emitter honours no `Range` header and never produces `206`. An application
  wanting partial content sets the status, the `Content-Range` header, and a pre-sliced body itself.
- **`NativeSession` changes.** Unchanged from the cookies spec: PHP emits the session cookie itself at
  `session_start()`. The emitter's obligation is only to not destroy it.
- **Connection and protocol handling.** Keep-alive, chunked transfer encoding, and HTTP/2 push belong to the
  SAPI.

## Known limitations

- **Reason phrases under HTTP/2 are advisory.** HTTP/2 has no reason phrase on the wire; a custom phrase set
  on the `Response` is accepted and passed to `header()`, and the protocol layer discards it. This is PHP's
  behaviour, not a choice made here.
- **The suite cannot prove PHP delivers the headers.** See "What this does and does not prove" above.
- **A response emitted to a CLI SAPI writes headers nowhere.** `header()` is a no-op outside a web SAPI. The
  body still reaches stdout, which makes `SapiEmitter` usable but not meaningful under `php -a`.
