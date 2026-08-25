# PSR-14 Event Dispatcher — `Manychois\PhpStrong\Events`

An implementation of `Psr\EventDispatcher\EventDispatcherInterface` that dispatches an event to every listener
returned by a `Psr\EventDispatcher\ListenerProviderInterface`. `EventDispatcher` accepts any PSR-14 listener
provider. `ListenerProvider`, the provider implementation in this package, matches listeners by `instanceof` — a
listener registered against a parent class or an interface also matches its subclasses/implementors. The optional
container passed to `ListenerProvider` is only consulted to resolve deferred `[$serviceId, $method]` listeners.

```php
use Manychois\PhpStrong\Events\EventDispatcher;
use Manychois\PhpStrong\Events\ListenerProvider;
use Manychois\PhpStrong\Events\StoppableEventTrait;
use Psr\EventDispatcher\StoppableEventInterface;

final class UserRegistered implements StoppableEventInterface
{
    use StoppableEventTrait;

    public function __construct(public readonly string $email)
    {
    }
}

$provider = new ListenerProvider($container);
$provider->on(UserRegistered::class, static fn (UserRegistered $e) => $mailer->welcome($e->email), 10);
$provider->on(UserRegistered::class, [AuditLog::class, 'onUserRegistered']); // resolved on dispatch

$event = (new EventDispatcher($provider))->dispatch(new UserRegistered('a@example.com'));
```

## `EventDispatcher`

| Method | Notes |
| ------ | ----- |
| `__construct(ListenerProviderInterface $provider)` | The provider consulted for every dispatched event. |
| `dispatch(object $event): object` | Returns the same instance passed in. Checks `isPropagationStopped()` before each listener; a listener's return value is discarded; an exception thrown by a listener is not caught, so it propagates to the caller and no later listener runs. |

## `ListenerProvider`

| Method | Notes |
| ------ | ----- |
| `__construct(?ContainerInterface $container = null)` | Container used to resolve deferred `[$serviceId, $method]` listeners. |
| `on(string $eventType, callable\|array $listener, int $priority = 0): static` | Registers a listener for an event type, its subclasses, and its implementors. Higher priority runs first. Returns `$this` for chaining. |
| `getListenersForEvent(object $event): iterable` | Returns the listeners matching the event, most important first. |

## `StoppableEventTrait`

Implements the members of `Psr\EventDispatcher\StoppableEventInterface`. The using class must still declare
`implements StoppableEventInterface` itself; the trait carries no `#[Override]` attributes so it stays usable by a
class that only wants the two methods.

| Method | Notes |
| ------ | ----- |
| `isPropagationStopped(): bool` | Returns `true` once `stopPropagation()` has been called, `false` otherwise. |
| `stopPropagation(): void` | Stops the event from reaching any listener that has not run yet. |

## Listener forms

`on()` accepts three forms for `$listener`:

- A plain `callable` that is not an `array` (closure, first-class callable, function name, etc.) — called as-is.
- `[$instance, $method]` — an object plus a method name; passed through `toCallable()`, which recognises the object
  target and returns the array unchanged. `$instance` must already exist at registration time, and the listener is
  never resolved through the container even when one is configured.
- `[$serviceId, $method]` — a string service identifier plus a method name; requires a container to have been
  passed to the constructor. The service is resolved from the container on every dispatch (not cached across
  dispatches, not resolved at registration time), so registering a service listener without a container succeeds
  only if the array is otherwise `callable` on its own; deferred resolution is not checked by static analysis and
  can fail at dispatch time even though `on()` accepted it. When a container is configured, `[SomeClass::class,
  'staticMethod']` stops meaning "call the static method" and instead means "resolve the service `SomeClass` from
  the container and call `staticMethod` on it" — the presence of a container changes how a string target is
  interpreted.

## Ordering

Listeners run in a single priority-descending order: higher `priority` first, and listeners with equal priority run
in the order they were registered (`on()` call order), independent of the event type they were registered against.
Listeners registered against a parent class or an interface compete in the same sort as listeners registered
against the concrete event class — type specificity does not affect ordering. Resolved listener lists are cached
per concrete event class; the cache is cleared whenever `on()` is called again.

## Errors

`on()` throws `InvalidArgumentException` when:

- `$eventType` is not an existing class or interface.
- An array `$listener` is malformed — not exactly `[$target, $method]`, `$method` is not a non-empty string, or
  `$target` is not a non-empty string/object.
- An array `$listener` with an object `$target` (or a string `$target` with no container configured) is not itself
  `callable`.

`dispatch()` throws `RuntimeException` when a deferred `[$serviceId, $method]` listener resolves to a service that
is not an object, or whose `$method` is not callable on it.

Resolving a deferred `$serviceId` from the container also propagates the container's own
`Psr\Container\NotFoundExceptionInterface` when the identifier is unknown, and any other exception the container's
`get()` may throw.
