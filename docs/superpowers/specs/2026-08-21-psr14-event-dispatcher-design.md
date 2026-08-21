# PSR-14 Event Dispatcher — Design

Date: 2026-08-21
Module: `Manychois\PhpStrong\Events`

## Goal

Ship a concrete, strongly-typed implementation of PSR-14 (`psr/event-dispatcher`): a dispatcher, a type-based
listener provider with priorities, and a trait for stoppable events.

## Scope

In scope:

- `EventDispatcher` — implements `Psr\EventDispatcher\EventDispatcherInterface`.
- `ListenerProvider` — implements `Psr\EventDispatcher\ListenerProviderInterface`; mutable, type-based, prioritised,
  optionally backed by a PSR-11 container for deferred listeners.
- `StoppableEventTrait` — reusable implementation of `Psr\EventDispatcher\StoppableEventInterface` members.

Out of scope (deliberately):

- Aggregate/delegating provider composing several providers.
- Listener subscriber objects (a class declaring many listeners at once).
- Once-only listeners, listener removal, error/exception events.

## Dependencies and layout

- `composer.json`: require `psr/event-dispatcher: ^1.0`; add `psr-14` and `event-dispatcher` keywords. `psr/container`
  is already required; the module depends on the PSR-11 *interface* only, never on
  `Manychois\PhpStrong\DependencyInjection`.
- `src/Events/EventDispatcher.php`, `ListenerProvider.php`, `StoppableEventTrait.php`.
- `tests/Events/` mirrors `src/`.
- `docs/events.md` — reference page in the style of `docs/dependency-injection.md`.
- `README.md` — add the module to the module list.

Imports follow the project standard: `use Psr\EventDispatcher\EventDispatcherInterface as IEventDispatcher;`,
`ListenerProviderInterface as IListenerProvider`, `StoppableEventInterface as IStoppableEvent`,
`Psr\Container\ContainerInterface as IContainer`.

## `EventDispatcher`

```php
final class EventDispatcher implements IEventDispatcher
{
    public function __construct(private readonly IListenerProvider $provider) {}

    public function dispatch(object $event): object;
}
```

- Accepts any PSR-14 listener provider, not only `ListenerProvider`.
- `dispatch()` returns the same instance it was given. Typed `@template T of object`,
  `@phpstan-param T $event`, `@phpstan-return T` so callers keep the concrete event type.
- Before each listener call, if the event is an `IStoppableEvent` and `isPropagationStopped()` is true, the loop
  ends. The check happens before the first call too, so an event that arrives already stopped invokes no listener.
- Listener exceptions are not caught. They propagate to the caller, which by the specification ends propagation.
- Listeners are called as `$listener($event)`; any return value is ignored.
- The dispatcher holds no container. Which callable represents a listener is the provider's decision, and
  `getListenersForEvent()` must yield plain callables, so deferred resolution lives entirely in the provider.

## `ListenerProvider`

```php
final class ListenerProvider implements IListenerProvider
{
    public function __construct(private readonly ?IContainer $container = null) {}

    public function on(string $eventType, callable|array $listener, int $priority = 0): static;

    public function getListenersForEvent(object $event): iterable;
}
```

Registration:

- `on()` is chainable and may be called at any time, including after dispatching has begun.
- `$eventType` must name an existing class or interface (`class_exists()` or `interface_exists()`); otherwise
  `InvalidArgumentException`.
- Duplicate registrations are allowed; the same listener registered twice is called twice.
- Typing for the callable form: `@phpstan-param callable(T):mixed|array{string, string} $listener` under
  `@template T of object` with `@phpstan-param class-string<T> $eventType`, so a closure whose parameter type does
  not match the event type fails static analysis. `:mixed` rather than `:void` because the dispatcher discards
  return values; requiring `void` would reject a listener that merely returns what it delegates to.

Array listeners are classified by their first element:

- `array{object, string}` — an ordinary `[$instance, 'method']` callable. Passed straight through and called
  directly; it is not a service reference and needs no container. Rejected with `InvalidArgumentException` at
  registration if the method does not exist or is not public (`is_callable()`).
- `array{string, string}` — a service reference, per the rules below.

Deferred (container-resolved) listeners:

- An `array{string, string}` argument is a service reference `[$serviceId, $method]`, resolved on dispatch as
  `$container->get($serviceId)->$method($event)`. This lets a caller register an instance method before the
  instance exists.
- Resolution happens per dispatch, never at registration. The provider yields a one-parameter wrapper closure, so
  the listener stays spec-compatible with any PSR-14 dispatcher.
- Without a container, an array argument must still be a genuine PHP callable (a static method); if it is not,
  `on()` throws `InvalidArgumentException`.
- A malformed array (not exactly two elements, a first element that is neither object nor string, a non-string
  method, or an empty service id) throws `InvalidArgumentException` at registration.
- The service id is *not* checked against `$container->has()` at registration: a mutable PSR-11 container may be
  populated later. An unknown id surfaces as the container's own `NotFoundExceptionInterface` at dispatch.
- A service that resolves to a non-object, or to an object without that public method, throws `RuntimeException` at
  dispatch — PSR-11 `get()` returns `mixed`, so nothing can be verified earlier.
- Static analysis cannot check the listener method's parameter type against the event type. That is the price of
  deferral; the callable form of `on()` remains the type-checked path.

Matching and ordering:

- An event matches every registered type `$type` for which `$event instanceof $type` — its own class, parent
  classes, and interfaces alike.
- All matching listeners are merged into one list and sorted by priority descending. Higher priority runs first.
- Ties are broken by registration order across the whole provider (a monotonic sequence number assigned in `on()`),
  so ordering does not depend on PHP's sort stability or on which type a listener was registered against.
- Type specificity does not affect ordering; priority is the only knob.

Caching:

- Resolved listener lists are cached keyed by `$event::class`. The cache holds the wrapper closures, so a deferred
  listener still resolves its service afresh on every dispatch.
- `on()` clears the cache entirely. Correctness over cleverness: registration is rare, dispatch is hot.

## `StoppableEventTrait`

```php
trait StoppableEventTrait
{
    private bool $propagationStopped = false;

    public function isPropagationStopped(): bool;

    public function stopPropagation(): void;
}
```

- The trait does not declare `implements IStoppableEvent`; the using class does. This keeps the trait usable by a
  class that only wants the two methods.
- No `#[Override]` inside the trait: it would fail to compile in a class that uses the trait without declaring the
  interface.

## Usage

```php
use Manychois\PhpStrong\Events\EventDispatcher;
use Manychois\PhpStrong\Events\ListenerProvider;
use Manychois\PhpStrong\Events\StoppableEventTrait;
use Psr\EventDispatcher\StoppableEventInterface;

final class UserRegistered implements StoppableEventInterface
{
    use StoppableEventTrait;

    public function __construct(public readonly string $email) {}
}

$provider = new ListenerProvider($container);
$provider->on(UserRegistered::class, $sendWelcomeMail, 10);
$provider->on(UserRegistered::class, [AuditLog::class, 'onUserRegistered']); // resolved on dispatch

$event = (new EventDispatcher($provider))->dispatch(new UserRegistered('a@example.com'));
```

## Testing

Unit tests only; no HTTP fixtures or external processes. Target 100% coverage, matching the other modules.

`EventDispatcherTest`:

- Listeners run in the order the provider yields them; the dispatched instance is returned.
- An event with no listeners is returned untouched.
- A stoppable event already stopped before dispatch invokes no listener.
- A listener that calls `stopPropagation()` prevents later listeners from running.
- A non-stoppable event runs every listener regardless of state.
- An exception thrown by a listener propagates and later listeners do not run.
- Works with a foreign `ListenerProviderInterface` implementation (a test double).

`ListenerProviderTest`:

- Priority descending ordering; equal priorities keep registration order.
- Matching by parent class and by interface, merged with exact-class matches under one priority sort.
- An unrelated event yields no listeners.
- Registering after a first `getListenersForEvent()` call is visible on the next call (cache invalidation).
- Repeated calls for the same event class return the same ordering (cache hit path).
- A non-existent class or interface name throws `InvalidArgumentException`.
- `on()` returns the provider for chaining.
- A deferred `[id, method]` listener resolves the service from the container at dispatch, not at registration, and
  re-resolves on a second dispatch.
- An array listener without a container: a static method is accepted and called directly; a non-static one throws
  `InvalidArgumentException`.
- An `[$instance, 'method']` listener is called directly, with and without a container present, and is never
  resolved through the container.
- An `[$instance, 'noSuchMethod']` listener throws `InvalidArgumentException` at registration.
- Malformed array listeners (wrong length, non-string elements, empty id) throw `InvalidArgumentException`.
- An unknown service id surfaces the container's `NotFoundExceptionInterface` at dispatch.
- A service resolving to a non-object, or lacking the method, throws `RuntimeException` at dispatch.

`StoppableEventTraitTest`:

- Defaults to not stopped; `stopPropagation()` makes `isPropagationStopped()` true and is idempotent.

## Quality gates

`composer phpcbf` → `composer phpcs` → `composer phpstan` → `composer test`, all clean before the work is done.
