<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\Events;

use InvalidArgumentException;
use Override;
use Psr\Container\ContainerInterface as IContainer;
use Psr\EventDispatcher\ListenerProviderInterface as IListenerProvider;
use RuntimeException;

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

    #endregion implements IListenerProvider

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

            $callable = [$service, $method];

            return $callable($event);
        };
    }
}
