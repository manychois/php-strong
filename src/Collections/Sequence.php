<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\Collections;

use Manychois\PhpStrong\Collections\Internal\AbstractArraySequence;
use Manychois\PhpStrong\Collections\Internal\SequenceFactoryTrait;
use Manychois\PhpStrong\ComparerInterface;
use Manychois\PhpStrong\Defaults\DefaultComparer;
use Manychois\PhpStrong\EqualityComparerInterface;
use Traversable;

/**
 * Represents a sequence of items.
 *
 * @template T
 *
 * @template-extends AbstractArraySequence<T>
 */
class Sequence extends AbstractArraySequence
{
    use SequenceFactoryTrait;

    /**
     * Appends one or more items to the end of the sequence.
     *
     * @param T ...$items The items to append to the sequence.
     */
    final public function append(mixed ...$items): void
    {
        $this->appendRange($items);
    }

    /**
     * Appends a range of items to the end of the sequence.
     *
     * @param array<T>|Traversable<T> $items The items to append to the sequence.
     */
    public function appendRange(array|Traversable $items): void
    {
        if (!\is_array($items)) {
            if ($items instanceof AbstractArraySequence) {
                $items = $items->asArray();
            } else {
                $items = \iterator_to_array($items, false);
            }
        }
        \array_splice($this->items, \count($this->items), 0, $items);
    }

    /**
     * Removes all items from the sequence.
     */
    public function clear(): void
    {
        $this->items = [];
    }

    /**
     * Inserts one or more items at the specified index into the sequence.
     *
     * @param int $index    The zero-based index at which the new items should be inserted.
     *                      If the index is negative, it is counted from the end of the sequence.
     *                      If the index is out of range, an `OutOfRangeException` is thrown.
     * @param T   ...$items The items to insert.
     */
    final public function insertAt(int $index, mixed ...$items): void
    {
        $this->insertRange($index, $items);
    }

    /**
     * Inserts a range of items at the specified index into the sequence.
     *
     * @param int                     $index The zero-based index at which the new items should be inserted.
     *                                       If the index is negative, it is counted from the end of the sequence.
     *                                       If the index is out of range, an `OutOfRangeException` is thrown.
     * @param array<T>|Traversable<T> $items The items to insert.
     */
    public function insertRange(int $index, array|Traversable $items): void
    {
        $count = \count($this->items);
        if ($index < 0) {
            $index += $count;
        }
        if ($index < 0 || $index > $count) {
            throw new \OutOfRangeException('The index is out of range.');
        }

        if (!\is_array($items)) {
            if ($items instanceof AbstractArraySequence) {
                $items = $items->asArray();
            } else {
                $items = \iterator_to_array($items, false);
            }
        }
        \array_splice($this->items, $index, 0, $items);
    }

    /**
     * Removes the first occurrence of an item from the sequence.
     *
     * @param T                              $item The item to remove.
     * @param EqualityComparerInterface|null $eq   The equality comparer to use.
     *                                             If `null`, the default equality comparer is used.
     *
     * @return bool `true` if the item was removed; otherwise, `false`.
     */
    public function remove(mixed $item, ?EqualityComparerInterface $eq = null): bool
    {
        $index = $this->indexOf($item, 0, $eq);
        if ($index !== -1) {
            \array_splice($this->items, $index, 1);

            return true;
        }

        return false;
    }

    /**
     * Removes one or more items at the specified index from the sequence.
     *
     * @param int      $index The zero-based index of the item to remove.
     *                        If the index is negative, it is counted
     *                        from the end of the sequence. If the index
     *                        is out of range, an `OutOfRangeException`
     *                        is thrown.
     * @param int|null $count The number of items to remove.
     *                        If `null`, items are removed until the end of the sequence.
     *
     * @return self<T> The items that were removed.
     */
    public function removeAt(int $index, ?int $count = 1): self
    {
        if ($index < 0) {
            $index += \count($this->items);
        }
        if ($index < 0 || $index > \count($this->items)) {
            throw new \OutOfRangeException('The index is out of range.');
        }
        if ($count !== null && $count < 0) {
            throw new \InvalidArgumentException('The count cannot be negative.');
        }

        $removed = \array_splice($this->items, $index, $count);

        return new self($removed);
    }

    /**
     * Removes all items that match the specified predicate from the sequence.
     *
     * @param callable $predicate The predicate that defines the conditions of the items to remove.
     *
     * @return int The number of items that were removed.
     */
    public function removeAll(callable $predicate): int
    {
        $removeIndices = [];
        foreach ($this->items as $index => $item) {
            if (!$predicate($item)) {
                continue;
            }

            $removeIndices[] = $index;
        }

        foreach (\array_reverse($removeIndices) as $index) {
            \array_splice($this->items, $index, 1);
        }

        return \count($removeIndices);
    }

    /**
     * Sorts the items in the sequence in place.
     *
     * @param callable|ComparerInterface|null $comparer The comparer to use. If `null`, the default comparer is used.
     *
     * @phpstan-param callable(T,T):int|ComparerInterface|null $comparer
     */
    public function sort(callable|ComparerInterface|null $comparer = null): void
    {
        $comparer ??= new DefaultComparer();
        if (\is_callable($comparer)) {
            \usort($this->items, $comparer);
        } else {
            \usort($this->items, static fn ($a, $b) => $comparer->compare($a, $b));
        }
    }
}
