<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\Collections;

/**
 * Represents a mutable sequence of items.
 *
 * @template T
 *
 * @extends ReadonlySequence<T>
 */
class Sequence extends ReadonlySequence
{
    /**
     * Appends the given items to the end of the sequence.
     *
     * @param T ...$items The items to append.
     */
    public function append(mixed ...$items): void
    {
        /** @var array<int,T> $result */
        $result = \array_merge($this->items, $items);
        $this->items = $result;
    }

    /**
     * Removes all items from the sequence.
     */
    public function clear(): void
    {
        $this->items = [];
    }

    /**
     * Removes a range of items from the sequence and inserts new items at the same position.
     *
     * @param int         $index       The index at which to start removing items.
     * @param int|null    $length      The number of items to remove, or null to remove all items from $index to the end
     *                                 of the sequence.
     * @param iterable<T> $replacement The items to insert at the same position.
     *
     * @return ReadonlySequence<T> The removed items.
     */
    public function splice(int $index, ?int $length = null, iterable $replacement = []): ReadonlySequence
    {
        $toReplace = \is_array($replacement) ? $replacement : \iterator_to_array($replacement, false);
        $removed = \array_splice($this->items, $index, $length, $toReplace);

        return new ReadonlySequence($removed);
    }
}
