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
| `ServerRequest` | `ServerRequestInterface` | Extends `Request`; adds `$serverParams, $cookieParams, $queryParams, $uploadedFiles, $parsedBody, $attributes`. `ServerRequest::fromGlobals()` builds one from PHP superglobals (headers from `HTTP_*`, `CONTENT_*`, `REDIRECT_HTTP_AUTHORIZATION`; scheme from `HTTPS`/`REQUEST_SCHEME`; protocol from `SERVER_PROTOCOL`). Uploaded files must be `UploadedFileInterface` instances (nested arrays allowed) or `InvalidArgumentException` is thrown. |
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
| `Client` | `ClientInterface` | `new Client($options = null)`; `$options` is a `RequestOptions` (defaults apply when `null`). `sendRequest()` returns the concrete `Response`. Responses are returned whatever their status code. Protocol versions `1.0`, `1.1` (default), and `2` are supported. |
| `RequestOptions` | — | Immutable transport options applied to every request: `timeout` (30.0 s total), `connectTimeout` (10.0 s), `followRedirects` (`false`) + `maxRedirects` (10), `verifyTls` (`true`), `proxy`, `userAgent` (sent only when the request has no `User-Agent` header), `caFile`, `caPath`. Non-positive timeouts, a negative `maxRedirects`, or empty strings throw `InvalidArgumentException`. |
| `ClientException` | `ClientExceptionInterface` | Base class of the two exceptions below; extends `RuntimeException`. |
| `RequestException` | `RequestExceptionInterface` | Thrown before sending when the request method is empty, the URI has a scheme other than `http`/`https` or lacks a host, or the request body cannot be read; `getRequest()` returns the offending request. |
| `NetworkException` | `NetworkExceptionInterface` | Thrown when the request cannot complete: DNS failure, connection refused, or timeout. The message carries the underlying cURL error. |
| `PendingRequest` | — | Handle returned by `sendAsync()`. `response(): Response` waits for and returns this transfer's response (all transfers of the same client progress while waiting; repeated calls return the same result or rethrow the same exception). `static waitAny(iterable $requests): PendingRequest` returns the first handle to complete — failed transfers count as completed and throw from the winner's `response()`. Discarding a handle (`unset`) aborts its transfer. |
| `Client::sendAsync()` | — | `sendAsync(RequestInterface $request): PendingRequest` — dispatches immediately and returns a handle; transfers of one client run concurrently over `curl_multi`. Same validation and exception rules as `sendRequest()`. Not part of PSR-18. |

`sendAsync()` places no cap on concurrency. To throttle, keep a sliding window:
start N transfers, then each time `PendingRequest::waitAny($window)` yields a
completed handle, remove it from the window, process it, and start the next
request.
