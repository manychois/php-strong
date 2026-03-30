<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\Collections;

use OutOfBoundsException;

/**
 * Defines a mutable list of items.
 *
 * @template T
 *
 * @extends ReadonlyListInterface<T>
 */
interface ListInterface extends ReadonlyListInterface
{
    /**
     * Adds one or more items to the end of the list.
     *
     * @param T ...$items The items to add to the list.
     */
    public function add(mixed ...$items): void;

    /**
     * Adds a range of items to the end of the list.
     *
     * @param iterable<T> ...$ranges The ranges of items to add to the list.
     */
    public function addRange(iterable ...$ranges): void;

    /**
     * Gets a readonly version of the list.
     *
     * @return ReadonlyListInterface<T> A readonly version of the list.
     */
    public function asReadonly(): ReadonlyListInterface;

    /**
     * Clears all items from the list.
     */
    public function clear(): void;

    /**
     * Sets the item at the specified index.
     *
     * @param int $index The index of the item to set.
     * Negative indices are supported, which count from the end of the list.
     * @param T $item The item to set at the specified index.
     * @throws OutOfBoundsException If the index is out of bounds.
     */
    public function set(int $index, mixed $item): void;
}
