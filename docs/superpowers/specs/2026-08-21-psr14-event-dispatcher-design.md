# PSR-14 Event Dispatcher — Design

Date: 2026-08-21
Module: `Manychois\PhpStrong\EventDispatcher`

## Goal

Ship a concrete, strongly-typed implementation of PSR-14 (`psr/event-dispatcher`): a dispatcher, a type-based
listener provider with priorities, and a trait for stoppable events.

## Scope

In scope:

- `EventDispatcher` — implements `Psr\EventDispatcher\EventDispatcherInterface`.
- `ListenerProvider` — implements `Psr\EventDispatcher\ListenerProviderInterface`; mutable, type-based, prioritised.
- `StoppableEventTrait` — reusable implementation of `Psr\EventDispatcher\StoppableEventInterface` members.

Out of scope (deliberately):

- Container-resolved (lazy) listeners; no coupling to `Manychois\PhpStrong\DependencyInjection`.
- Aggregate/delegating provider composing several providers.
- Listener subscriber objects (a class declaring many listeners at once).
- Once-only listeners, listener removal, error/exception events.

## Dependencies and layout

- `composer.json`: require `psr/event-dispatcher: ^1.0`; add `psr-14` and `event-dispatcher` keywords.
- `src/EventDispatcher/EventDispatcher.php`, `ListenerProvider.php`, `StoppableEventTrait.php`.
- `tests/EventDispatcher/` mirrors `src/`.
- `docs/event-dispatcher.md` — reference page in the style of `docs/dependency-injection.md`.
- `README.md` — add the module to the module list.

Imports follow the project standard: `use Psr\EventDispatcher\EventDispatcherInterface as IEventDispatcher;`,
`ListenerProviderInterface as IListenerProvider`, `StoppableEventInterface as IStoppableEvent`.

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

## `ListenerProvider`

```php
final class ListenerProvider implements IListenerProvider
{
    public function on(string $eventType, callable $listener, int $priority = 0): static;

    public function getListenersForEvent(object $event): iterable;
}
```

Registration:

- `on()` is chainable and may be called at any time, including after dispatching has begun.
- `$eventType` must name an existing class or interface (`class_exists()` or `interface_exists()`); otherwise
  `InvalidArgumentException`.
- Duplicate registrations are allowed; the same listener registered twice is called twice.
- Typing: `@phpstan-param class-string<T> $eventType` with `@phpstan-param callable(T):void $listener` under
  `@template T of object`, so a listener whose parameter type does not match the event type fails static analysis.

Matching and ordering:

- An event matches every registered type `$type` for which `$event instanceof $type` — its own class, parent
  classes, and interfaces alike.
- All matching listeners are merged into one list and sorted by priority descending. Higher priority runs first.
- Ties are broken by registration order across the whole provider (a monotonic sequence number assigned in `on()`),
  so ordering does not depend on PHP's sort stability or on which type a listener was registered against.
- Type specificity does not affect ordering; priority is the only knob.

Caching:

- Resolved listener lists are cached keyed by `$event::class`.
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
use Manychois\PhpStrong\EventDispatcher\EventDispatcher;
use Manychois\PhpStrong\EventDispatcher\ListenerProvider;
use Manychois\PhpStrong\EventDispatcher\StoppableEventTrait;
use Psr\EventDispatcher\StoppableEventInterface;

final class UserRegistered implements StoppableEventInterface
{
    use StoppableEventTrait;

    public function __construct(public readonly string $email) {}
}

$provider = new ListenerProvider();
$provider->on(UserRegistered::class, $sendWelcomeMail, 10);
$provider->on(UserRegistered::class, $auditLog);

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

`StoppableEventTraitTest`:

- Defaults to not stopped; `stopPropagation()` makes `isPropagationStopped()` true and is idempotent.

## Quality gates

`composer phpcbf` → `composer phpcs` → `composer phpstan` → `composer test`, all clean before the work is done.
