<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\Collections;

use ArrayIterator;
use Iterator;
use Manychois\PhpStrong\Collections\Internal\AbstractCollection;
use Manychois\PhpStrong\EqualityComparerInterface;
use OutOfBoundsException;

/**
 * Represents a readonly sequence of items.
 *
 * @template T
 *
 * @template-extends AbstractCollection<int,T>
 */
class ReadonlySequence extends AbstractCollection
{
    /**
     * @var array<int,T> The items in the sequence.
     */
    protected array $items;

    /**
     * Initializes a readonly sequence from an iterable object.
     *
     * @param iterable<T> $collection The iterable object.
     */
    public function __construct(iterable $collection = [])
    {
        $collection = \is_array($collection) ? $collection : \iterator_to_array($collection);
        $this->items = \array_values($collection);
    }

    /**
     * Finds the index of the last item that satisfies the condition.
     *
     * @param callable $predicate A function to test each item for a condition.
     *
     * @return int The index of the last item that satisfies the condition, or -1 if no item satisfies the condition.
     *
     * @phpstan-param callable(T,int): bool $predicate
     *
     * @phpstan-return int<-1, max>
     */
    public function findLastIndex(callable $predicate): int
    {
        $count = \count($this->items);
        for ($i = $count - 1; $i >= 0; --$i) {
            if ($predicate($this->items[$i], $i)) {
                return $i;
            }
        }

        return -1;
    }

    /**
     * Returns the item at the specified index.
     *
     * @param int $index The zero-based index of the item to get.
     *
     * @return T The item at the specified index.
     */
    public function itemAt(int $index): mixed
    {
        $count = \count($this->items);
        if ($index < 0) {
            $index += $count;
        }
        if ($index < 0 || $index >= $count) {
            throw new OutOfBoundsException('The index is out of range.');
        }

        return $this->items[$index];
    }

    /**
     * Returns the index of the last occurrence of a specific item in the collection.
     *
     * @param T                              $needle   The item to locate in the collection.
     * @param EqualityComparerInterface|null $comparer The comparer to use to determine whether two items are equal.
     *
     * @return int The index of the last occurrence of the item in the collection, or -1 if the item is not found.
     *
     * @phpstan-return int<-1, max>
     */
    public function lastIndexOf(mixed $needle, ?EqualityComparerInterface $comparer = null): int
    {
        $comparer = $this->getEqualityComparer($comparer);
        $count = \count($this->items);
        for ($i = $count - 1; $i >= 0; --$i) {
            if ($comparer->equals($needle, $this->items[$i])) {
                return $i;
            }
        }

        return -1;
    }

    #region extends AbstractCollection

    public function count(): int
    {
        return \count($this->items);
    }

    public function getIterator(): Iterator
    {
        return new ArrayIterator($this->items);
    }

    public function first(): mixed
    {
        if (\count($this->items) === 0) {
            throw new \OutOfBoundsException('The sequence is empty.');
        }

        return $this->items[0];
    }

    public function firstOrDefault(mixed $default = null): mixed
    {
        return \count($this->items) === 0 ? $default : $this->items[0];
    }

    public function last(): mixed
    {
        $count = \count($this->items);
        if ($count === 0) {
            throw new \OutOfBoundsException('The sequence is empty.');
        }

        return $this->items[$count - 1];
    }

    public function lastOrDefault(mixed $default = null): mixed
    {
        $count = \count($this->items);

        return $count === 0 ? $default : $this->items[$count - 1];
    }

    public function reverse(): CollectionInterface
    {
        $generator = function () {
            for ($i = \count($this->items) - 1; $i >= 0; --$i) {
                yield $i => $this->items[$i];
            }
        };

        return new TraversableCollection($generator());
    }

    public function slice(int $skip, int $take): CollectionInterface
    {
        if ($skip < 0) {
            throw new OutOfBoundsException('The skip must be greater than or equal to zero.');
        }
        if ($take < 0) {
            throw new OutOfBoundsException('The take must be greater than or equal to zero.');
        }

        $generator = function () use ($skip, $take) {
            $count = \count($this->items);
            $end = \min($skip + $take, $count);
            for ($i = $skip; $i < $end; ++$i) {
                $keyItem = $this->items[$i];
                yield $keyItem->key => $keyItem->item;
            }
        };

        return new TraversableCollection($generator());
    }

    #endregion extends AbstractCollection
}
