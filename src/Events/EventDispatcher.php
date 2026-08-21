<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\Events;

use Override;
use Psr\EventDispatcher\EventDispatcherInterface as IEventDispatcher;
use Psr\EventDispatcher\ListenerProviderInterface as IListenerProvider;
use Psr\EventDispatcher\StoppableEventInterface as IStoppableEvent;
use Throwable;

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
     * @throws Throwable if a listener throws, the exception propagates to the caller and no later listener runs,
     *   including a `RuntimeException` or a PSR-11 `NotFoundExceptionInterface` raised by a container-resolved
     *   listener.
     *
     * @phpstan-param T $event
     *
     * @phpstan-return T
     */
    #[Override]
    public function dispatch(object $event): object
    {
        $stoppable = $event instanceof IStoppableEvent ? $event : null;

        /**
         * @phpstan-var callable $listener
         */
        foreach ($this->provider->getListenersForEvent($event) as $listener) {
            if ($stoppable?->isPropagationStopped() === true) {
                break;
            }

            $listener($event);
        }

        return $event;
    }

    #endregion implements IEventDispatcher
}
