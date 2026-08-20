# Async Promise Library — Design

**Date:** 2026-08-20
**Status:** Approved design, pending implementation plan
**Namespace:** `Manychois\PhpStrong\Async`

## Purpose

A Promise library built on PHP native Fibers (PHP 8.5+), as the foundation for a later
non-blocking HTTP client transport based on `curl_multi`. It is the project's first
non-PSR subsystem; the project scope statement broadens to "PSR implementations plus
the supporting primitives they need".

## Decisions taken

| Decision | Choice |
| -------- | ------ |
| Placement | `src/Async/` in php-strong (not a separate package) |
| Consumption model | await-first, with `then`/`catch`/`finally` chaining also provided |
| Scheduler | Implicit global scheduler; top-level `await()` allowed (amphp v3 model) |
| v1 features | Combinators (`all`, `race`, `any`) and `Deferred`; **no** `delay()`, **no** cancellation |
| Poller integration | `PollerInterface` seam only; no pollers ship in v1 (curl_multi comes later) |

## Public API

### `Promise<T>` (final)

Not publicly constructible. Created via `Deferred`, `Async::async()`, or static factories.

| Member | Behaviour |
| ------ | --------- |
| `await(): T` | Inside an `async()` fiber: suspends that fiber until settled. At top level: drives the scheduler until settled. Throws the rejection reason when rejected. |
| `then(callable $onFulfilled): Promise` | Maps the fulfilled value; a returned `Promise` is flattened. Rejections pass through untouched. |
| `catch(callable $onRejected): Promise` | Maps a rejection to a recovery value (or a `Promise`, flattened). Fulfillments pass through. |
| `finally(callable $onSettled): Promise` | Callback takes no arguments; value/reason passes through. A throw from the callback rejects the resulting promise. |
| `static resolved(mixed $value): Promise` | Already-fulfilled promise. |
| `static rejected(Throwable $reason): Promise` | Already-rejected promise. |
| `static all(iterable $promises): Promise` | Fulfills with an array preserving input keys once every input fulfills; rejects with the first rejection reason. `all([])` fulfills with `[]`. |
| `static race(iterable $promises): Promise` | Settles exactly as the first input to settle (fulfil or reject). Empty input throws `InvalidArgumentException`. |
| `static any(iterable $promises): Promise` | Fulfills with the first fulfillment; if all inputs reject, rejects with `CompositeException` aggregating every reason in input order. Empty input throws `InvalidArgumentException`. |

Combinator inputs MUST be `Promise` instances; plain values are not auto-wrapped
(`InvalidArgumentException` otherwise). Generics documented with `@template T` and
`@phpstan-*` tags per the coding standard.

### `Deferred<T>` (final)

Producer-side handle (the future curl_multi transport holds one per transfer).

| Member | Behaviour |
| ------ | --------- |
| `public Promise $promise { get }` | Property hook exposing the consumer-side promise. |
| `resolve(mixed $value): void` | Fulfills the promise. Second settlement attempt throws `BadMethodCallException`. |
| `reject(Throwable $reason): void` | Rejects the promise. Second settlement attempt throws `BadMethodCallException`. |

### `Async` (final, static)

| Member | Behaviour |
| ------ | --------- |
| `static async(callable $fn, mixed ...$args): Promise` | Runs `$fn` in a new Fiber scheduled on the global scheduler. Its return value fulfills the promise; an uncaught `Throwable` rejects it. |

### `CompositeException`

Extends `RuntimeException`; carries `list<Throwable>` of reasons (readonly, exposed via
a `reasons` property). Used by `Promise::any()`.

## Internal machinery (`src/Async/Internal/`, all `@internal`)

### `Scheduler`

One static instance. State: a FIFO ready-queue of `callable():void` (fiber resumptions
and settlement callbacks) and a `list<PollerInterface>`.

- `defer(callable): void` — enqueue.
- `registerPoller(PollerInterface): void` / `unregisterPoller(PollerInterface): void`.
- `runUntil(callable():bool $isDone): void` — drain queue items one at a time, checking
  `$isDone` between items; when the queue is empty and `$isDone` is false, poll each
  registered poller that `hasPending()`; when the queue is empty, `$isDone` is false,
  and no poller has pending work, throw `RuntimeException` (deadlock) instead of
  hanging.

### `PollerInterface`

The integration seam for future I/O backends (curl_multi).

- `hasPending(): bool` — whether the poller has in-flight work.
- `poll(float $maxWait): void` — perform ready I/O, waiting at most `$maxWait` seconds;
  may enqueue callbacks on the scheduler (typically Deferred settlements).

### `await()` mechanics

- Inside a fiber started by `Async::async()`: register a settlement callback that
  re-queues `Fiber::resume` (with value) or `Fiber::throw` (with reason), then
  `Fiber::suspend()`.
- At top level (no current fiber): `Scheduler::runUntil(promise is settled)`.
- Inside a scheduler-queue callback that is not a fiber (e.g. a `then()` callback):
  behaves like top-level await — `runUntil` runs reentrantly. Allowed and documented,
  though `async()` fibers are the recommended place for awaiting.

## Semantics

- **Asynchronous callbacks always:** `then`/`catch`/`finally` callbacks and settlement
  notifications run via the scheduler queue, never inline during `resolve()`/`reject()`,
  giving deterministic, JS-microtask-like ordering.
- **Unhandled rejections** are silently dropped in v1 (documented); observe rejections
  via `await()` or `catch()`.
- **Exception classes** follow the house rules: `InvalidArgumentException` for invalid
  combinator input, `BadMethodCallException` for double settlement,
  `RuntimeException` for deadlock.

## Testing

Pure unit tests in `tests/Async/` (no I/O; no feature-tests):

- Promise state transitions and `await()` in both top-level and nested-fiber contexts.
- Chaining: mapping, flattening, rejection pass-through, `finally` pass-through and
  throw behavior.
- Interleaving order assertions via event-log arrays (multiple `async()` fibers).
- Combinator matrix: `all` (success, first-rejection, empty, key preservation),
  `race` (fulfil-first, reject-first, empty), `any` (first fulfilment, all-reject →
  `CompositeException`, empty).
- Deadlock detection, double-settle errors, non-Promise combinator input.
- A stub `PollerInterface` test proving the scheduler polls and settles via a poller.
- 100% statement coverage, phpstan max, phpcs — the standard quality gates.

## Documentation

- `docs/async.md` (reference, per Diátaxis) + README table row labeled as a
  supporting (non-PSR) component.
- CLAUDE.md scope line updated to include supporting primitives.

## Out of scope (v1)

Cancellation, timers/`delay()`, unhandled-rejection reporting, any concrete poller
(curl_multi lands with the async HTTP work), promise auto-wrapping of plain values.
