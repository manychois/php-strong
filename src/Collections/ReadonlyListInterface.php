<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\Collections;

use ArrayAccess;
use BadMethodCallException;
use InvalidArgumentException;
use Manychois\PhpStrong\Collections\SequenceInterface as ISequence;
use OutOfBoundsException;

/**
 * Defines a list of items.
 *
 * @template T
 *
 * @extends ArrayAccess<int, T>
 * @extends ISequence<T>
 */
interface ReadonlyListInterface extends ArrayAccess, ISequence
{
    /**
     * Returns the item at the specified index.
     *
     * @param int $index The index of the item to return.
     * Negative indices are supported, which count from the end of the list.
     * @return T The item at the specified index.
     * @throws OutOfBoundsException If the index is out of bounds.
     */
    public function at(int $index): mixed;

    /**
     * Finds the index of the first item that satisfies the specified predicate.
     *
     * @param callable(T,int): bool $predicate The predicate to check.
     * @param int $start The index to start searching from. Negative indices count from the end.
     * @return int The index of the first item that satisfies the predicate, or -1 if no such item is found.
     *
     * @throws OutOfBoundsException If the start index is out of bounds.
     */
    public function findIndex(callable $predicate, int $start = 0): int;

    #region extends ArrayAccess

    /**
     * @inheritDoc
     *
     * @throws InvalidArgumentException If the offset is not an integer.
     */
    public function offsetExists(mixed $offset): bool;

    /**
     * @inheritDoc
     *
     * @throws InvalidArgumentException If the offset is not an integer.
     * @throws OutOfBoundsException If the offset is out of bounds.
     */
    public function offsetGet(mixed $offset): mixed;

    /**
     * @inheritDoc
     *
     * @throws BadMethodCallException if the list is readonly.
     * @throws InvalidArgumentException If the offset is not an integer.
     * @throws OutOfBoundsException If the offset is out of bounds.
     */
    public function offsetSet(mixed $offset, mixed $value): void;

    /**
     * Unsets the item at the specified offset.
     * Unsetting an out of bounds offset is an no-op.
     *
     * @throws BadMethodCallException if the list is readonly.
     * @throws InvalidArgumentException If the offset is not an integer.
     */
    public function offsetUnset(mixed $offset): void;

    #endregion extends ArrayAccess
}
