<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\Events;

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
