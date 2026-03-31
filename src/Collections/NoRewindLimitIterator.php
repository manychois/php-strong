<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\Collections;

use InvalidArgumentException;
use Iterator;
use Override;

/**
 * Wraps an iterator which ignores rewind operations and restricts iterations to a bounded number.
 *
 * @template TKey
 * @template TValue
 *
 * @implements Iterator<TKey, TValue>
 */
class NoRewindLimitIterator implements Iterator
{
    /**
     * @var Iterator<TKey, TValue>
     */
    private readonly Iterator $source;

    private int $remaining;

    /**
     * @param Iterator<TKey, TValue> $source Iterator to wrap.
     * @param int                    $size   Maximum number of iterations.
     *
     * @throws InvalidArgumentException if size is not positive.
     */
    public function __construct(Iterator $source, int $size)
    {
        if ($size <= 0) {
            throw new InvalidArgumentException('Size must be greater than 0');
        }
        $this->source = $source;
        $this->remaining = $size;
    }

    /**
     * Advances the underlying iterator until this limit is exhausted.
     */
    public function exhaust(): void
    {
        while ($this->remaining > 0) {
            $this->remaining--;
            $this->source->next();
        }
    }

    #region implements Iterator

    /**
     * @inheritDoc
     */
    #[Override]
    public function current(): mixed
    {
        return $this->source->current();
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function next(): void
    {
        if ($this->remaining > 0) {
            $this->remaining--;
            $this->source->next();
        }
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function key(): mixed
    {
        return $this->source->key();
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function valid(): bool
    {
        return $this->remaining > 0 && $this->source->valid();
    }

    /**
     * Does not rewind the underlying iterator (intentional no-op).
     */
    #[Override]
    public function rewind(): void
    {
        // do nothing
    }

    #endregion implements Iterator
}
