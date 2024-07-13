<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\Collections;

use Countable;
use IteratorAggregate;
use Manychois\PhpStrong\ComparerInterface;
use Manychois\PhpStrong\EqualityComparerInterface;

/**
 * Represents a collection of items.
 *
 * @template TKey
 * @template TItem
 *
 * @template-extends IteratorAggregate<TKey,TItem>
 */
interface CollectionInterface extends Countable, IteratorAggregate
{
    /**
     * Determines whether all items of a collection satisfy a condition.
     *
     * @param callable $predicate A function to test each item for a condition.
     *
     * @return bool `true` if every item passes the test in the specified predicate, or if the collection is empty;
     * otherwise, `false`.
     *
     * @phpstan-param callable(TItem,TKey):bool $predicate
     */
    public function all(callable $predicate): bool;

    /**
     * Splits the collection into chunks of the specified size.
     *
     * @param int $size The maximum size of each chunk.
     *
     * @return CollectionInterface<int,CollectionInterface<TKey,TItem>> A new collection that contains chunks of the
     * current collection.
     *
     * @phpstan-param positive-int $size
     */
    public function chunks(int $size): self;

    /**
     * Returns a new collection that contains items of the current collection and the specified collections.
     *
     * @param iterable<TKey,TItem> ...$collections The collections to combine with the current collection.
     *
     * @return CollectionInterface<TKey,TItem> A new collection that contains items of the current collection and the
     * specified collections.
     *
     * @phpstan-param iterable<covariant TKey,covariant TItem> ...$collections
     */
    public function combine(iterable ...$collections): self;

    /**
     * Determines whether the collection contains a specific item.
     *
     * @param TItem                          $needle   The item to locate in the collection.
     * @param EqualityComparerInterface|null $comparer The comparer to use to determine whether two items are equal.
     *
     * @return bool `true` if the item is found in the collection; otherwise, `false`.
     */
    public function contains(mixed $needle, ?EqualityComparerInterface $comparer = null): bool;

    /**
     * Returns a new collection that contains items from the current collection that are not found in the specified
     * collection.
     *
     * @param iterable<TKey,TItem>           $other    The collection to compare to the current collection.
     * @param EqualityComparerInterface|null $comparer The comparer to use to determine whether two items are equal.
     *
     * @return CollectionInterface<TKey,TItem> A new collection that contains items from the current collection that
     * are not found in the specified collection.
     *
     * @phpstan-param iterable<covariant TKey,covariant TItem> $other
     */
    public function diff(iterable $other, ?EqualityComparerInterface $comparer = null): self;

    /**
     * Returns a new collection that contains distinct items from the current collection.
     *
     * @param EqualityComparerInterface|null $comparer The comparer to use to determine whether two items are equal.
     *
     * @return CollectionInterface<TKey,TItem> A new collection that contains distinct items from the current
     * collection.
     */
    public function distinct(?EqualityComparerInterface $comparer = null): self;

    /**
     * Performs the specified action on each item of the collection.
     *
     * @param callable $action The action to perform on each item. If the action returns `false`, the iteration stops.
     *
     * @phpstan-param callable(TItem,TKey):mixed $action
     */
    public function each(callable $action): void;

    /**
     * Finds the first item that satisfies a condition.
     *
     * @param callable $predicate A function to test each item for a condition.
     *
     * @return TItem|null The first item that satisfies a condition; otherwise, `null`.
     *
     * @phpstan-param callable(TItem,TKey):bool $predicate
     */
    public function find(callable $predicate): mixed;

    /**
     * Finds the index of the first item that satisfies a condition.
     *
     * @param callable $predicate A function to test each item for a condition.
     *
     * @return int The index of the first item that satisfies a condition, or -1 if no item satisfies the condition.
     *
     * @phpstan-param callable(TItem,TKey):bool $predicate
     *
     * @phpstan-return int<-1, max>
     */
    public function findIndex(callable $predicate): int;

    /**
     * Returns the first item of the collection.
     *
     * @return TItem The first item of the collection.
     */
    public function first(): mixed;

    /**
     * Returns the first item of the collection, or a default value if the collection is empty.
     *
     * @param TItem|null $default The default value to return if the collection is empty.
     *
     * @return TItem|null The first item of the collection, or a default value if the collection is empty.
     */
    public function firstOrDefault(mixed $default = null): mixed;

    /**
     * Groups the items of the collection by a specified group key selector.
     *
     * @template TGroupKey
     *
     * @param callable                       $keySelector A function to extract the group key from each item.
     * @param EqualityComparerInterface|null $comparer    The comparer to use to determine whether two group keys are
     *                                                    equal.
     *
     * @return ReadonlyMap<TGroupKey,CollectionInterface<TKey,TItem>> A new map that contains groups of items.
     *
     * @phpstan-param callable(TItem,TKey):TGroupKey $keySelector
     */
    public function groupBy(callable $keySelector, ?EqualityComparerInterface $comparer = null): ReadonlyMap;

    /**
     * Returns the index of the first occurrence of a specific item in the collection.
     *
     * @param TItem                          $needle   The item to locate in the collection.
     * @param EqualityComparerInterface|null $comparer The comparer to use to determine whether two items are equal.
     *
     * @return int The index of the first occurrence of the item in the collection, or -1 if the item is not found.
     *
     * @phpstan-return int<-1, max>
     */
    public function indexOf(mixed $needle, ?EqualityComparerInterface $comparer = null): int;

    /**
     * Returns the set intersection of the current collection and the specified collection.
     *
     * @param iterable<TKey,TItem>           $other    The collection to compare to the current collection.
     * @param EqualityComparerInterface|null $comparer The comparer to use to determine whether two items are equal.
     *
     * @return CollectionInterface<TKey,TItem> A new collection that contains the set intersection of the current
     * collection and the specified collection.
     */
    public function intersect(iterable $other, ?EqualityComparerInterface $comparer = null): self;

    /**
     * Returns the last item of the collection.
     *
     * @return TItem The last item of the collection.
     */
    public function last(): mixed;

    /**
     * Returns the last item of the collection, or a default value if the collection is empty.
     *
     * @param TItem|null $default The default value to return if the collection is empty.
     *
     * @return TItem|null The last item of the collection, or a default value if the collection is empty.
     */
    public function lastOrDefault(mixed $default = null): mixed;

    /**
     * Returns a new collection that contains the results of applying a function to each item of the current collection.
     *
     * @template TResult
     *
     * @param callable $selector A transform function to apply to each item.
     *
     * @return CollectionInterface<TKey,TResult> A new collection that contains the results of applying a function to
     * each item of the current collection.
     *
     * @phpstan-param callable(TItem,TKey):TResult $selector
     */
    public function map(callable $selector): self;

    /**
     * Returns a new collection of items sorted by the specified comparer.
     *
     * @param ComparerInterface<TItem> $comparer The comparer to use to compare items.
     *
     * @return CollectionInterface<TKey,TItem> A new collection of items sorted by the specified comparer.
     */
    public function orderBy(ComparerInterface $comparer): self;

    /**
     * Applies an accumulator function over the collection.
     *
     * @template TResult
     *
     * @param callable $reducer An accumulator function to be invoked on each item.
     * @param TResult  $initial The initial accumulator value.
     *
     * @return TResult The final accumulator value.
     *
     * @phpstan-param callable(TResult,TItem,TKey):TResult $reducer
     */
    public function reduce(callable $reducer, mixed $initial): mixed;

    /**
     * Returns a new collection that contains the items of the current collection in reverse order.
     *
     * @return CollectionInterface<TKey,TItem> A new collection that contains the items of the current collection in
     * reverse order.
     */
    public function reverse(): self;

    /**
     * Returns a continuous part of the collection.
     *
     * @param int $skip The number of items to skip from the beginning of the collection.
     * @param int $take The number of items to take from the collection.
     *
     * @return CollectionInterface<TKey,TItem> A new collection that contains the continuous part of the collection.
     */
    public function slice(int $skip, int $take): self;

    /**
     * Determines whether the collection contains any item that satisfies a condition.
     *
     * @param callable $predicate A function to test each item for a condition.
     *
     * @return bool `true` if any item passes the test in the specified predicate; otherwise, `false`.
     *
     * @phpstan-param callable(TItem,TKey):bool $predicate
     */
    public function some(callable $predicate): bool;

    /**
     * Swaps the key and item of the collection.
     *
     * @return CollectionInterface<TItem,TKey> A new collection that contains the items of the current collection as
     * keys and the keys as items.
     */
    public function swapKeyItem(): self;

    /**
     * Returns a new collection that contains the items with a transformed key.
     *
     * @template TResult
     *
     * @param callable $selector A transform function to obtain a new key.
     *
     * @return CollectionInterface<TResult,TItem> A new collection that contains the items with a transformed key.
     *
     * @phpstan-param callable(TItem,TKey):TResult $selector
     */
    public function transformKey(callable $selector): self;

    /**
     * Creates a map from the collection.
     *
     * @param DuplicateKeyPolicy             $policy   What to do when a duplicate key is found.
     * @param EqualityComparerInterface|null $comparer The equality comparer to use for comparing keys.
     *
     * @return Map<TKey,TItem> The readonly map of the items.
     */
    public function toMap(
        DuplicateKeyPolicy $policy = DuplicateKeyPolicy::ThrowException,
        EqualityComparerInterface $comparer = null
    ): Map;

    /**
     * Creates a sequence from the collection.
     *
     * @return Sequence<TItem> The readonly sequence of the items.
     */
    public function toSequence(): Sequence;

    /**
     * Produces the set union of the current collection and the specified collection.
     *
     * @param iterable<TKey,TItem>           $other    The collection to compare to the current collection.
     * @param EqualityComparerInterface|null $comparer The comparer to use to determine whether two items are equal.
     *
     * @return CollectionInterface<TKey,TItem> A new collection that contains the set union of the current collection
     * and the specified collection.
     */
    public function union(iterable $other, ?EqualityComparerInterface $comparer = null): self;

    /**
     * Filters the collection based on a predicate.
     *
     * @param callable $predicate A function to test each item for a condition.
     *
     * @return CollectionInterface<TKey,TItem> A new collection that contains the items that satisfy the condition.
     *
     * @phpstan-param callable(TItem,TKey):bool $predicate
     */
    public function where(callable $predicate): self;
}
