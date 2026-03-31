<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\Collections;

use Countable;
use InvalidArgumentException;
use Iterator;
use IteratorAggregate;
use Manychois\PhpStrong\Collections\ComparerInterface as IComparer;
use RuntimeException;
use UnderflowException;

/**
 * Defines a sequence of items with keys always starting from 0 and incrementing by 1.
 *
 * @template T
 *
 * @extends IteratorAggregate<int, T>
 *
 * @phpstan-extends IteratorAggregate<non-negative-int, T>
 */
interface SequenceInterface extends Countable, IteratorAggregate
{
    /**
     * Determines whether all values of the sequence satisfy the predicate.
     *
     * @param callable $predicate The predicate to check.
     *
     * @return bool `true` if all values of the sequence satisfy the predicate; otherwise, `false`.
     *
     * @phpstan-param callable(T,non-negative-int):bool $predicate
     */
    public function all(callable $predicate): bool;

    /**
     * Determines whether any value of the sequence satisfies the predicate.
     *
     * @param callable $predicate The predicate to check.
     *
     * @return bool `true` if any value of the sequence satisfies the predicate; otherwise, `false`.
     *
     * @phpstan-param callable(T,non-negative-int):bool $predicate
     */
    public function any(callable $predicate): bool;

    /**
     * Returns the values of the sequence as an array.
     *
     * @return list<T> The values of the sequence as an array.
     */
    public function asArray(): array;

    /**
     * Returns a new mutable list containing the values of the sequence.
     *
     * @return ListInterface<T> A new mutable list containing the values of the sequence.
     */
    public function asList(): ListInterface;

    /**
     * Chunks the sequence into smaller ones of the given maximum size.
     *
     * @param int $size The maximum size of the chunks.
     *
     * @return SequenceInterface<SequenceInterface<T>> A new sequence that is chunked into
     * smaller sequences of the given maximum size.
     *
     * @throws InvalidArgumentException if the size is less than or equal to 0.
     */
    public function chunk(int $size): SequenceInterface;

    /**
     * Determines whether the sequence contains the specified value.
     *
     * @param T $value The value to check.
     *
     * @return bool `true` if the sequence contains the specified value; otherwise, `false`.
     */
    public function contains(mixed $value): bool;

    /**
     * Returns a new sequence that contains only distinct values.
     *
     * @return SequenceInterface<T> A new sequence that contains only distinct values.
     */
    public function distinct(): SequenceInterface;

    /**
     * Returns a new sequence that contains only values that are not present in the given source.
     *
     * @param iterable<T> $sequence The sequence to exclude.
     *
     * @return SequenceInterface<T> A new sequence that contains only values that are not present in the
     * given source.
     */
    public function except(iterable $sequence): SequenceInterface;

    /**
     * Returns a new sequence that contains only values that satisfy the predicate.
     *
     * @param callable $predicate The predicate to check.
     *
     * @return SequenceInterface<T> A new sequence that contains only values that satisfy the predicate.
     *
     * @phpstan-param callable(T,non-negative-int):bool $predicate
     */
    public function filter(callable $predicate): SequenceInterface;

    /**
     * Returns the first value of the sequence.
     *
     * @param ?callable $predicate The predicate to check.
     *
     * @return T The first value of the sequence.
     *
     * @throws UnderflowException if the sequence is empty.
     * @throws RuntimeException if no value satisfies the predicate.
     *
     * @phpstan-param ?callable(T,non-negative-int):bool $predicate
     */
    public function first(?callable $predicate = null): mixed;

    /**
     * Returns the first value of the sequence, or `null` if the sequence is empty, or no value satisfies the predicate.
     *
     * @param ?callable $predicate The predicate to check.
     *
     * @return ?T The first value of the sequence, or `null` if the sequence is empty, or no value
     * satisfies the predicate.
     *
     * @phpstan-param ?callable(T,non-negative-int):bool $predicate
     */
    public function firstOrNull(?callable $predicate = null): mixed;

    /**
     * Returns the iterator for the sequence.
     *
     * @return Iterator<int, T> The iterator for the sequence.
     *
     * @phpstan-return Iterator<non-negative-int, T>
     */
    public function getIterator(): Iterator;

    /**
     * Determines whether the sequence is empty.
     *
     * @return bool `true` if the sequence is empty; otherwise, `false`.
     */
    public function isEmpty(): bool;

    /**
     * Returns a new sequence that contains only values that are present in both the current sequence and the given one.
     *
     * @param iterable<T> $sequence The sequence to intersect with.
     *
     * @return SequenceInterface<T> A new sequence that contains only values that are present in both the current
     * sequence and the given one.
     */
    public function intersect(iterable $sequence): SequenceInterface;

    /**
     * Returns the last value of the sequence.
     *
     * @param ?callable $predicate The predicate to check.
     *
     * @return T The last value of the sequence.
     *
     * @throws UnderflowException if the sequence is empty.
     * @throws RuntimeException if no value satisfies the predicate.
     *
     * @phpstan-param ?callable(T,non-negative-int):bool $predicate
     */
    public function last(?callable $predicate = null): mixed;

    /**
     * Returns the last value of the sequence, or `null` if the sequence is empty, or no value satisfies the predicate.
     *
     * @param ?callable $predicate The predicate to check.
     *
     * @return ?T The last value of the sequence, or `null` if the sequence is empty, or no value
     * satisfies the predicate.
     *
     * @phpstan-param ?callable(T,non-negative-int):bool $predicate
     */
    public function lastOrNull(?callable $predicate = null): mixed;

    /**
     * Returns a new sequence that contains the results of applying the given callback to each value of the current
     * sequence.
     *
     * @template TResult
     *
     * @param callable $callback The callback to apply to each value of the current sequence.
     *
     * @return SequenceInterface<TResult> A new sequence that contains the results of applying the given callback to
     * each value of the current sequence.
     *
     * @phpstan-param callable(T,non-negative-int):TResult $callback
     */
    public function map(callable $callback): SequenceInterface;

    /**
     * Returns a new sequence that is ordered by the given comparer.
     *
     * @param IComparer<T> $comparer The comparer to use.
     *
     * @return SequenceInterface<T> A new sequence that is ordered by the given comparer.
     */
    public function orderBy(IComparer $comparer): SequenceInterface;

    /**
     * Returns a new sequence that is ordered by the given comparer in descending order.
     *
     * @param IComparer<T> $comparer The comparer to use.
     *
     * @return SequenceInterface<T> A new sequence that is ordered by the given comparer in descending order.
     */
    public function orderDescBy(IComparer $comparer): SequenceInterface;

    /**
     * Returns a new sequence that loops through the given sequences first, then the current one.
     *
     * @param iterable<T> ...$sequences The sequences to loop through.
     *
     * @return SequenceInterface<T> A new sequence that loops through the given sequences first, then the current one.
     */
    public function precededBy(iterable ...$sequences): SequenceInterface;

    /**
     * Applies a callback function to the elements of the sequence, reducing the sequence to a single value.
     *
     * @template TResult
     *
     * @param callable $callback The callback to apply to the elements of the sequence.
     * @param TResult $initial The initial value to pass to the callback.
     *
     * @return TResult The reduced value.
     *
     * @phpstan-param callable(TResult,T,non-negative-int):TResult $callback
     */
    public function reduce(callable $callback, mixed $initial): mixed;

    /**
     * Returns a new sequence that is the reverse of the current sequence.
     *
     * @return SequenceInterface<T> A new sequence that is the reverse of the current sequence.
     */
    public function reverse(): SequenceInterface;

    /**
     * Returns a new sequence that is shuffled.
     * Randomization is not cryptographically secure.
     *
     * @return SequenceInterface<T> A new sequence that is shuffled.
     */
    public function shuffle(): SequenceInterface;

    /**
     * Returns a new sequence that contains a slice of the current sequence.
     *
     * @param int $index The zero-based index at which to start the slice.
     * @param int $length The length of the slice.
     *
     * @return SequenceInterface<T> A new sequence that contains a slice of the current sequence.
     *
     * @throws InvalidArgumentException if the index or length is less than 0.
     */
    public function slice(int $index, int $length): SequenceInterface;

    /**
     * Returns a new sequence that skips the first `count` values.
     *
     * @param int $count The number of values to skip.
     *
     * @return SequenceInterface<T> A new sequence that skips the first `count` values.
     *
     * @throws InvalidArgumentException if the count is less than 0.
     */
    public function skip(int $count): SequenceInterface;

    /**
     * Returns a new sequence that skips the last `count` values.
     *
     * @param int $count The number of values to skip.
     *
     * @return SequenceInterface<T> A new sequence that skips the last `count` values.
     *
     * @throws InvalidArgumentException if the count is less than 0.
     */
    public function skipLast(int $count): SequenceInterface;

    /**
     * Returns a new sequence that takes the first `count` values.
     *
     * @param int $count The number of values to take.
     *
     * @return SequenceInterface<T> A new sequence that takes the first `count` values.
     *
     * @throws InvalidArgumentException if the count is less than 0.
     */
    public function take(int $count): SequenceInterface;

    /**
     * Returns a new sequence that takes the last `count` values.
     *
     * @param int $count The number of values to take.
     *
     * @return SequenceInterface<T> A new sequence that takes the last `count` values.
     *
     * @throws InvalidArgumentException if the count is less than 0.
     */
    public function takeLast(int $count): SequenceInterface;

    /**
     * Returns a new sequence that loops through the current sequence first, then the given sequences.
     *
     * @param iterable<T> ...$sequences The sequences to loop through.
     *
     * @return SequenceInterface<T> A new sequence that loops through the current sequence first, then the given
     * sequences.
     */
    public function then(iterable ...$sequences): SequenceInterface;

    /**
     * Returns a new sequence that contains the values of the current sequence and the given sequence, without
     * duplicates.
     *
     * @param iterable<T> $sequence The sequence to union with.
     *
     * @return SequenceInterface<T> A new sequence that contains the values of the current sequence and the given
     * sequence.
     */
    public function union(iterable $sequence): SequenceInterface;
}
