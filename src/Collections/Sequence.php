<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\Collections;

use Manychois\PhpStrong\Collections\Internal\AbstractSequence;
use OutOfRangeException;

/**
 * Represents a sequence of items.
 *
 * @template T
 *
 * @template-extends AbstractSequence<T>
 */
class Sequence extends AbstractSequence
{
        /**
     * Initializes a new instance of the Sequence class.
     *
     * @template TObject of object
     *
     * @param class-string<TObject> $class   The class of the items in the sequence.
     * @param iterable<TObject>     $initial The initial items of the sequence.
     *
     * @return self<TObject> The new instance.
     */
    public static function ofObject(string $class, iterable $initial = []): self
    {
        // @phpstan-ignore return.type
        return new self($initial);
    }

    /**
     * Initializes a new instance of the Sequence class.
     *
     * @param iterable<string> $initial The initial items of the sequence.
     *
     * @return self<string> The new instance.
     */
    public static function ofString(iterable $initial = []): self
    {
        // @phpstan-ignore return.type
        return new self($initial);
    }

    /**
     * Appends one or more items to the end of the sequence.
     *
     * @param T ...$items The items to append to the sequence.
     */
    public function append(mixed ...$items): void
    {
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
    public function insertAt(int $index, mixed ...$items): void
    {
        if ($index < 0) {
            $index += \count($this->items);
        }
        if ($index < 0 || $index > \count($this->items)) {
            throw new OutOfRangeException('The index is out of range.');
        }

        \array_splice($this->items, $index, 0, $items);
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
     * @return ReadonlySequence<T> The items that were removed.
     */
    public function removeAt(int $index, ?int $count = 1): ReadonlySequence
    {
        if ($index < 0) {
            $index += \count($this->items);
        }
        if ($index < 0 || $index > \count($this->items)) {
            throw new OutOfRangeException('The index is out of range.');
        }
        if ($count !== null && $count < 0) {
            throw new \InvalidArgumentException('The count cannot be negative.');
        }

        $removed = \array_splice($this->items, $index, $count);
        // @phpstan-ignore return.type
        return new ReadonlySequence($removed);
    }
}
