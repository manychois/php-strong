<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\Collections;

use Countable;
use IteratorAggregate;
use LengthException;
use Manychois\PhpStrong\AbstractObject;
use OutOfRangeException;
use Traversable;

/**
 * Represents a list of objects that can be individually accessed by zero-based index.
 *
 * @template T The type of the items in the list.
 *
 * @phpstan-implements IteratorAggregate<int,T>
 *
 * @phpstan-type Predicate callable(T,int=):bool
 */
class ArrayList extends AbstractObject implements Countable, IteratorAggregate
{
    /**
     * Initializes a new instance of the `ArrayList` class that contains items copied from the specified iterable.
     *
     * @param string            $className The class name of the items in the list.
     * @param iterable<TObject> $source    The iterable whose items are copied to the new list.
     *
     * @return self<TObject> The new list.
     *
     * @template TObject The type of the items in the list.
     *
     * @phpstan-param class-string<TObject> $className
     */
    public static function ofType(string $className, iterable $source = []): self
    {
        /** @var self<TObject> $result */
        $result = new self($source);

        return $result;
    }

    /**
     * @var array<int,T> The internal list.
     *
     * @phpstan-var list<T>
     */
    private array $items = [];

    /**
     * Initializes a new instance of the `ArrayList` class that contains items copied from the specified iterable.
     *
     * @param iterable<T> $source The iterable whose items are copied to the new list.
     */
    public function __construct(iterable $source = [])
    {
        if (\is_array($source) && \array_is_list($source)) {
            $this->items = $source;
        } else {
            foreach ($source as $item) {
                $this->items[] = $item;
            }
        }
    }

    #region implements Countable

    public function count(): int
    {
        return \count($this->items);
    }

    #endregion implements Countable

    #region implements IteratorAggregate

    /**
     * @inheritDoc
     *
     * @return Traversable<int,T>
     *
     * @phpstan-return Traversable<non-negative-int, T>
     */
    public function getIterator(): Traversable
    {
        foreach ($this->items as $i => $item) {
            \assert($i >= 0);
            yield $i => $item;
        }
    }

    #endregion implements IteratorAggregate

    /**
     * Adds one or more items to the end of the list.
     *
     * @param T ...$items The items to add to the list.
     */
    public function add(mixed ...$items): void
    {
        foreach ($items as $item) {
            $this->items[] = $item;
        }
    }

    /**
     * Removes all items from the list.
     */
    public function clear(): void
    {
        $this->items = [];
    }

    /**
     * Determines whether an item is in the list.
     *
     * @param mixed $item The object to locate in the list.
     *
     * @return bool `true` if `item` is found in the list; otherwise, `false`.
     */
    public function contains(mixed $item): bool
    {
        $found = \in_array($item, $this->items, true);

        if (!$found) {
            foreach ($this->items as $i) {
                if (DefaultComparer::areEqual($i, $item)) {
                    return true;
                }
            }
        }

        return $found;
    }

    /**
     * Performs the specified action on each item of the list.
     *
     * @param callable $action The action to perform on each item of the list.
     *                         The first argument is the item.
     *                         The second argument is the index of the item.
     *                         If the action returns `false`, the iteration will stop.
     *
     * @phpstan-param callable(T,int=):mixed $action
     */
    public function each(callable $action): void
    {
        foreach ($this->items as $i => $item) {
            if ($action($item, $i) === false) {
                break;
            }
        }
    }

    /**
     * Determines whether all items of the list satisfy the specified predicate.
     *
     * @param callable $predicate The predicate to test each item of the list against.
     *                            The first argument is the item.
     *                            The second argument is the index of the item.
     *
     * @return bool `true` if all items of the list satisfy the predicate; otherwise, `false`.
     *
     * @phpstan-param Predicate $predicate
     */
    public function every(callable $predicate): bool
    {
        foreach ($this->items as $i => $item) {
            if (!$predicate($item, $i)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Returns a new list that contains the items that satisfy the specified predicate.
     *
     * @param callable $predicate The predicate to test each item of the list against.
     *                            The first argument is the item.
     *                            The second argument is the index of the item.
     *
     * @return self<T> The list that contains the items that satisfy the specified predicate.
     *
     * @phpstan-param Predicate $predicate
     */
    public function filter(callable $predicate): self
    {
        $result = new self();
        foreach ($this->items as $i => $item) {
            if ($predicate($item, $i)) {
                $result->add($item);
            }
        }

        return $result;
    }

    /**
     * Returns the first item that satisfies the specified predicate.
     *
     * @param callable $predicate The predicate to test each item of the list against.
     *                            The first argument is the item.
     *                            The second argument is the index of the item.
     * @param int      $from      From which index to start searching.
     *                            If negative, the index is relative to the end of the list.
     *
     * @return T|null The first item that satisfies the specified predicate, or `null` if no item satisfies the
     * predicate or the from index is out of range.
     *
     * @phpstan-param Predicate $predicate
     */
    public function find(callable $predicate, int $from = 0): mixed
    {
        $count = \count($this->items);
        if ($from < -$count || $from >= $count) {
            return null;
        }

        if ($from < 0) {
            $from += $count;
        }
        for ($i = $from; $i < $count; $i++) {
            if ($predicate($this->items[$i], $i)) {
                return $this->items[$i];
            }
        }

        return null;
    }

    /**
     * Returns the index of the first item that satisfies the specified predicate.
     *
     * @param callable $predicate The predicate to test each item of the list against.
     *                            The first argument is the item.
     *                            The second argument is the index of the item.
     * @param int      $from      From which index to start searching.
     *                            If negative, the index is relative to the end of the list.
     *
     * @return int The index of the first item that satisfies the specified predicate, or -1 if no item satisfies the
     * predicate or the from index is out of range.
     *
     * @phpstan-param Predicate $predicate
     */
    public function findIndex(callable $predicate, int $from = 0): int
    {
        $count = \count($this->items);
        if ($from < -$count || $from >= $count) {
            return -1;
        }

        if ($from < 0) {
            $from += $count;
        }
        for ($i = $from; $i < $count; $i++) {
            if ($predicate($this->items[$i], $i)) {
                return $i;
            }
        }

        return -1;
    }

    /**
     * Returns the last item that satisfies the specified predicate.
     *
     * @param callable $predicate The predicate to test each item of the list against.
     *                            The first argument is the item.
     *                            The second argument is the index of the item.
     * @param int      $from      From which index to start searching backwards.
     *                            If negative, the index is relative to the end of the list.
     *
     * @return T|null The last item that satisfies the specified predicate, or `null` if no item satisfies the
     * predicate or the from index is out of range.
     *
     * @phpstan-param Predicate $predicate
     */
    public function findLast(callable $predicate, int $from = -1): mixed
    {
        $count = \count($this->items);
        if ($from < -$count || $from >= $count) {
            return null;
        }

        if ($from < 0) {
            $from += $count;
        }
        for ($i = $from; $i >= 0; $i--) {
            if ($predicate($this->items[$i], $i)) {
                return $this->items[$i];
            }
        }

        return null;
    }

    /**
     * Returns the index of the last item that satisfies the specified predicate.
     *
     * @param callable $predicate The predicate to test each item of the list against.
     *                            The first argument is the item.
     *                            The second argument is the index of the item.
     * @param int      $from      From which index to start searching backwards.
     *                            If negative, the index is relative to the end of the list.
     *
     * @return int The index of the last item that satisfies the specified predicate, or -1 if no item satisfies the
     * predicate or the from index is out of range.
     *
     * @phpstan-param Predicate $predicate
     */
    public function findLastIndex(callable $predicate, int $from = -1): int
    {
        $count = \count($this->items);
        if ($from < -$count || $from >= $count) {
            return -1;
        }

        if ($from < 0) {
            $from += $count;
        }
        for ($i = $from; $i >= 0; $i--) {
            if ($predicate($this->items[$i], $i)) {
                return $i;
            }
        }

        return -1;
    }

    /**
     * Returns the index of the first occurrence of an item in the list.
     *
     * @param T   $item The item to locate in the list.
     * @param int $from From which index to start searching.
     *                  If negative, the index is relative to the end of the list.
     *
     * @return int The index of the first occurrence of `item` in the list, if found; otherwise, -1.
     */
    public function indexOf(mixed $item, int $from = 0): int
    {
        $count = \count($this->items);
        if ($from < -$count || $from >= $count) {
            return -1;
        }

        if ($from < 0) {
            $from += $count;
        }

        $toSearch = $from > 0 ? \array_slice($this->items, $from, null, true) : $this->items;
        $result = \array_search($toSearch, $this->items, true);
        if ($result === false) {
            foreach ($toSearch as $i => $v) {
                if (DefaultComparer::areEqual($v, $item)) {
                    return $i;
                }
            }

            return -1;
        }

        return $result;
    }

    /**
     * Inserts one or more items into the list at the specified index.
     *
     * @param int $index    The zero-based index at which the new items should be inserted.
     *                      If negative, the index is relative to the end of the list.
     * @param T   ...$items The items to insert into the list.
     *
     * @throws OutOfRangeException If the index is out of range.
     */
    public function insert(int $index, mixed ...$items): void
    {
        $count = \count($this->items);
        if ($index < -$count || $index > $count) {
            throw new OutOfRangeException('Index is out of range.');
        }

        if ($index < 0) {
            $index += $count;
        }
        \array_splice($this->items, $index, 0, $items);
    }

    /**
     * Gets the item at the specified index.
     *
     * @param int $index The zero-based index of the item to get.
     *                   If negative, the index is relative to the end of the list.
     *
     * @return T The item at the specified index.
     *
     * @throws OutOfRangeException If the index is out of range.
     */
    public function item(int $index): mixed
    {
        $count = \count($this->items);
        if ($index < -$count || $index >= $count) {
            throw new OutOfRangeException('Index is out of range.');
        }

        if ($index < 0) {
            $index += $count;
        }

        return $this->items[$index];
    }

    /**
     * Returns the index of the last occurrence of an item in the list.
     *
     * @param T   $item The item to locate in the list.
     * @param int $from From which index to start searching backwards.
     *                  If negative, the index is relative to the end of the list.
     *
     * @return int The index of the last occurrence of `item` in the list, if found; otherwise, -1.
     */
    public function lastIndexOf(mixed $item, int $from = -1): int
    {
        $count = \count($this->items);
        if ($from < -$count || $from >= $count) {
            return -1;
        }

        if ($from < 0) {
            $from += $count;
        }

        $toSearch = \array_reverse($from > 0 ? \array_slice($this->items, 0, $from + 1, true) : $this->items, true);
        $result = \array_search($toSearch, $this->items, true);
        if ($result === false) {
            foreach ($toSearch as $i => $v) {
                if (DefaultComparer::areEqual($v, $item)) {
                    return $i;
                }
            }

            return -1;
        }

        return $result;
    }

    /**
     * Projects each item of the list into a new form.
     *
     * @param callable $transformer A transform function to apply to each item.
     *
     * @template TResult The type of the result of the transform function.
     *
     * @phpstan-param callable(T,int):TResult $transformer
     *
     * @return self<TResult> A list whose items are the result of invoking the transform function on each item of
     * the list.
     */
    public function map(callable $transformer): self
    {
        $result = new self();
        foreach ($this->items as $i => $item) {
            $result->add($transformer($item, $i));
        }

        return $result;
    }

    /**
     * Removes the last item from the list and returns it.
     *
     * @return T The last item in the list.
     *
     * @throws OutOfRangeException If the list is empty.
     */
    public function pop(): mixed
    {
        $item = \array_pop($this->items);
        if ($item === null) {
            throw new OutOfRangeException('The list is empty.');
        }

        return $item;
    }

    /**
     * Executes a reducer function on each item of the list, resulting in a single output value.
     *
     * @param callable $reducer The reducer function.
     *                          The first argument is the previous accumulated value.
     *                          The second argument is the current item.
     *                          The third argument is the index of the current item.
     * @param TValue   $initial The initial accumulated value.
     *
     * @return TValue The accumulated value that results from applying the reducer function to each item of the list.
     *
     * @template TValue The type of the accumulated value.
     *
     * @phpstan-param callable(TValue,T,int):TValue $reducer
     */
    public function reduce(callable $reducer, mixed $initial = null): mixed
    {
        $result = $initial;
        foreach ($this->items as $i => $item) {
            $result = $reducer($result, $item, $i);
        }

        return $result;
    }

    /**
     * Removes the first occurrence of a specific item from the list.
     *
     * @param T $item The item to remove from the list.
     *
     * @return bool `true` if `item` was successfully removed from the list; otherwise, `false`.
     */
    public function remove(mixed $item): bool
    {
        $index = $this->indexOf($item);
        if ($index === -1) {
            return false;
        }

        $this->removeAt($index);

        return true;
    }

    /**
     * Removes the item at the specified index.
     *
     * @param int $index The zero-based index of the item to remove.
     *                   If negative, the index is relative to the end of the list.
     *
     * @return T The item that was removed from the list.
     *
     * @throws OutOfRangeException If the index is out of range.
     */
    public function removeAt(int $index): mixed
    {
        $count = \count($this->items);
        if ($index < -$count || $index >= $count) {
            throw new OutOfRangeException('Index is out of range.');
        }

        if ($index < 0) {
            $index += $count;
        }
        $removed = \array_splice($this->items, $index, 1);

        return $removed[0];
    }

    /**
     * Removes a range of items from the list.
     *
     * @param int $index  The zero-based starting index of the range of items to remove.
     *                    If negative, the index is relative to the end of the list.
     * @param int $length The number of items to remove.
     *                    If null, all items from `index` to the end of the list are removed.
     *
     * @return self<T> The list with the specified range of items removed.
     *
     * @throws LengthException If the length is negative.
     * @throws OutOfRangeException If the index is out of range.
     */
    public function removeRange(int $index, int $length = null): self
    {
        if ($length !== null && $length < 0) {
            throw new LengthException('Length cannot be negative.');
        }
        $count = \count($this->items);
        if ($index < -$count || $index >= $count) {
            throw new OutOfRangeException('Index is out of range.');
        }

        if ($index < 0) {
            $index += $count;
        }

        $result = new self();
        $result->items = \array_splice($this->items, $index, $length);

        return $result;
    }

    /**
     * Returns a new list of items in reverse order.
     *
     * @return self<T> A list whose items are in reverse order.
     */
    public function reverse(): self
    {
        $result = new self();
        $result->items = \array_reverse($this->items);

        return $result;
    }

    /**
     * Removes the first item from the list and returns it.
     *
     * @return T The first item in the list.
     *
     * @throws OutOfRangeException If the list is empty.
     */
    public function shift(): mixed
    {
        $item = \array_shift($this->items);
        if ($item === null) {
            throw new OutOfRangeException('The list is empty.');
        }

        return $item;
    }

    /**
     * Returns a new list that contains the specified range of items.
     *
     * @param int $index  The zero-based starting index of the range of items to include in the result.
     *                    If negative, the index is relative to the end of the list.
     * @param int $length The number of items to include in the result.
     *                    If null, all items from `index` to the end of the list are included.
     *
     * @return self<T> A list that contains the specified range of items.
     *
     * @throws LengthException If the length is negative.
     * @throws OutOfRangeException If the index is out of range.
     */
    public function slice(int $index, ?int $length = null): self
    {
        if ($length !== null && $length < 0) {
            throw new LengthException('Length cannot be negative.');
        }

        $count = \count($this->items);
        if ($index < -$count || $index >= $count) {
            throw new OutOfRangeException('Index is out of range.');
        }

        if ($index < 0) {
            $index += $count;
        }
        $result = new self();
        $result->items = \array_slice($this->items, $index, $length);

        return $result;
    }

    /**
     * Determines whether any item of the list satisfies the specified predicate.
     *
     * @param callable $predicate The predicate to test each item of the list against.
     *                            The first argument is the item.
     *                            The second argument is the index of the item.
     *
     * @return bool `true` if any item of the list passes the test in the specified predicate; otherwise, `false`.
     *
     * @phpstan-param Predicate $predicate
     */
    public function some(callable $predicate): bool
    {
        foreach ($this->items as $i => $item) {
            if ($predicate($item, $i)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Sorts the items of the list in place.
     *
     * @param ComparerInterface<T>|null $comparer The comparer to use when comparing elements, or `null` to use the
     *                                            default comparer.
     */
    public function sort(ComparerInterface $comparer = null): void
    {
        if ($comparer === null) {
            $comparer = new DefaultComparer();
        }

        \usort($this->items, static fn ($x, $y) => $comparer->compare($x, $y));
    }

    /**
     * Returns the native array representation of the list.
     *
     * @return array<int, T> The native array representation of the list.
     *
     * @phpstan-return list<T>
     */
    public function toArray(): array
    {
        return $this->items;
    }

    #region extends AbstractObject

    public function equals(mixed $other): bool
    {
        if ($this === $other) {
            return true;
        }

        if ($other instanceof self) {
            $count = \count($this->items);
            if ($count !== \count($other->items)) {
                return false;
            }

            for ($i = 0; $i < $count; $i++) {
                if (!DefaultComparer::areEqual($this->items[$i], $other->items[$i])) {
                    return false;
                }
            }

            return true;
        }

        return false;
    }

    #endregion extends AbstractObject
}
