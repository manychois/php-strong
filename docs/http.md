# PSR-7 HTTP Message & PSR-17 Factories — `Manychois\PhpStrong\Http`

Immutable implementations of `psr/http-message` plus the matching `psr/http-factory` factories.
Every `with*()` method returns a modified clone; factories return the concrete classes below.

## Quick start

```php
use Manychois\PhpStrong\Http\Request;
use Manychois\PhpStrong\Http\Response;
use Manychois\PhpStrong\Http\ServerRequest;
use Manychois\PhpStrong\Http\StatusCode;

$incoming = ServerRequest::fromGlobals();           // $_SERVER, $_GET, $_COOKIE, $_POST, php://input
$id = $incoming->getQueryParams()['id'] ?? null;

$outgoing = (new Request('POST', 'https://api.example.com/items', body: '{"id":1}'))
    ->withHeader('Content-Type', 'application/json');

$response = new Response(StatusCode::Created->value, headers: ['Location' => '/items/1'], body: 'done');
echo $response->getReasonPhrase(); // Created
```

## Classes

| Class | Implements | Notes |
| ----- | ---------- | ----- |
| `Request` | `RequestInterface` | `new Request($method = 'GET', $uri = null, $headers = [], $body = null, $protocolVersion = '1.1', $requestTarget = null)`. `$uri` accepts `UriInterface`, string, or `null` (`/`); `$body` accepts `StreamInterface`, string, or `null`. A `Host` header is derived from the URI when absent. |
| `ServerRequest` | `ServerRequestInterface` | Extends `Request`; adds `$serverParams, $cookieParams, $queryParams, $uploadedFiles, $parsedBody, $attributes`. `ServerRequest::fromGlobals()` builds one from PHP superglobals (headers from `HTTP_*`, `CONTENT_*`, `REDIRECT_HTTP_AUTHORIZATION`; scheme from `HTTPS`/`REQUEST_SCHEME`; protocol from `SERVER_PROTOCOL`) — `$_FILES` is not read, so it always carries no uploaded files. Uploaded files given to the constructor or `withUploadedFiles()` must be `UploadedFileInterface` instances (nested arrays allowed) or `InvalidArgumentException` is thrown. |
| `Response` | `ResponseInterface` | `new Response($statusCode = 200, $reasonPhrase = '', $headers = [], $body = null, $protocolVersion = '1.1')`. Codes outside 100–599 throw `InvalidArgumentException`; an empty reason phrase defaults to the IANA phrase via `StatusCode`. |
| `Stream` | `StreamInterface` | Wraps a PHP stream resource; non-resources throw `RuntimeException`. |
| `UploadedFile` | `UploadedFileInterface` | `moveTo()` copies the stream to the target path and marks the file moved; later `getStream()`/`moveTo()` throw `RuntimeException`. |
| `Uri` | `UriInterface` | `Uri::fromString()` parses with `parse_url` (malformed input throws `RuntimeException`). Standard ports (80/443) are omitted from `getPort()`/authority; `withPort()` validates 1–65535. |
| `Method` | enum (string) | `GET`, `HEAD`, `POST`, `PUT`, `PATCH`, `DELETE`, `CONNECT`, `OPTIONS`, `TRACE`, `QUERY`; `Method::fromString()` is case-insensitive. |
| `StatusCode` | enum (int) | IANA-registered status codes; `fromCode()` throws on unknown codes, `reasonPhrase()` gives the default phrase. |

## Factories (PSR-17)

| Class | Implements | Returns |
| ----- | ---------- | ------- |
| `RequestFactory` | `RequestFactoryInterface`, `ServerRequestFactoryInterface` | `Request`, `ServerRequest` |
| `ResponseFactory` | `ResponseFactoryInterface` | `Response` |
| `StreamFactory` | `StreamFactoryInterface` | `Stream` (`createStream()` uses `php://temp`) |
| `UploadedFileFactory` | `UploadedFileFactoryInterface` | `UploadedFile` (stream must be readable) |
| `UriFactory` | `UriFactoryInterface` | `Uri` (parse failures are rethrown as `InvalidArgumentException`) |

Header names are case-insensitive for lookup and preserve the casing of the last `withHeader()`/constructor entry.

## HTTP Client (PSR-18)

`Client` implements `Psr\Http\Client\ClientInterface`, backed by cURL.

```php
use Manychois\PhpStrong\Http\Client;
use Manychois\PhpStrong\Http\Request;
use Manychois\PhpStrong\Http\RequestOptions;

$client = new Client(new RequestOptions(timeout: 10.0, followRedirects: true));
$response = $client->sendRequest(new Request('GET', 'https://api.example.com/items'));
```

| Class | Implements | Notes |
| ----- | ---------- | ----- |
| `Client` | `ClientInterface` | `new Client($options = null, $transport = null)`; `$options` is a `RequestOptions` (defaults apply when `null`), `$transport` an internal seam that replaces cURL for `sendRequest()` (see the note below the async example). `sendRequest()` returns the concrete `Response`. Responses are returned whatever their status code. Protocol versions `1.0`, `1.1` (default), `2` and `2.0` are supported; any other value falls back to `1.1`. `sendAsync(RequestInterface $request): PendingRequest` dispatches immediately and returns a handle; transfers of one client run concurrently over `curl_multi`, with the same validation and exception rules as `sendRequest()`. Not part of PSR-18. |
| `RequestOptions` | — | Immutable transport options applied to every request: `timeout` (30.0 s total), `connectTimeout` (10.0 s), `followRedirects` (`false`) + `maxRedirects` (10), `verifyTls` (`true`), `proxy`, `userAgent` (sent only when the request has no `User-Agent` header), `caFile`, `caPath`. Non-positive timeouts, a negative `maxRedirects`, or empty strings throw `InvalidArgumentException`. |
| `ClientException` | `ClientExceptionInterface` | Base class of the two exceptions below; extends `RuntimeException`. |
| `RequestException` | `RequestExceptionInterface` | Thrown before sending when the request method is empty, the URI has a scheme other than `http`/`https` or lacks a host, or the request body cannot be read; `getRequest()` returns the offending request. |
| `NetworkException` | `NetworkExceptionInterface` | Thrown when the request cannot complete: DNS failure, connection refused, or timeout. The message carries the underlying cURL error. |
| `PendingRequest` | — | Handle returned by `sendAsync()`. `response(): Response` waits for and returns this transfer's response (all transfers of the same client progress while waiting; repeated calls return the same result or rethrow the same exception). `static waitAny(iterable $requests): PendingRequest` returns the first handle to complete — failed transfers count as completed and throw from the winner's `response()`. Discarding a handle (`unset`) aborts its transfer. |

`sendAsync()` places no cap on concurrency. To throttle, keep a sliding window:
start N transfers, then each time `PendingRequest::waitAny($window)` yields a
completed handle, remove it from the window, process it, and start the next
request.

### Async example

```php
use Manychois\PhpStrong\Http\Client;
use Manychois\PhpStrong\Http\PendingRequest;
use Manychois\PhpStrong\Http\Request;

$client = new Client();

$pending = [
    'items' => $client->sendAsync(new Request('GET', 'https://api.example.com/items')),
    'users' => $client->sendAsync(new Request('GET', 'https://api.example.com/users')),
];

while ($pending !== []) {
    $winner = PendingRequest::waitAny($pending);
    $key = array_search($winner, $pending, true);
    unset($pending[$key]);

    try {
        $response = $winner->response();
        // ... handle $response for $key
    } catch (\Throwable $ex) {
        // ... handle the failure for $key
    }
}
```

`waitAny()` throws `InvalidArgumentException` when given empty or
non-`PendingRequest` input. The handles it accepts may span multiple `Client`
instances. `sendAsync()` always sends over cURL directly; a custom
`$transport` passed to the `Client` constructor applies only to
`sendRequest()`.

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

## Session

`NativeSession` reads and writes PHP's own session — the `$_SESSION` superglobal — with the typed accessors of
[`DataReader`](collections.md), because `SessionInterface` extends `DataReaderInterface`.

```php
use Manychois\PhpStrong\Http\NativeSession;

$session = new NativeSession();          // starts nothing

$session->set('user.name', 'Ann');       // session_start() happens here
$session->string('user.name');           // 'Ann'
$session->asInt('cart.count');           // '3' becomes 3
$session->nullString('user.email');      // null when absent

$session->regenerate();                  // new id, data kept — call this right after sign-in
$session->destroy();
```

The session starts lazily: constructing the class touches nothing, and `session_start()` runs on the first read or
write of a value. `id()` and `isStarted()` deliberately do not start it, so they are safe to call before you have
decided whether a session is needed.

### Configuration

`NativeSessionOptions` carries everything applied to PHP right before the session starts. Its defaults are the secure
ones, so the no-argument constructor is a reasonable production setting.

```php
use Manychois\PhpStrong\Http\{NativeSession, NativeSessionOptions, SameSite, SessionSerializer};

$session = new NativeSession(new NativeSessionOptions(
    name: 'app_session',
    savePath: '/var/lib/app/sessions',
    cookieLifetime: 0,                              // until the browser closes
    cookieSameSite: SameSite::Strict,
    gcMaxLifetime: 1800,
    serializeHandler: SessionSerializer::PhpSerialize,
    ini: ['session.gc_probability' => 1],           // anything without a dedicated option
));
```

| Option | Default | Notes |
| ------ | ------- | ----- |
| `name` | `null` | The session, and cookie, name. `null` keeps PHP's. Letters, digits, dashes and underscores; digits only is rejected, as PHP forbids it. |
| `savePath` | `null` | Where session files are written. `null` keeps PHP's. |
| `cookieLifetime` | `0` | Seconds; `0` means until the browser closes. |
| `cookiePath` | `'/'` | |
| `cookieDomain` | `''` | Empty means the current host only. |
| `cookieSecure` | `true` | HTTPS only. |
| `cookieHttpOnly` | `true` | Hidden from JavaScript. |
| `cookieSameSite` | `SameSite::Lax` | `None` requires `cookieSecure`, which browsers enforce. |
| `cookiePartitioned` | `false` | CHIPS; requires `cookieSecure`. |
| `useStrictMode` | `true` | PHP refuses a session id it did not generate — the fixation defence. |
| `useOnlyCookies` | `true` | The id never comes from the URL. |
| `gcMaxLifetime` | `null` | Seconds an idle session survives. `null` keeps PHP's. |
| `serializeHandler` | `null` | See *Serialization* below. |
| `readAndClose` | `false` | Read the session once and close it immediately, releasing its lock. See below. |
| `ini` | `[]` | Further `session.*` settings, applied verbatim. Keys must carry the `session.` prefix, and a key a dedicated option already controls is rejected rather than silently losing to it. |

Every value is validated in the constructor, so a bad setting fails where it is written rather than at
`session_start()`. The options are handed to `session_start()` as one array — the only way to reach `read_and_close`,
and the form in which PHP reports an unrecognised setting instead of ignoring it.

### Read-only requests

A started session holds an exclusive lock on its file until the request ends, so a second request from the same
visitor waits. When a request only reads, `readAndClose` avoids that:

```php
$session = new NativeSession(new NativeSessionOptions(readAndClose: true));

$session->string('user.name');   // read once, lock already released
$session->isStarted();           // false — the session is closed again
$session->set('user.name', 'x'); // BadMethodCallException
```

The data stays readable for the rest of the request, and every member which would write — `set()`, `remove()`,
`clear()`, `regenerate()`, `destroy()` — throws `BadMethodCallException` rather than discarding the write silently.

### Serialization

Everything stored in the session is serialized when the request ends, not when `set()` is called — so an unstorable
value fails late, during shutdown. Closures throw, and **resources are silently replaced by `int 0`**. Objects are
fine as long as their class can be loaded when the session is read back.

The default handler of PHP, `session.serialize_handler = php`, stores entries as `key|serialized`, which brings two
traps at the top level: a numeric key is silently dropped, and a key containing `|` makes the *entire* session write
fail. `set()` already rejects a numeric top-level key for this reason. Choosing
`serializeHandler: SessionSerializer::PhpSerialize` serializes the data array as a whole and has neither limitation.

### Writing

`set()` and `remove()` take keys in the same dot notation as the reads, and `set()` creates missing segments as it
descends:

```php
$session->set('cart.items.0.sku', 'B-1');   // creates cart, items and the element
$session->remove('cart.items');
$session->clear();                          // empties the data, session stays alive
```

Two keys are refused rather than written:

- A segment which exists but holds something other than an array. `set('a.b', 1)` when `a` is the string `'x'`
  throws `InvalidArgumentException` instead of discarding `'x'`.
- A numeric top-level key. PHP would store `$_SESSION[0]`, and `keys()` promises strings.

### Members beyond the reader

| Member | Notes |
| ------ | ----- |
| `set(string $key, mixed $value): void` | Dot notation; creates missing segments. |
| `remove(string $key): void` | Dot notation; an absent key is ignored. |
| `clear(): void` | Empties the data; the session stays alive with the same id. |
| `id(): string` | `''` when the session has not started. |
| `isStarted(): bool` | Never starts the session. |
| `regenerate(bool $deleteOldSession = true): void` | New id, same data — the session fixation defence. |
| `destroy(): void` | Ends the session and drops its data; a no-op when never started. |

### Notes

- `reader('cart')` returns a plain `DataReader` over a *copy* of that subtree. Reads through it are fine; writes do
  not reach the session, so write with `$session->set('cart.…')`.
- `entries()`, `keys()` and `count()` describe the top level of the session data only.
- Since `SessionInterface` extends `DataReaderInterface`, type-hint the narrower `SessionInterface` only where you
  actually write; code which just reads can accept a `DataReaderInterface` and be tested with a plain `DataReader`.
