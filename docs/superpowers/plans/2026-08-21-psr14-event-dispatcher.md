# PSR-14 Event Dispatcher Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ship `Manychois\PhpStrong\EventDispatcher` — a PSR-14 dispatcher, a prioritised type-based listener provider that can defer instance-method listeners to a PSR-11 container, and a trait for stoppable events.

**Architecture:** `EventDispatcher` holds only a `ListenerProviderInterface` and calls `$listener($event)` over whatever it yields, stopping early on a stopped `StoppableEventInterface`. `ListenerProvider` keeps a registry keyed by event type, matched with `instanceof` so parent classes and interfaces match too, merged and sorted by priority (ties by registration order) and cached per event class. Array listeners are normalised to callables at registration: `[$instance, 'method']` passes through, `[$serviceId, 'method']` becomes a closure that resolves the service from the container on every call. `StoppableEventTrait` supplies the two interface methods without imposing inheritance.

**Tech Stack:** PHP 8.5, `psr/event-dispatcher` ^1.0, `psr/container` ^2 (interface only), PHPUnit 13, PHPStan max + strict rules, PHP_CodeSniffer with Slevomat.

**Spec:** `docs/superpowers/specs/2026-08-21-psr14-event-dispatcher-design.md`

## Global Constraints

- PHP >= 8.5; library code under `Manychois\PhpStrong\EventDispatcher` (PSR-4 `src/EventDispatcher/`), tests under `Manychois\PhpStrongTests\EventDispatcher` (`tests/EventDispatcher/`).
- Quality gates after every task: `composer phpcbf` → `composer phpcs` → `composer phpstan` → `composer test` — all must pass. `composer phpstan` and `composer phpcbf` end with `|| true`, so read their output rather than trusting the exit code.
- 100% statement coverage of `src/EventDispatcher/` at the end of Task 6 (check `coverage/clover.xml`).
- No dependency on `Manychois\PhpStrong\DependencyInjection`. The container is used through `Psr\Container\ContainerInterface` only.
- `phpcs` only scans `./src`; test files are not style-checked, but keep them in the same house style anyway.
- Global PHP functions are called unqualified (matching existing `src/`); only global *constants* take a leading backslash. `@template` comes first in a docblock, before `@param`.
- Coding standard (`docs/internal/php-coding-standard.md`): `declare(strict_types=1)` with blank lines around it; interfaces imported with `IXxx` aliases (`EventDispatcherInterface as IEventDispatcher`, `ListenerProviderInterface as IListenerProvider`, `StoppableEventInterface as IStoppableEvent`, `ContainerInterface as IContainer`); `#[Override]` on every interface implementation; `#region implements IInterface` … `#endregion` blocks; methods outside regions placed above the region blocks; within a group, sorted static-then-instance, public-then-private, alphabetically; PHPDoc on all public/protected members with one blank line between annotation types; `@phpstan-param`/`@phpstan-return` for precise types; global constants written with a leading backslash.
- Exceptions: `InvalidArgumentException` for bad registration input (unknown event type, malformed or non-callable array listener); `RuntimeException` for a service that resolves to a non-object or lacks the listener method; listener exceptions are never caught by the dispatcher.
- PHPUnit enforces a 3-second per-test time limit; all tests here are pure unit tests with no I/O.
- Tests use `#[Test]` attributes and `final class`, method naming `member_behaviourDescription`, `self::assert*`, matching `tests/DependencyInjection/ContainerTest.php`.
- Commit after every task; message style `feat(event-dispatcher): …`, `test(event-dispatcher): …`, `docs: …`, each ending with the repo's Claude co-author trailer:

```
Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>
Claude-Session: https://claude.ai/code/session_01D7cq6B6pJ8BaHJQFKYFtUd
```

## File Structure

| File | Responsibility |
| ---- | -------------- |
| `src/EventDispatcher/StoppableEventTrait.php` | The two `StoppableEventInterface` members, reusable by any event class. |
| `src/EventDispatcher/EventDispatcher.php` | PSR-14 dispatcher: iterate the provider's listeners, honour propagation stop, return the event. |
| `src/EventDispatcher/ListenerProvider.php` | Registration (`on()`), array-listener normalisation, `instanceof` matching, priority sort, per-event-class cache. |
| `tests/EventDispatcher/StoppableEventTraitTest.php` | Trait behaviour, via a local test event class. |
| `tests/EventDispatcher/EventDispatcherTest.php` | Dispatch order, stop semantics, exception propagation, foreign providers. |
| `tests/EventDispatcher/ListenerProviderTest.php` | Ordering, inheritance matching, cache invalidation, array/service listeners, all error paths. |
| `docs/event-dispatcher.md` | Reference page in the style of `docs/dependency-injection.md`. |
| `composer.json`, `README.md` | Dependency, keywords, module listing. |

---

### Task 1: Add the PSR-14 dependency and the stoppable event trait

**Files:**
- Modify: `composer.json`
- Create: `src/EventDispatcher/StoppableEventTrait.php`
- Test: `tests/EventDispatcher/StoppableEventTraitTest.php`

**Interfaces:**
- Consumes: nothing.
- Produces: `Manychois\PhpStrong\EventDispatcher\StoppableEventTrait` with `private bool $propagationStopped`, `public function isPropagationStopped(): bool`, `public function stopPropagation(): void`. Tasks 2 and 3 use it in their test event classes. Also makes `Psr\EventDispatcher\*` autoloadable for every later task.

- [ ] **Step 1: Add the dependency**

Run: `composer require psr/event-dispatcher:^1.0`

Expected: `composer.json` gains `"psr/event-dispatcher": "^1.0"` under `require`, and `vendor/psr/event-dispatcher/` exists. This needs network access; if it is unavailable, stop and report rather than hand-editing `composer.lock`.

- [ ] **Step 2: Add the keywords**

In `composer.json`, extend the `keywords` array with `"psr-14"` and `"event-dispatcher"`, keeping the existing entries and the file's 2-space indentation. The PSR numbers are listed in ascending order, so `"psr-14"` goes between `"psr-11"` and `"psr-17"`; put `"event-dispatcher"` next to `"container"` in the trailing group of topic keywords.

- [ ] **Step 3: Write the failing test**

Create `tests/EventDispatcher/StoppableEventTraitTest.php`:

```php
<?php

declare(strict_types=1);

namespace Manychois\PhpStrongTests\EventDispatcher;

use Manychois\PhpStrong\EventDispatcher\StoppableEventTrait;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\StoppableEventInterface as IStoppableEvent;

final class StoppableEventTraitTest extends TestCase
{
    #[Test]
    public function isPropagationStopped_falseByDefault(): void
    {
        $event = new class implements IStoppableEvent {
            use StoppableEventTrait;
        };

        self::assertFalse($event->isPropagationStopped());
    }

    #[Test]
    public function stopPropagation_flipsTheFlagAndIsIdempotent(): void
    {
        $event = new class implements IStoppableEvent {
            use StoppableEventTrait;
        };

        $event->stopPropagation();
        self::assertTrue($event->isPropagationStopped());

        $event->stopPropagation();
        self::assertTrue($event->isPropagationStopped());
    }
}
```

- [ ] **Step 4: Run the test to verify it fails**

Run: `./vendor/bin/phpunit --no-coverage tests/EventDispatcher/StoppableEventTraitTest.php`

Expected: FAIL — `Trait "Manychois\PhpStrong\EventDispatcher\StoppableEventTrait" not found`.

- [ ] **Step 5: Write the trait**

Create `src/EventDispatcher/StoppableEventTrait.php`:

```php
<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\EventDispatcher;

/**
 * Implements the members of `Psr\EventDispatcher\StoppableEventInterface`.
 * The using class must declare `implements StoppableEventInterface` itself; this trait deliberately carries no
 * `#[Override]` attributes so it stays usable by a class that only wants the two methods.
 */
trait StoppableEventTrait
{
    private bool $propagationStopped = false;

    /**
     * Tells whether a listener has stopped the event from reaching later listeners.
     *
     * @return bool `true` once `stopPropagation()` has been called.
     */
    public function isPropagationStopped(): bool
    {
        return $this->propagationStopped;
    }

    /**
     * Stops the event from reaching any listener that has not run yet.
     */
    public function stopPropagation(): void
    {
        $this->propagationStopped = true;
    }
}
```

- [ ] **Step 6: Run the test to verify it passes**

Run: `./vendor/bin/phpunit --no-coverage tests/EventDispatcher/StoppableEventTraitTest.php`

Expected: `OK (2 tests, 3 assertions)`.

- [ ] **Step 7: Quality gates**

Run: `composer phpcbf`, then `composer phpcs`, then `composer phpstan`, then `composer test`.

Expected: no `phpcs` violations; PHPStan reports `[OK] No errors`; the full suite passes.

- [ ] **Step 8: Commit**

```bash
git add composer.json composer.lock src/EventDispatcher/StoppableEventTrait.php tests/EventDispatcher/StoppableEventTraitTest.php
git commit -m "feat(event-dispatcher): add psr/event-dispatcher and StoppableEventTrait"
```

---

### Task 2: EventDispatcher

**Files:**
- Create: `src/EventDispatcher/EventDispatcher.php`
- Test: `tests/EventDispatcher/EventDispatcherTest.php`

**Interfaces:**
- Consumes: `StoppableEventTrait` from Task 1 (test events only).
- Produces: `final class EventDispatcher implements IEventDispatcher` with `__construct(IListenerProvider $provider)` and `dispatch(object $event): object` returning the same instance (`@template T of object`, `@phpstan-param T $event`, `@phpstan-return T`). Task 4 dispatches through it in an end-to-end test.

This task uses a hand-written fake provider, so it does not depend on Task 3.

- [ ] **Step 1: Write the failing test**

Create `tests/EventDispatcher/EventDispatcherTest.php`:

```php
<?php

declare(strict_types=1);

namespace Manychois\PhpStrongTests\EventDispatcher;

use LogicException;
use Manychois\PhpStrong\EventDispatcher\EventDispatcher;
use Manychois\PhpStrong\EventDispatcher\StoppableEventTrait;
use Override;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\ListenerProviderInterface as IListenerProvider;
use Psr\EventDispatcher\StoppableEventInterface as IStoppableEvent;
use stdClass;

final class EventDispatcherTest extends TestCase
{
    #[Test]
    public function dispatch_callsListenersInProviderOrderAndReturnsTheSameEvent(): void
    {
        $calls = [];
        $event = new stdClass();
        $provider = new FakeListenerProvider([
            static function (object $e) use (&$calls): void {
                $calls[] = 'first';
            },
            static function (object $e) use (&$calls): void {
                $calls[] = 'second';
            },
        ]);

        $returned = (new EventDispatcher($provider))->dispatch($event);

        self::assertSame($event, $returned);
        self::assertSame(['first', 'second'], $calls);
    }

    #[Test]
    public function dispatch_withoutListenersReturnsTheEventUntouched(): void
    {
        $event = new stdClass();

        self::assertSame($event, (new EventDispatcher(new FakeListenerProvider([])))->dispatch($event));
    }

    #[Test]
    public function dispatch_alreadyStoppedEventCallsNoListener(): void
    {
        $called = false;
        $event = new StoppableTestEvent();
        $event->stopPropagation();
        $provider = new FakeListenerProvider([
            static function (object $e) use (&$called): void {
                $called = true;
            },
        ]);

        (new EventDispatcher($provider))->dispatch($event);

        self::assertFalse($called);
    }

    #[Test]
    public function dispatch_stopPropagationPreventsLaterListeners(): void
    {
        $calls = [];
        $provider = new FakeListenerProvider([
            static function (StoppableTestEvent $e) use (&$calls): void {
                $calls[] = 'first';
                $e->stopPropagation();
            },
            static function (StoppableTestEvent $e) use (&$calls): void {
                $calls[] = 'second';
            },
        ]);

        (new EventDispatcher($provider))->dispatch(new StoppableTestEvent());

        self::assertSame(['first'], $calls);
    }

    #[Test]
    public function dispatch_nonStoppableEventRunsEveryListener(): void
    {
        $calls = 0;
        $listener = static function (object $e) use (&$calls): void {
            $calls++;
        };

        (new EventDispatcher(new FakeListenerProvider([$listener, $listener])))->dispatch(new stdClass());

        self::assertSame(2, $calls);
    }

    #[Test]
    public function dispatch_listenerExceptionPropagatesAndStopsLaterListeners(): void
    {
        $reached = false;
        $provider = new FakeListenerProvider([
            static function (object $e): void {
                throw new LogicException('listener failed');
            },
            static function (object $e) use (&$reached): void {
                $reached = true;
            },
        ]);

        try {
            (new EventDispatcher($provider))->dispatch(new stdClass());
            self::fail('Expected LogicException.');
        } catch (LogicException $ex) {
            self::assertSame('listener failed', $ex->getMessage());
        }

        self::assertFalse($reached);
    }
}

final class StoppableTestEvent implements IStoppableEvent
{
    use StoppableEventTrait;
}

final class FakeListenerProvider implements IListenerProvider
{
    /**
     * @param list<callable> $listeners
     */
    public function __construct(private readonly array $listeners)
    {
    }

    #[Override]
    public function getListenersForEvent(object $event): iterable
    {
        return $this->listeners;
    }
}
```

Note: `StoppableTestEvent` and `FakeListenerProvider` are declared in this test file and reused by Task 4's tests via the same namespace, so do not move or rename them.

- [ ] **Step 2: Run the test to verify it fails**

Run: `./vendor/bin/phpunit --no-coverage tests/EventDispatcher/EventDispatcherTest.php`

Expected: FAIL — `Class "Manychois\PhpStrong\EventDispatcher\EventDispatcher" not found`.

- [ ] **Step 3: Write the dispatcher**

Create `src/EventDispatcher/EventDispatcher.php`:

```php
<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\EventDispatcher;

use Override;
use Psr\EventDispatcher\EventDispatcherInterface as IEventDispatcher;
use Psr\EventDispatcher\ListenerProviderInterface as IListenerProvider;
use Psr\EventDispatcher\StoppableEventInterface as IStoppableEvent;

/**
 * PSR-14 dispatcher that passes an event to every listener the provider yields for it.
 * Listeners are called as `$listener($event)` and their return values are discarded; an exception thrown by a
 * listener is not caught, so it reaches the caller and no later listener runs.
 */
final class EventDispatcher implements IEventDispatcher
{
    private readonly IListenerProvider $provider;

    /**
     * Creates a dispatcher backed by a listener provider.
     *
     * @param IListenerProvider $provider The provider consulted for every dispatched event.
     */
    public function __construct(IListenerProvider $provider)
    {
        $this->provider = $provider;
    }

    #region implements IEventDispatcher

    /**
     * Passes the event to each listener until they are exhausted or propagation is stopped.
     *
     * @template T of object
     *
     * @param object $event The event to dispatch.
     *
     * @return object The same instance that was passed in, after the listeners have run.
     *
     * @phpstan-param T $event
     *
     * @phpstan-return T
     */
    #[Override]
    public function dispatch(object $event): object
    {
        $stoppable = $event instanceof IStoppableEvent ? $event : null;

        foreach ($this->provider->getListenersForEvent($event) as $listener) {
            if ($stoppable?->isPropagationStopped() === true) {
                break;
            }

            $listener($event);
        }

        return $event;
    }

    #endregion
}
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `./vendor/bin/phpunit --no-coverage tests/EventDispatcher/EventDispatcherTest.php`

Expected: `OK (6 tests, ...)`.

- [ ] **Step 5: Quality gates**

Run: `composer phpcbf`, then `composer phpcs`, then `composer phpstan`, then `composer test`.

Expected: clean. If PHPStan objects to `$listener($event)` because `getListenersForEvent()` is typed as a bare `iterable` in the PSR interface, add `@phpstan-var callable $listener` above the `foreach` body rather than casting; do not silence it in `phpstan.dist.neon`.

- [ ] **Step 6: Commit**

```bash
git add src/EventDispatcher/EventDispatcher.php tests/EventDispatcher/EventDispatcherTest.php
git commit -m "feat(event-dispatcher): add PSR-14 EventDispatcher"
```

---

### Task 3: ListenerProvider — callable listeners, matching and ordering

**Files:**
- Create: `src/EventDispatcher/ListenerProvider.php`
- Test: `tests/EventDispatcher/ListenerProviderTest.php`

**Interfaces:**
- Consumes: nothing from earlier tasks.
- Produces: `final class ListenerProvider implements IListenerProvider` with `__construct(?IContainer $container = null)`, `on(string $eventType, callable|array $listener, int $priority = 0): static`, `getListenersForEvent(object $event): iterable`, and the private helper `private function toCallable(array $listener): callable` (a stub in this task, completed in Task 4). Internal state: `array<string, list<array{callable, int, int}>> $registry`, `array<string, list<callable>> $cache`, `int $sequence`. Tasks 4 and 5 extend this class.

- [ ] **Step 1: Write the failing tests**

Create `tests/EventDispatcher/ListenerProviderTest.php`:

```php
<?php

declare(strict_types=1);

namespace Manychois\PhpStrongTests\EventDispatcher;

use InvalidArgumentException;
use Manychois\PhpStrong\EventDispatcher\ListenerProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use stdClass;

interface AnimalEventInterface
{
}

class AnimalEvent implements AnimalEventInterface
{
}

final class DogEvent extends AnimalEvent
{
}

final class ListenerProviderTest extends TestCase
{
    #[Test]
    public function on_returnsTheProviderForChaining(): void
    {
        $provider = new ListenerProvider();

        self::assertSame($provider, $provider->on(DogEvent::class, static function (DogEvent $e): void {
        }));
    }

    #[Test]
    public function getListenersForEvent_sortsByPriorityThenRegistrationOrder(): void
    {
        $calls = [];
        $record = static fn (string $name): callable => static function (DogEvent $e) use (&$calls, $name): void {
            $calls[] = $name;
        };

        $provider = new ListenerProvider();
        $provider->on(DogEvent::class, $record('low'), -5);
        $provider->on(DogEvent::class, $record('mid-first'), 10);
        $provider->on(DogEvent::class, $record('mid-second'), 10);
        $provider->on(DogEvent::class, $record('high'), 100);

        foreach ($provider->getListenersForEvent(new DogEvent()) as $listener) {
            $listener(new DogEvent());
        }

        self::assertSame(['high', 'mid-first', 'mid-second', 'low'], $calls);
    }

    #[Test]
    public function getListenersForEvent_matchesParentClassAndInterfaceUnderOnePrioritySort(): void
    {
        $calls = [];
        $record = static fn (string $name): callable => static function (object $e) use (&$calls, $name): void {
            $calls[] = $name;
        };

        $provider = new ListenerProvider();
        $provider->on(AnimalEventInterface::class, $record('interface'), 100);
        $provider->on(AnimalEvent::class, $record('parent'), 50);
        $provider->on(DogEvent::class, $record('exact'), 10);

        foreach ($provider->getListenersForEvent(new DogEvent()) as $listener) {
            $listener(new DogEvent());
        }

        self::assertSame(['interface', 'parent', 'exact'], $calls);
    }

    #[Test]
    public function getListenersForEvent_ignoresUnrelatedEvents(): void
    {
        $provider = new ListenerProvider();
        $provider->on(DogEvent::class, static function (DogEvent $e): void {
        });

        self::assertSame([], [...$provider->getListenersForEvent(new stdClass())]);
    }

    #[Test]
    public function on_rejectsAnUnknownEventType(): void
    {
        $provider = new ListenerProvider();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Event type "No\Such\Type" is not an existing class or interface.');

        $provider->on('No\Such\Type', static function (object $e): void {
        });
    }
}
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `./vendor/bin/phpunit --no-coverage tests/EventDispatcher/ListenerProviderTest.php`

Expected: FAIL — `Class "Manychois\PhpStrong\EventDispatcher\ListenerProvider" not found`.

- [ ] **Step 3: Write the provider**

Create `src/EventDispatcher/ListenerProvider.php`. `toCallable()` is a deliberate stub here — Task 4 fills it in.

```php
<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\EventDispatcher;

use InvalidArgumentException;
use Override;
use Psr\Container\ContainerInterface as IContainer;
use Psr\EventDispatcher\ListenerProviderInterface as IListenerProvider;

/**
 * Listener provider that matches listeners by event type and orders them by priority.
 * A listener registered against a parent class or an interface also matches, and all matches compete in one
 * priority order regardless of the type they were registered against.
 */
final class ListenerProvider implements IListenerProvider
{
    private readonly ?IContainer $container;

    /**
     * Registered listeners keyed by event type, each entry being `[callable, priority, sequence]`.
     *
     * @var array<string, list<array{callable, int, int}>>
     */
    private array $registry = [];

    /**
     * Resolved listener lists keyed by concrete event class name.
     *
     * @var array<string, list<callable>>
     */
    private array $cache = [];

    /**
     * Registration counter, used to keep equal priorities in registration order.
     */
    private int $sequence = 0;

    /**
     * Creates a listener provider.
     *
     * @param ?IContainer $container Container used to resolve deferred `[$serviceId, $method]` listeners.
     */
    public function __construct(?IContainer $container = null)
    {
        $this->container = $container;
    }

    /**
     * Registers a listener for an event type, its subclasses, and its implementors.
     *
     * @template T of object
     *
     * @param string $eventType The event class or interface to listen for.
     * @param callable|array $listener The listener, or `[$instance, $method]`, or `[$serviceId, $method]`.
     * @param int $priority Higher runs first; equal priorities run in registration order.
     *
     * @return static The same provider, for chaining.
     *
     * @throws InvalidArgumentException if the event type does not exist, or the array listener is malformed.
     *
     * @phpstan-param class-string<T> $eventType
     * @phpstan-param callable(T):mixed|array{string|object, string} $listener
     */
    public function on(string $eventType, callable|array $listener, int $priority = 0): static
    {
        if (!class_exists($eventType) && !interface_exists($eventType)) {
            throw new InvalidArgumentException(
                sprintf('Event type "%s" is not an existing class or interface.', $eventType)
            );
        }

        $callable = is_array($listener) ? $this->toCallable($listener) : $listener;
        $this->registry[$eventType][] = [$callable, $priority, $this->sequence];
        $this->sequence++;
        $this->cache = [];

        return $this;
    }

    /**
     * Normalises an array listener into a callable.
     *
     * @param array<mixed> $listener The array form of a listener.
     *
     * @return callable The listener to call with the event.
     *
     * @throws InvalidArgumentException if the array is not a usable listener.
     */
    private function toCallable(array $listener): callable
    {
        if (!is_callable($listener)) {
            throw new InvalidArgumentException('The array listener is not callable.');
        }

        return $listener;
    }

    #region implements IListenerProvider

    /**
     * Returns the listeners that match the event, most important first.
     *
     * @param object $event The event about to be dispatched.
     *
     * @return iterable The matching listeners in call order.
     *
     * @phpstan-return list<callable>
     */
    #[Override]
    public function getListenersForEvent(object $event): iterable
    {
        $class = $event::class;
        if (array_key_exists($class, $this->cache)) {
            return $this->cache[$class];
        }

        $matched = [];
        foreach ($this->registry as $type => $entries) {
            if (!($event instanceof $type)) {
                continue;
            }

            foreach ($entries as $entry) {
                $matched[] = $entry;
            }
        }

        usort($matched, static function (array $a, array $b): int {
            $byPriority = $b[1] <=> $a[1];

            return $byPriority !== 0 ? $byPriority : $a[2] <=> $b[2];
        });

        $listeners = array_map(static fn (array $entry): callable => $entry[0], $matched);
        $this->cache[$class] = $listeners;

        return $listeners;
    }

    #endregion
}
```

Note on member order: `on()` and `toCallable()` sit above the `#region implements IListenerProvider` block because methods outside a region go first, public before private.

- [ ] **Step 4: Run the tests to verify they pass**

Run: `./vendor/bin/phpunit --no-coverage tests/EventDispatcher/ListenerProviderTest.php`

Expected: `OK (5 tests, ...)`.

- [ ] **Step 5: Quality gates**

Run: `composer phpcbf`, then `composer phpcs`, then `composer phpstan`, then `composer test`.

Expected: clean. Two likely PHPStan complaints and their fixes: `usort()` callback parameters need `@param array{callable, int, int} $a` style hints — add a full docblock to the closure if so; `array_map()` over `list<array{callable, int, int}>` should already infer `list<callable>`, and if it does not, build the list with an explicit `foreach` instead of adding an ignore.

- [ ] **Step 6: Commit**

```bash
git add src/EventDispatcher/ListenerProvider.php tests/EventDispatcher/ListenerProviderTest.php
git commit -m "feat(event-dispatcher): add type-based prioritised ListenerProvider"
```

---

### Task 4: Array and container-resolved listeners

**Files:**
- Modify: `src/EventDispatcher/ListenerProvider.php` (replace the `toCallable()` stub)
- Test: `tests/EventDispatcher/ListenerProviderTest.php` (append tests and helper classes)

**Interfaces:**
- Consumes: `ListenerProvider::toCallable()` from Task 3; `EventDispatcher` and `FakeListenerProvider` from Task 2.
- Produces: the final `toCallable()` behaviour — `[$instance, 'method']` passed through; `[$serviceId, 'method']` wrapped in a closure resolving from the container on every call; `InvalidArgumentException` on malformed or non-callable arrays; `RuntimeException` at dispatch when a service is not an object or lacks the method.

- [ ] **Step 1: Write the failing tests**

Append to `tests/EventDispatcher/ListenerProviderTest.php` — the helper classes go after the test class, and add `use Manychois\PhpStrong\EventDispatcher\EventDispatcher;`, `use Override;`, `use Psr\Container\ContainerInterface as IContainer;`, `use Psr\Container\NotFoundExceptionInterface as INotFoundException;`, `use RuntimeException;` to the imports:

```php
    #[Test]
    public function on_callsAnInstanceMethodListenerDirectly(): void
    {
        $spy = new ListenerSpy();
        $provider = new ListenerProvider();
        $provider->on(DogEvent::class, [$spy, 'handle']);

        foreach ($provider->getListenersForEvent(new DogEvent()) as $listener) {
            $listener(new DogEvent());
        }

        self::assertSame(1, $spy->calls);
    }

    #[Test]
    public function on_rejectsAnInstanceListenerWithoutThatMethod(): void
    {
        $provider = new ListenerProvider();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The array listener is not callable.');

        $provider->on(DogEvent::class, [new ListenerSpy(), 'noSuchMethod']);
    }

    #[Test]
    public function on_rejectsAMalformedArrayListener(): void
    {
        $provider = new ListenerProvider();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('An array listener must be [$target, $method] with a non-empty method name.');

        $provider->on(DogEvent::class, ['only-one-element']);
    }

    #[Test]
    public function on_withoutContainerRejectsANonStaticServiceReference(): void
    {
        $provider = new ListenerProvider();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The array listener is not callable.');

        $provider->on(DogEvent::class, [ListenerSpy::class, 'handle']);
    }

    #[Test]
    public function on_withoutContainerAcceptsAStaticMethodReference(): void
    {
        ListenerSpy::$staticCalls = 0;
        $provider = new ListenerProvider();
        $provider->on(DogEvent::class, [ListenerSpy::class, 'handleStatically']);

        foreach ($provider->getListenersForEvent(new DogEvent()) as $listener) {
            $listener(new DogEvent());
        }

        self::assertSame(1, ListenerSpy::$staticCalls);
    }

    #[Test]
    public function on_serviceReferenceResolvesOnEveryDispatchNotAtRegistration(): void
    {
        $spy = new ListenerSpy();
        $container = new FakeContainer(['spy' => $spy]);
        $provider = new ListenerProvider($container);
        $provider->on(DogEvent::class, ['spy', 'handle']);

        self::assertSame(0, $container->gets, 'Registration must not resolve the service.');

        $dispatcher = new EventDispatcher($provider);
        $dispatcher->dispatch(new DogEvent());
        $dispatcher->dispatch(new DogEvent());

        self::assertSame(2, $spy->calls);
        self::assertSame(2, $container->gets, 'The service is resolved once per dispatch.');
    }

    #[Test]
    public function on_serviceReferenceReceivesTheDispatchedEvent(): void
    {
        $spy = new ListenerSpy();
        $provider = new ListenerProvider(new FakeContainer(['spy' => $spy]));
        $provider->on(DogEvent::class, ['spy', 'handle']);
        $event = new DogEvent();

        (new EventDispatcher($provider))->dispatch($event);

        self::assertSame($event, $spy->lastEvent);
    }

    #[Test]
    public function getListenersForEvent_unknownServiceIdSurfacesTheContainerException(): void
    {
        $provider = new ListenerProvider(new FakeContainer([]));
        $provider->on(DogEvent::class, ['missing', 'handle']);

        $this->expectException(INotFoundException::class);

        (new EventDispatcher($provider))->dispatch(new DogEvent());
    }

    #[Test]
    public function getListenersForEvent_nonObjectServiceThrowsAtDispatch(): void
    {
        $provider = new ListenerProvider(new FakeContainer(['spy' => 'not-an-object']));
        $provider->on(DogEvent::class, ['spy', 'handle']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Service "spy" cannot handle the event with method "handle".');

        (new EventDispatcher($provider))->dispatch(new DogEvent());
    }

    #[Test]
    public function getListenersForEvent_serviceWithoutTheMethodThrowsAtDispatch(): void
    {
        $provider = new ListenerProvider(new FakeContainer(['spy' => new ListenerSpy()]));
        $provider->on(DogEvent::class, ['spy', 'noSuchMethod']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Service "spy" cannot handle the event with method "noSuchMethod".');

        (new EventDispatcher($provider))->dispatch(new DogEvent());
    }
```

Helper classes, appended after the test class in the same file:

```php
final class ListenerSpy
{
    public static int $staticCalls = 0;

    public int $calls = 0;

    public ?object $lastEvent = null;

    public static function handleStatically(object $event): void
    {
        self::$staticCalls++;
    }

    public function handle(object $event): void
    {
        $this->calls++;
        $this->lastEvent = $event;
    }
}

final class FakeContainer implements IContainer
{
    public int $gets = 0;

    /**
     * @param array<string, mixed> $services
     */
    public function __construct(private readonly array $services)
    {
    }

    #[Override]
    public function get(string $id): mixed
    {
        $this->gets++;
        if (!array_key_exists($id, $this->services)) {
            throw new ServiceNotFoundException(sprintf('Service "%s" is not registered.', $id));
        }

        return $this->services[$id];
    }

    #[Override]
    public function has(string $id): bool
    {
        return array_key_exists($id, $this->services);
    }
}

final class ServiceNotFoundException extends RuntimeException implements INotFoundException
{
}
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `./vendor/bin/phpunit --no-coverage tests/EventDispatcher/ListenerProviderTest.php`

Expected: the new tests FAIL — the malformed-array test reports the stub's `The array listener is not callable.` message instead of the expected one, and every service-reference test fails because `['spy', 'handle']` is not callable, so registration throws.

- [ ] **Step 3: Replace the `toCallable()` stub**

In `src/EventDispatcher/ListenerProvider.php`, add `use RuntimeException;` to the imports and replace `toCallable()` with:

```php
    /**
     * Normalises an array listener into a callable.
     * `[$instance, $method]` is used as-is; `[$serviceId, $method]` becomes a closure that resolves the service
     * from the container each time the listener runs.
     *
     * @param array<mixed> $listener The array form of a listener.
     *
     * @return callable The listener to call with the event.
     *
     * @throws InvalidArgumentException if the array is malformed, or is not callable and cannot be deferred.
     */
    private function toCallable(array $listener): callable
    {
        $target = $listener[0] ?? null;
        $method = $listener[1] ?? null;
        $malformed = count($listener) !== 2
            || !is_string($method)
            || $method === ''
            || (!is_object($target) && !is_string($target))
            || $target === '';
        if ($malformed) {
            throw new InvalidArgumentException(
                'An array listener must be [$target, $method] with a non-empty method name.'
            );
        }

        $container = $this->container;
        if (is_object($target) || $container === null) {
            if (!is_callable($listener)) {
                throw new InvalidArgumentException('The array listener is not callable.');
            }

            return $listener;
        }

        $serviceId = $target;

        return static function (object $event) use ($container, $serviceId, $method): mixed {
            $service = $container->get($serviceId);
            if (!is_object($service) || !is_callable([$service, $method])) {
                throw new RuntimeException(
                    sprintf('Service "%s" cannot handle the event with method "%s".', $serviceId, $method)
                );
            }

            return $service->$method($event);
        };
    }
```

- [ ] **Step 4: Run the tests to verify they pass**

Run: `./vendor/bin/phpunit --no-coverage tests/EventDispatcher/ListenerProviderTest.php`

Expected: `OK (15 tests, ...)`.

- [ ] **Step 5: Quality gates**

Run: `composer phpcbf`, then `composer phpcs`, then `composer phpstan`, then `composer test`.

Expected: clean. If PHPStan cannot narrow `$listener[0] ?? null` from `array<mixed>`, keep the explicit `is_object`/`is_string` checks as written — they are the narrowing; do not add an ignore.

- [ ] **Step 6: Commit**

```bash
git add src/EventDispatcher/ListenerProvider.php tests/EventDispatcher/ListenerProviderTest.php
git commit -m "feat(event-dispatcher): support instance and container-resolved listeners"
```

---

### Task 5: Cache behaviour

**Files:**
- Test: `tests/EventDispatcher/ListenerProviderTest.php` (append tests)

**Interfaces:**
- Consumes: `ListenerProvider` from Tasks 3 and 4, `ListenerSpy` and `FakeContainer` from Task 4.
- Produces: no production API; locks in that the cache is invalidated by `on()` and that a cached deferred listener still re-resolves its service.

If a test in this task fails, the fix belongs in `getListenersForEvent()`/`on()` in `src/EventDispatcher/ListenerProvider.php`; do not weaken the test.

- [ ] **Step 1: Write the tests**

Append to `tests/EventDispatcher/ListenerProviderTest.php`:

```php
    #[Test]
    public function getListenersForEvent_repeatedCallsReturnTheSameOrder(): void
    {
        $provider = new ListenerProvider();
        $first = static function (DogEvent $e): void {
        };
        $second = static function (DogEvent $e): void {
        };
        $provider->on(DogEvent::class, $first, 10);
        $provider->on(DogEvent::class, $second);

        $one = [...$provider->getListenersForEvent(new DogEvent())];
        $two = [...$provider->getListenersForEvent(new DogEvent())];

        self::assertSame([$first, $second], $one);
        self::assertSame($one, $two);
    }

    #[Test]
    public function on_afterAResolvedDispatchIsVisibleOnTheNextCall(): void
    {
        $provider = new ListenerProvider();
        $provider->on(DogEvent::class, static function (DogEvent $e): void {
        });

        self::assertCount(1, [...$provider->getListenersForEvent(new DogEvent())]);

        $provider->on(DogEvent::class, static function (DogEvent $e): void {
        }, 100);

        self::assertCount(2, [...$provider->getListenersForEvent(new DogEvent())]);
    }

    #[Test]
    public function on_registeringAnotherTypeInvalidatesAnUnrelatedCachedList(): void
    {
        $provider = new ListenerProvider();
        $provider->on(DogEvent::class, static function (DogEvent $e): void {
        });

        self::assertCount(1, [...$provider->getListenersForEvent(new DogEvent())]);

        $provider->on(AnimalEventInterface::class, static function (object $e): void {
        });

        self::assertCount(2, [...$provider->getListenersForEvent(new DogEvent())]);
    }
```

- [ ] **Step 2: Run the tests**

Run: `./vendor/bin/phpunit --no-coverage tests/EventDispatcher/ListenerProviderTest.php`

Expected: `OK (18 tests, ...)`. These should pass against Task 3's implementation; if one fails, the cache is not being cleared in `on()` — fix the production code.

- [ ] **Step 3: Quality gates**

Run: `composer phpcbf`, then `composer phpcs`, then `composer phpstan`, then `composer test`.

Expected: clean.

- [ ] **Step 4: Commit**

```bash
git add tests/EventDispatcher/ListenerProviderTest.php
git commit -m "test(event-dispatcher): cover listener cache invalidation"
```

---

### Task 6: Documentation and coverage verification

**Files:**
- Create: `docs/event-dispatcher.md`
- Modify: `README.md`

**Interfaces:**
- Consumes: the finished public API from Tasks 1–5.
- Produces: user-facing reference documentation; final coverage confirmation.

- [ ] **Step 1: Check how the other modules are listed**

Run: `grep -n "dependency-injection\|clock.md\|logging.md" README.md`

Expected: the module list / documentation links. Match that formatting exactly when adding the new row; if the file has no such list, add the link wherever the other `docs/*.md` pages are referenced.

- [ ] **Step 2: Write the reference page**

Create `docs/event-dispatcher.md`, following the shape of `docs/dependency-injection.md` (title line naming the PSR and namespace, a short intro, a quick-start code block, then one table per class). Required content:

- Title: `# PSR-14 Event Dispatcher — \`Manychois\PhpStrong\EventDispatcher\``.
- Intro: the dispatcher takes any PSR-14 listener provider; `ListenerProvider` matches by type including parents and interfaces; the container is optional and only used for deferred listeners.
- Quick start:

```php
use Manychois\PhpStrong\EventDispatcher\EventDispatcher;
use Manychois\PhpStrong\EventDispatcher\ListenerProvider;
use Manychois\PhpStrong\EventDispatcher\StoppableEventTrait;
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

- `EventDispatcher` table: `__construct(ListenerProviderInterface $provider)`; `dispatch(object $event): object` returns the same instance, checks `isPropagationStopped()` before each listener, discards listener return values, and never catches listener exceptions.
- `ListenerProvider` table: `__construct(?ContainerInterface $container = null)`; `on(string $eventType, callable|array $listener, int $priority = 0): static`; `getListenersForEvent(object $event): iterable`.
- A "Listener forms" section covering the three accepted forms (`callable`, `[$instance, $method]`, `[$serviceId, $method]`), stating that the service form needs a container, resolves on every dispatch, and is not checked by static analysis.
- An "Ordering" section: higher priority first, ties in registration order, parents and interfaces compete in the same sort, type specificity does not matter.
- An "Errors" section: `InvalidArgumentException` for an unknown event type, a malformed array listener, or an array listener that is neither callable nor deferrable; `RuntimeException` at dispatch when a service is not an object or lacks the method; the container's own `NotFoundExceptionInterface` for an unknown id.

- [ ] **Step 3: Add the module to the README**

Add the new page to the README list in the same format as the existing entries, e.g. a row/bullet naming PSR-14 and linking `docs/event-dispatcher.md`.

- [ ] **Step 4: Verify coverage**

Run: `composer test`

Then run: `php -r '$x = simplexml_load_file("coverage/clover.xml"); foreach ($x->xpath("//file") as $f) { $m = $f->metrics; if (str_contains((string) $f["name"], "EventDispatcher")) { printf("%s %d/%d\n", basename((string) $f["name"]), (int) $m["coveredstatements"], (int) $m["statements"]); } }'`

Expected: every `src/EventDispatcher/*.php` file reports covered == total. If a line is uncovered, add the missing test to the relevant test file rather than excluding the code.

- [ ] **Step 5: Quality gates**

Run: `composer phpcbf`, then `composer phpcs`, then `composer phpstan`, then `composer test`.

Expected: clean.

- [ ] **Step 6: Commit**

```bash
git add docs/event-dispatcher.md README.md
git commit -m "docs: document the PSR-14 event dispatcher module"
```
