<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\Collections;

use Manychois\PhpStrong\Collections\EqualityComparerInterface as IEqualityComparer;
use Manychois\PhpStrong\Collections\ReadonlyListInterface as IReadonlyList;
use OutOfBoundsException;

/**
 * Defines a mutable list of items.
 *
 * @template T
 *
 * @extends IReadonlyList<T>
 */
interface ListInterface extends IReadonlyList
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
     * @return IReadonlyList<T> A readonly version of the list.
     */
    public function asReadonly(): IReadonlyList;

    /**
     * Clears all items from the list.
     */
    public function clear(): void;

    /**
     * Inserts one or more items at the specified index.
     *
     * @param int $index The index at which to insert the items. Negative indices count from the end.
     * @param T ...$items The items to insert.
     *
     * @throws OutOfBoundsException If the index is out of bounds.
     */
    public function insert(int $index, mixed ...$items): void;

    /**
     * Inserts a range of items at the specified index.
     *
     * @param int $index The index at which to insert the items. Negative indices count from the end.
     * @param iterable<T> ...$ranges The ranges of items to insert.
     *
     * @throws OutOfBoundsException If the index is out of bounds.
     */
    public function insertRange(int $index, iterable ...$ranges): void;

    /**
     * Removes the first occurrence of the specified item from the list.
     *
     * @param T $item The item to remove from the list.
     * @param IEqualityComparer|null $eq An optional equality comparer to determine item equality.
     *
     * @return bool True if the item was found and removed; otherwise, false.
     *
     * @throws OutOfBoundsException If the item is not found in the list.
     */
    public function remove(mixed $item, ?IEqualityComparer $eq = null): bool;

    /**
     * Removes all items that satisfy the specified predicate from the list.
     *
     * @param callable(T,int): bool $predicate The predicate function to determine which items to remove.
     *
     * @return ListInterface<T> A list containing the removed items.
     */
    public function removeAll(callable $predicate): ListInterface;

    /**
     * Removes the item at the specified indices.
     *
     * @param int ...$indices The indices of the items to remove. Negative indices count from the end.
     *
     * @throws OutOfBoundsException If any index is out of bounds.
     */
    public function removeAt(int ...$indices): void;

    /**
     * Sets the item at the specified index.
     *
     * @param int $index The index of the item to set.
     * Negative indices are supported, which count from the end of the list.
     * @param T $item The item to set at the specified index.
     *
     * @throws OutOfBoundsException If the index is out of bounds.
     */
    public function set(int $index, mixed $item): void;
}
