# Non-Blocking HTTP Requests — Design

**Date:** 2026-08-20
**Status:** Approved design, pending implementation plan
**Supersedes:** the Promise library design of the same date (deleted; the
Promise/scheduler approach was rejected in favour of this minimal design).

## Purpose

Let a `Client` send multiple HTTP requests concurrently using `curl_multi`,
without introducing a Promise abstraction. A request handle (`PendingRequest`)
is returned immediately; the response is collected later. Concurrency for
independent requests comes entirely from `curl_multi`; PHP Fibers are **not
used in v1** but the design keeps a seam for a later `concurrently()` feature
covering multi-step flows (dependent request chains overlapping each other).

## Public API (namespace `Manychois\PhpStrong\Http`)

### `Client::sendAsync(RequestInterface $request): PendingRequest`

- An extension beyond PSR-18 (`sendRequest()` keeps the PSR contract and its
  existing implementation, untouched).
- Performs the same pre-send validation as `sendRequest()` (non-empty method,
  http/https scheme, non-empty host) throwing `RequestException` synchronously.
- Registers the transfer on the client's `CurlMultiExecutor`; the transfer is
  in flight when the method returns.
- Applies the client's `RequestOptions` exactly as the synchronous path does
  (reuses `CurlTransport::buildOptions()`).

### `PendingRequest` (final)

| Member | Behaviour |
| ------ | --------- |
| `response(): Response` | Drives the executor until this transfer completes; returns the `Response`. On transfer failure throws `NetworkException`; on unparseable response throws `ClientException` (same classification rules as `sendRequest()`). Idempotent: repeated calls return the same `Response` or rethrow the same exception. While waiting, **all** transfers on the same executor progress. |

Not publicly constructible (created by `Client::sendAsync()`). No cancellation,
no `isDone()`, no timeout parameter — per-transfer timeouts come from
`RequestOptions` and surface as `NetworkException` from `response()`.

## Internals (`src/Http/Internal/`, `@internal`)

### `CurlMultiExecutor`

One instance per `Client`, created lazily on first `sendAsync()`.

- Owns a `CurlMultiHandle`; maps each easy handle to its pending state
  (header-line buffer, settlement slot).
- `add(CurlHandle $handle, callable $onComplete): void` — attach a transfer;
  `$onComplete` receives either the parsed `RawResponse` or a `Throwable`.
- `pump(): void` — one iteration: `curl_multi_exec`, `curl_multi_select`
  (bounded wait), then drain `curl_multi_info_read`, parsing finished
  transfers via `RawResponse::fromHeaderLines()` and invoking their callbacks.
- Reuses `CurlTransport::buildOptions()` for option building; per-transfer
  header collection via `CURLOPT_HEADERFUNCTION` as in the sync path.

### Fiber seam (documented, not implemented in v1)

`PendingRequest::response()` is the single wait point. v1 implements it as
"pump the executor until settled". A future `concurrently()` will make it
"suspend the current managed fiber instead, if there is one" — an internal
change with no public API impact.

## Semantics

- Transfers start eagerly at `sendAsync()` time; a `PendingRequest` whose
  `response()` is never called simply never blocks anyone (its transfer may
  progress incidentally while other handles are pumped; it is discarded with
  the executor).
- Exception classification is identical to the synchronous path (PSR-18
  error-handling rules): `RequestException` before sending, `NetworkException`
  for transfer failures including timeouts, `ClientException` for unparseable
  responses; non-2xx responses are returned, never thrown.
- Redirect following, TLS, proxy, user agent: all `RequestOptions` behaviours
  apply per transfer unchanged.

## Testing

- Unit tests (`tests/Http/`): `sendAsync()` validation errors (thrown before
  any transfer is registered, so no network involved).
- `PendingRequest` idempotency and error-rethrow behaviour is exercised in the
  feature tests (it requires completed transfers; no mock-transport seam is
  added for the multi path in v1).
- Feature tests (`feature-tests/Http/`): two `/slow` requests overlap (elapsed
  ≈ max, not sum — asserted with a generous margin); mixed success/failure
  batches; `response()` called in reverse completion order; timeout inside
  `response()`.
- Standard gates: phpcbf, phpcs, phpstan max, 100% statement coverage.

## Out of scope (v1)

Fibers/`concurrently()` (multi-step flows overlapping), cancellation, promise
combinators, HTTP/2 multiplexing tuning, connection-pool configuration.
