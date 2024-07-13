<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\Collections\Internal;

use Iterator;
use OutOfBoundsException;

/**
 * An iterator that only move forwards but no rewind.
 *
 * @template TKey
 * @template TItem
 *
 * @template-implements Iterator<TKey,TItem>
 */
class NextOnlyIterator implements Iterator
{
    private readonly Iterator $internalIterator;
    private int $counter = 0;
    private int $maxSteps;

    /**
     * Initializes a new instance of the NextOnlyIterator class.
     *
     * @param Iterator<TKey,TItem> $internalIterator The internal iterator.
     * @param int                  $maxSteps         The maximum steps to iterate.
     *
     * @phpstan-param positive-int $maxSteps
     */
    public function __construct(Iterator $internalIterator, int $maxSteps)
    {
        if ($maxSteps <= 0) {
            throw new OutOfBoundsException('The maxSteps must be a positive integer.');
        }

        $this->internalIterator = $internalIterator;
        $this->maxSteps = $maxSteps;
    }

    #region implements Iterator

    public function current(): mixed
    {
        return $this->internalIterator->current();
    }

    public function next(): void
    {
        $this->internalIterator->next();
        $this->counter++;
    }

    public function key(): mixed
    {
        return $this->internalIterator->key();
    }

    public function valid(): bool
    {
        return $this->counter < $this->maxSteps && $this->internalIterator->valid();
    }

    public function rewind(): void
    {
        // do nothing
    }

    #endregion implements Iterator

    /**
     * Gets the number of `next()` called.
     *
     * @return int The number of `next()` called.
     */
    public function getCounter(): int
    {
        return $this->counter;
    }
}
