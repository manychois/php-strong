<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\Collections\Internal;

use ArrayIterator;
use Countable;
use Generator;
use IteratorAggregate;
use IteratorIterator;
use Manychois\PhpStrong\Collections\ReadonlySequence;
use Manychois\PhpStrong\ComparerInterface;
use Manychois\PhpStrong\Defaults\DefaultComparer;
use Manychois\PhpStrong\Defaults\DefaultEqualityComparer;
use Manychois\PhpStrong\EqualInterface;
use Manychois\PhpStrong\EqualityComparerInterface;
use Manychois\PhpStrong\ValueObject;
use OutOfRangeException;
use Traversable;

/**
 * Base class for sequences based on arrays.
 *
 * @template T
 *
 * @template-implements IteratorAggregate<non-negative-int,T>
 */
abstract class AbstractArraySequence implements Countable, EqualInterface, IteratorAggregate
{
    /**
     * @var array<non-negative-int,T> The items of the sequence.
     */
    protected array $items;

    /**
     * Initializes a new instance of the AbstractArraySequence class.
     *
     * @param array<T>|Traversable<T> $initial The initial items of the sequence.
     */
    public function __construct(array|Traversable $initial)
    {
        if ($initial instanceof self) {
            $this->items = $initial->items;
        } elseif (\is_array($initial)) {
            $this->items = \array_values($initial);
        } else {
            $this->items = \iterator_to_array($initial, false);
        }
    }

    /**
     * Determines whether all items in the sequence satisfy a condition.
     *
     * @param callable $predicate The condition to test for.
     *
     * @return bool true if all items in the sequence satisfy the condition; otherwise, false.
     *
     * @phpstan-param callable(T,int):bool $predicate
     */
    final public function all(callable $predicate): bool
    {
        foreach ($this->getIterator() as $index => $value) {
            if (!$predicate($value, $index)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Determines whether any item in the sequence satisfies a condition.
     *
     * @param callable $predicate The condition to test for.
     *
     * @return bool true if any item in the sequence satisfies the condition; otherwise, false.
     *
     * @phpstan-param callable(T,int):bool $predicate
     */
    final public function any(callable $predicate): bool
    {
        foreach ($this->getIterator() as $index => $value) {
            if ($predicate($value, $index)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Returns the sequence as an array.
     *
     * @return array<non-negative-int,T> The sequence as an array.
     */
    public function asArray(): array
    {
        return $this->items;
    }

    /**
     * Returns the sequence as a readonly sequence.
     *
     * @return ReadonlySequence<T> The sequence as a readonly sequence.
     */
    public function asReadonly(): ReadonlySequence
    {
        if ($this instanceof ReadonlySequence) {
            return $this;
        }

        return new ReadonlySequence($this->items);
    }

    /**
     * Searches for an item in binary search algorithm and returns its index.
     *
     * @param T                      $target The item to search for.
     * @param ComparerInterface|null $c      The comparer to use. If null, the default comparer is used.
     *
     * @return int The index of the item in the sequence, or -1 if the item is not found.
     */
    public function binarySearch(mixed $target, ?ComparerInterface $c = null): int
    {
        $c ??= new DefaultComparer();
        $low = 0;
        $high = \count($this->items) - 1;

        while ($low <= $high) {
            $mid = $low + $high - $low >> 1;
            $compare = $c->compare($this->items[$mid], $target);
            if ($compare === 0) {
                return $mid;
            }

            if ($compare < 0) {
                $low = $mid + 1;
            } else {
                $high = $mid - 1;
            }
        }

        return -1;
    }

    /**
     * Splits the sequence into chunks of the specified size.
     *
     * @param int $size The size of each chunk.
     *
     * @return ReadonlySequence<ReadonlySequence<T>> The chunks of the sequence.
     */
    final public function chunk(int $size): ReadonlySequence
    {
        $generator = function () use ($size) {
            $chunk = [];
            foreach ($this->getIterator() as $value) {
                $chunk[] = $value;
                if (\count($chunk) !== $size) {
                    continue;
                }

                yield new ReadonlySequence($chunk);

                $chunk = [];
            }
            if (\count($chunk) <= 0) {
                return;
            }

            yield new ReadonlySequence($chunk);
        };

        return new ReadonlySequence($generator());
    }

    /**
     * Determines whether the sequence contains a specific item.
     *
     * @param T                              $target The item to locate in the sequence.
     * @param EqualityComparerInterface|null $eq     The equality comparer to use.
     *                                               If null, the default equality
     *                                               comparer is used.
     *
     * @return bool true if the sequence contains the item; otherwise, false.
     */
    final public function contains(mixed $target, ?EqualityComparerInterface $eq = null): bool
    {
        $eq ??= new DefaultEqualityComparer();
        foreach ($this->getIterator() as $value) {
            if ($eq->equals($value, $target)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Performs the specified action on each item in the sequence.
     *
     * @param callable $action The action to perform on each item.
     *
     * @phpstan-param callable(T,int):void $action
     */
    final public function each(callable $action): void
    {
        foreach ($this->getIterator() as $index => $value) {
            $action($value, $index);
        }
    }

    /**
     * Returns a new sequence that contains the items that do not match the specified predicate.
     *
     * @param callable $predicate The predicate to use.
     *
     * @return ReadonlySequence<T> The items that do not match the predicate.
     *
     * @phpstan-param callable(T,int):bool $predicate
     */
    final public function except(callable $predicate): ReadonlySequence
    {
        $generator = function () use ($predicate) {
            foreach ($this->getIterator() as $index => $value) {
                if ($predicate($value, $index)) {
                    continue;
                }

                yield $value;
            }
        };

        return new ReadonlySequence($generator());
    }

    /**
     * Finds the first item that matches the specified predicate.
     *
     * @param callable $predicate The predicate to use.
     * @param int      $from      The index to start searching from.
     *                            If negative, it is counted from the end of the sequence.
     *
     * @return T|null The first item that matches the predicate, or null if no item matches.
     *
     * @phpstan-param callable(T,int):bool $predicate
     */
    public function find(callable $predicate, int $from = 0): mixed
    {
        $count = \count($this->items);
        if ($from < 0) {
            $from += $count;
        }
        if ($from < 0 || $from >= $count) {
            return null;
        }

        for ($i = $from; $i < $count; $i++) {
            if ($predicate($this->items[$i], $i)) {
                return $this->items[$i];
            }
        }

        return null;
    }

    /**
     * Finds all items that match the specified predicate.
     *
     * @param callable $predicate The predicate to use.
     *
     * @return ReadonlySequence<T> The items that match the predicate.
     *
     * @phpstan-param callable(T,int):bool $predicate
     */
    final public function findAll(callable $predicate): ReadonlySequence
    {
        $generator = function () use ($predicate) {
            foreach ($this->getIterator() as $index => $value) {
                if (!$predicate($value, $index)) {
                    continue;
                }

                yield $value;
            }
        };

        return new ReadonlySequence($generator());
    }

    /**
     * Finds the index of the first item that matches the specified predicate.
     *
     * @param callable $predicate The predicate to use.
     * @param int      $from      The index to start searching from.
     *                            If negative, it is counted from the end of the sequence.
     *
     * @return int The index of the first item that matches the predicate, or -1 if no item matches.
     *
     * @phpstan-param callable(T,int):bool $predicate
     */
    public function findIndex(callable $predicate, int $from = 0): int
    {
        $count = \count($this->items);
        if ($from < 0) {
            $from += $count;
        }
        if ($from < 0 || $from >= $count) {
            return -1;
        }

        for ($i = $from; $i < $count; $i++) {
            if ($predicate($this->items[$i], $i)) {
                return $i;
            }
        }

        return -1;
    }

    /**
     * Finds the first item that matches the specified predicate, in reverse order.
     *
     * @param callable $predicate The predicate to use.
     * @param int      $from      The index to start searching from.
     *                            If negative, it is counted from the end of the sequence.
     *
     * @return T|null The index of the first item that matches the predicate, in reverse order;
     * or null if no item matches.
     *
     * @phpstan-param callable(T,int):bool $predicate
     */
    public function findLast(callable $predicate, int $from = -1): mixed
    {
        $count = \count($this->items);
        if ($from < 0) {
            $from += $count;
        }
        if ($from < 0 || $from >= $count) {
            return null;
        }

        for ($i = $from; $i >= 0; $i--) {
            if ($predicate($this->items[$i], $i)) {
                return $this->items[$i];
            }
        }

        return null;
    }

    /**
     * Finds the index of the first item that matches the specified predicate, in reverse order.
     *
     * @param callable $predicate The predicate to use.
     * @param int      $from      The index to start searching from.
     *                            If negative, it is counted from the end of the sequence.
     *
     * @return int The index of the first item that matches the predicate, in reverse order;
     * or -1 if no item matches.
     *
     * @phpstan-param callable(T,int):bool $predicate
     *
     * @phpstan-return int<-1,max>
     */
    public function findLastIndex(callable $predicate, int $from = -1): int
    {
        $count = \count($this->items);
        if ($from < 0) {
            $from += $count;
        }
        if ($from < 0 || $from >= $count) {
            return -1;
        }

        for ($i = $from; $i >= 0; $i--) {
            if ($predicate($this->items[$i], $i)) {
                return $i;
            }
        }

        return -1;
    }

    /**
     * Gets the item at the specified index.
     * If the index is negative, it is counted from the end of the sequence.
     * If the index is out of range, an `OutOfRangeException` is thrown.
     *
     * @param int $index The zero-based index of the item to get.
     *
     * @return T The item at the specified index.
     */
    public function get(int $index): mixed
    {
        $count = \count($this->items);
        if ($index < 0) {
            $index += $count;
        }
        if ($index < 0 || $index >= $count) {
            throw new OutOfRangeException('The index is out of range.');
        }

        return $this->items[$index];
    }

    /**
     * Returns a new value object which represents the item at the specified index.
     * If the index is negative, it is counted from the end of the sequence.
     * If the index is out of range, an `OutOfRangeException` is thrown.
     *
     * @param int $index The zero-based index of the item to get.
     *
     * @return ValueObject The item at the specified index.
     */
    final public function getValueObject(int $index): ValueObject
    {
        return new ValueObject($this->get($index));
    }

    /**
     * Finds the index of the specified item in the sequence.
     *
     * @param T                              $target The item to locate in the sequence.
     * @param int                            $from   The index to start searching from.
     *                                               If negative, it is counted from the end of the sequence.
     * @param EqualityComparerInterface|null $eq     The equality comparer to use.
     *                                               If null, the default equality comparer is used.
     *
     * @return int The index of the item in the sequence, or -1 if the item is not found.
     *
     * @phpstan-return int<-1,max>
     */
    public function indexOf(mixed $target, int $from = 0, ?EqualityComparerInterface $eq = null): int
    {
        if ($from === 0) {
            $found = \array_search($target, $this->items, true);
            if ($found !== false) {
                \assert(\is_int($found) && $found >= 0);

                return $found;
            }
        }

        $count = \count($this->items);
        if ($from < 0) {
            $from += $count;
        }
        if ($from < 0 || $from >= $count) {
            return -1;
        }

        $eq ??= new DefaultEqualityComparer();
        for ($i = $from; $i < $count; $i++) {
            if ($eq->equals($this->items[$i], $target)) {
                return $i;
            }
        }

        return -1;
    }

    /**
     * Finds the index of the specified item in the sequence, in reverse order.
     *
     * @param T                              $target The item to locate in the sequence.
     * @param int                            $from   The index to start searching from.
     *                                               If negative, it is counted from the end of the sequence.
     * @param EqualityComparerInterface|null $eq     The equality comparer to use.
     *                                               If null, the default equality comparer is used.
     *
     * @return int The index of the item in the sequence, or -1 if the item is not found.
     *
     * @phpstan-return int<-1,max>
     */
    public function lastIndexOf(mixed $target, int $from = -1, ?EqualityComparerInterface $eq = null): int
    {
        $count = \count($this->items);
        if ($from < 0) {
            $from += $count;
        }
        if ($from < 0 || $from >= $count) {
            return -1;
        }

        $eq ??= new DefaultEqualityComparer();
        for ($i = $from; $i >= 0; $i--) {
            if ($eq->equals($this->items[$i], $target)) {
                return $i;
            }
        }

        return -1;
    }

    /**
     * Iterates through the sequence and produces the transformed item.
     *
     * @param callable $selector The transformation function.
     *
     * @return ReadonlySequence<TResult> The transformed item.
     *
     * @template TResult
     *
     * @phpstan-param callable(T,int):TResult $selector
     */
    final public function map(callable $selector): ReadonlySequence
    {
        $generator = function () use ($selector) {
            foreach ($this->getIterator() as $index => $value) {
                yield $selector($value, $index);
            }
        };

        return new ReadonlySequence($generator());
    }

    /**
     * Returns an ordered sequence according to a specified comparer.
     *
     * @param callable|ComparerInterface|null $comparer The comparer to use. If null, the default comparer is used.
     *
     * @return ReadonlySequence<T> The items in the sequence, ordered according to the specified comparer.
     *
     * @phpstan-param callable(T,T):int|ComparerInterface|null $comparer
     */
    final public function orderBy(callable|ComparerInterface|null $comparer = null): ReadonlySequence
    {
        $comparer ??= new DefaultComparer();
        $items = $this->asArray();
        if (\is_callable($comparer)) {
            \usort($items, $comparer);
        } else {
            \usort($items, static fn ($a, $b) => $comparer->compare($a, $b));
        }

        return new ReadonlySequence($items);
    }

    /**
     * Accumulates the result by iterating through the sequence.
     *
     * @param callable $reducer The reducer function to accumulate the result.
     * @param TResult  $initial The initial value of the accumulator.
     *
     * @return TResult The accumulated result.
     *
     * @template TResult
     *
     * @phpstan-param callable(TResult,T,int):TResult $reducer
     */
    final public function reduce(callable $reducer, mixed $initial): mixed
    {
        $result = $initial;
        foreach ($this->getIterator() as $index => $value) {
            $result = $reducer($result, $value, $index);
        }

        return $result;
    }

    /**
     * Iterates the sequence in the reverse order.
     *
     * @return ReadonlySequence<T> The items in the sequence, in reverse order.
     */
    public function reverse(): ReadonlySequence
    {
        $generator = function () {
            $count = \count($this->items);
            for ($i = $count - 1; $i >= 0; $i--) {
                yield $this->items[$i];
            }
        };

        return new ReadonlySequence($generator());
    }

    /**
     * Iterates part of the sequence by skipping a specified number of items.
     *
     * @param int $length The number of items to skip.
     *
     * @return ReadonlySequence<T> The items in the sequence after skipping the specified number of items.
     */
    final public function skip(int $length): ReadonlySequence
    {
        $generator = function () use ($length) {
            foreach ($this->getIterator() as $index => $value) {
                if ($index < $length) {
                    continue;
                }

                yield $value;
            }
        };

        return new ReadonlySequence($generator());
    }

    /**
     * Returns a portion of the sequence starting from the beginning.
     *
     * @param int $length The number of items to take.
     *
     * @return ReadonlySequence<T> The items in the sequence up to the specified number of items.
     */
    final public function take(int $length): ReadonlySequence
    {
        $generator = function () use ($length) {
            foreach ($this->getIterator() as $i => $value) {
                if ($i >= $length) {
                    break;
                }

                yield $value;
            }
        };

        return new ReadonlySequence($generator());
    }

    /**
     * Filters the sequence based on a predicate.
     *
     * @param callable $predicate The predicate to use.
     *
     * @return ReadonlySequence<T> The items in the sequence that satisfy the predicate.
     *
     * @phpstan-param callable(T,int):bool $predicate
     */
    final public function where(callable $predicate): ReadonlySequence
    {
        $generator = function () use ($predicate) {
            foreach ($this->getIterator() as $index => $value) {
                if (!$predicate($value, $index)) {
                    continue;
                }

                yield $value;
            }
        };

        return new ReadonlySequence($generator());
    }

    #region implemented Countable

    /**
     * @inheritDoc
     */
    public function count(): int
    {
        return \count($this->items);
    }

    #endregion implemented Countable

    #region implemented EqualInterface

    /**
     * @inheritDoc
     */
    public function equals(mixed $other): bool
    {
        if ($other === null) {
            return false;
        }
        if ($other === $this) {
            return true;
        }
        $count = $this->count();
        $eq = new DefaultEqualityComparer();
        if (\is_array($other) && \count($other) !== $count) {
            return false;
        }

        if ($other instanceof Countable && $other->count() !== $count) {
            return false;
        }

        if (\is_array($other) || $other instanceof Traversable) {
            $otherIterator = \is_array($other) ? new ArrayIterator($other) : new IteratorIterator($other);
            $iterator = $this->getIterator();
            foreach ($iterator as $value) {
                if (!$otherIterator->valid()) {
                    return false;
                }
                if (!$eq->equals($value, $otherIterator->current())) {
                    return false;
                }
                $otherIterator->next();
            }

            return !$otherIterator->valid();
        }

        return false;
    }

    #endregion implemented EqualInterface

    #region implemented IteratorAggregate

    /**
     * Returns an iterator that iterates through the sequence.
     *
     * @return Generator<non-negative-int,T> An iterator that can be used to iterate through the sequence.
     */
    public function getIterator(): Generator
    {
        foreach ($this->items as $index => $value) {
            yield $index => $value;
        }
    }

    #endregion implemented IteratorAggregate
}
