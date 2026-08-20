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

$client = new Client(timeout: 10.0);
$response = $client->sendRequest(new Request('GET', 'https://api.example.com/items'));
```

| Class | Implements | Notes |
| ----- | ---------- | ----- |
| `Client` | `ClientInterface` | `new Client($timeout = 30.0)`; `$timeout` (seconds) applies to both connecting and receiving, values ≤ 0 throw `InvalidArgumentException`. `sendRequest()` returns the concrete `Response`. Responses are returned whatever their status code; redirects are never followed. Protocol versions `1.0`, `1.1` (default), and `2` are supported. |
| `ClientException` | `ClientExceptionInterface` | Base class of the two exceptions below; extends `RuntimeException`. |
| `RequestException` | `RequestExceptionInterface` | Thrown before sending when the request URI has a scheme other than `http`/`https` or lacks a host; `getRequest()` returns the offending request. |
| `NetworkException` | `NetworkExceptionInterface` | Thrown when the request cannot complete: DNS failure, connection refused, or timeout. The message carries the underlying cURL error. |
