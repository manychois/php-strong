<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\Collections;

use InvalidArgumentException;
use OutOfBoundsException;
use TypeError;
use UnderflowException;

/**
 * Provides eager utilities for manipulating lists.
 *
 * Every source is normalized to a list on entry, so source keys are discarded and callbacks receive the element's
 * position instead of a key. Every sequence returned is a list reindexed from 0. Nothing is modified in place.
 */
final class Seq
{
    // This class is a static-only utility; the private constructor blocks instantiation and is never called.
    // @codeCoverageIgnoreStart
    private function __construct()
    {
    }
    // @codeCoverageIgnoreEnd

    /**
     * Checks whether every element matches a predicate.
     *
     * @param iterable $source The source iterable.
     * @param callable $predicate The predicate to test each element.
     *
     * @return bool True if every element matches (including an empty source); false otherwise.
     *
     * @template T
     *
     * @phpstan-param iterable<mixed,T> $source
     * @phpstan-param callable(T,non-negative-int):bool $predicate
     */
    public static function all(iterable $source, callable $predicate): bool
    {
        $index = 0;
        foreach ($source as $element) {
            if (!$predicate($element, $index)) {
                return false;
            }
            $index++;
        }

        return true;
    }

    /**
     * Checks whether any element matches a predicate.
     *
     * @param iterable $source The source iterable.
     * @param callable $predicate The predicate to test each element.
     *
     * @return bool True if any element matches; false otherwise (including an empty source).
     *
     * @template T
     *
     * @phpstan-param iterable<mixed,T> $source
     * @phpstan-param callable(T,non-negative-int):bool $predicate
     */
    public static function any(iterable $source, callable $predicate): bool
    {
        $index = 0;
        foreach ($source as $element) {
            if ($predicate($element, $index)) {
                return true;
            }
            $index++;
        }

        return false;
    }

    /**
     * Returns the element at the given position.
     *
     * A negative $index counts from the end, so -1 is the last element and -count($source) the first.
     *
     * @param iterable $source The source iterable.
     * @param int $index The position of the element, counted from the end when negative.
     *
     * @return mixed The element at that position.
     *
     * @throws OutOfBoundsException if $index resolves outside the list.
     *
     * @template T
     *
     * @phpstan-param iterable<mixed,T> $source
     *
     * @phpstan-return T
     */
    public static function at(iterable $source, int $index): mixed
    {
        $list = Iter::toList($source);
        $count = \count($list);
        $resolved = $index < 0 ? $index + $count : $index;
        if ($resolved < 0 || $resolved >= $count) {
            throw new OutOfBoundsException(\sprintf('Index %d is out of bounds.', $index));
        }

        return $list[$resolved];
    }

    /**
     * Groups elements into fixed-size lists.
     *
     * @param iterable $source The source iterable.
     * @param int $size The maximum size of each chunk.
     *
     * @return array The chunks, each a list of at most $size elements.
     *
     * @throws InvalidArgumentException if $size is not positive.
     *
     * @template T
     *
     * @phpstan-param iterable<mixed,T> $source
     * @phpstan-param positive-int $size
     *
     * @phpstan-return list<list<T>>
     */
    public static function chunk(iterable $source, int $size): array
    {
        if ($size <= 0) {
            throw new InvalidArgumentException('Size must be positive.');
        }

        return \array_chunk(Iter::toList($source), $size);
    }

    /**
     * Appends the sources end to end into one list.
     *
     * @param iterable ...$sources The source iterables to concatenate.
     *
     * @return array The concatenated elements; empty when no sources are given.
     *
     * @template T
     *
     * @phpstan-param iterable<mixed,T> ...$sources
     *
     * @phpstan-return list<T>
     */
    public static function concat(iterable ...$sources): array
    {
        $result = [];
        foreach ($sources as $source) {
            foreach ($source as $element) {
                $result[] = $element;
            }
        }

        return $result;
    }

    /**
     * Checks whether the source contains a value, compared strictly.
     *
     * @param iterable $source The source iterable.
     * @param mixed $value The value to look for.
     *
     * @return bool True if a strictly equal element exists; false otherwise.
     *
     * @template T
     *
     * @phpstan-param iterable<mixed,T> $source
     */
    public static function contains(iterable $source, mixed $value): bool
    {
        foreach ($source as $element) {
            if ($element === $value) {
                return true;
            }
        }

        return false;
    }

    /**
     * Keeps only the elements matching a predicate.
     *
     * @param iterable $source The source iterable.
     * @param callable $predicate The predicate to test each element.
     *
     * @return array The matching elements.
     *
     * @template T
     *
     * @phpstan-param iterable<mixed,T> $source
     * @phpstan-param callable(T,non-negative-int):bool $predicate
     *
     * @phpstan-return list<T>
     */
    public static function filter(iterable $source, callable $predicate): array
    {
        $result = [];
        $index = 0;
        foreach ($source as $element) {
            if ($predicate($element, $index)) {
                $result[] = $element;
            }
            $index++;
        }

        return $result;
    }

    /**
     * Returns the first element, optionally matching a predicate.
     *
     * @param iterable $source The source iterable.
     * @param ?callable $predicate The predicate the returned element must match; defaults to matching any element.
     *
     * @return mixed The first matching element.
     *
     * @throws UnderflowException if no element matches.
     *
     * @template T
     *
     * @phpstan-param iterable<mixed,T> $source
     * @phpstan-param ?callable(T,non-negative-int):bool $predicate
     *
     * @phpstan-return T
     */
    public static function first(iterable $source, ?callable $predicate = null): mixed
    {
        $index = 0;
        foreach ($source as $element) {
            if ($predicate === null || $predicate($element, $index)) {
                return $element;
            }
            $index++;
        }

        throw new UnderflowException('No matching element found.');
    }

    /**
     * Returns the first element, optionally matching a predicate, or null if none is found.
     *
     * @param iterable $source The source iterable.
     * @param ?callable $predicate The predicate the returned element must match; defaults to matching any element.
     *
     * @return mixed The first matching element, or null if none is found.
     *
     * @template T
     *
     * @phpstan-param iterable<mixed,T> $source
     * @phpstan-param ?callable(T,non-negative-int):bool $predicate
     *
     * @phpstan-return ?T
     */
    public static function firstOrNull(iterable $source, ?callable $predicate = null): mixed
    {
        $index = 0;
        foreach ($source as $element) {
            if ($predicate === null || $predicate($element, $index)) {
                return $element;
            }
            $index++;
        }

        return null;
    }

    /**
     * Maps each element to an iterable and concatenates the results.
     *
     * @param iterable $source The source iterable.
     * @param callable $mapper The mapper producing an iterable per element.
     *
     * @return array The concatenated results.
     *
     * @template T
     * @template TOut
     *
     * @phpstan-param iterable<mixed,T> $source
     * @phpstan-param callable(T,non-negative-int):iterable<TOut> $mapper
     *
     * @phpstan-return list<TOut>
     */
    public static function flatMap(iterable $source, callable $mapper): array
    {
        $result = [];
        $index = 0;
        foreach ($source as $element) {
            foreach ($mapper($element, $index) as $inner) {
                $result[] = $inner;
            }
            $index++;
        }

        return $result;
    }

    /**
     * Flattens one level of a nested iterable.
     *
     * @param iterable $source The source iterable of iterables.
     *
     * @return array The concatenated inner elements.
     *
     * @template T
     *
     * @phpstan-param iterable<mixed,iterable<mixed,T>> $source
     *
     * @phpstan-return list<T>
     */
    public static function flatten(iterable $source): array
    {
        return Iter::toList(Iter::flatten($source));
    }

    /**
     * Returns the position of the first element strictly equal to a value.
     *
     * @param iterable $source The source iterable.
     * @param mixed $value The value to look for.
     *
     * @return ?int The position of the first strictly equal element, or null if there is none.
     *
     * @template T
     *
     * @phpstan-param iterable<mixed,T> $source
     *
     * @phpstan-return ?non-negative-int
     */
    public static function indexOf(iterable $source, mixed $value): ?int
    {
        $index = 0;
        foreach ($source as $element) {
            if ($element === $value) {
                return $index;
            }
            $index++;
        }

        return null;
    }

    /**
     * Inserts values before the given position.
     *
     * @param iterable $source The source iterable.
     * @param int $index The position to insert before; equal to the element count to append.
     * @param mixed ...$values The values to insert.
     *
     * @return array The list with the values inserted.
     *
     * @throws OutOfBoundsException if $index is negative or greater than the element count.
     *
     * @template T
     *
     * @phpstan-param iterable<mixed,T> $source
     * @phpstan-param T ...$values
     *
     * @phpstan-return list<T>
     */
    public static function insertAt(iterable $source, int $index, mixed ...$values): array
    {
        $list = Iter::toList($source);
        $count = \count($list);
        if ($index < 0 || $index > $count) {
            throw new OutOfBoundsException(\sprintf('Index %d is out of bounds.', $index));
        }
        \array_splice($list, $index, 0, \array_values($values));

        return $list;
    }

    /**
     * Returns the last element, optionally matching a predicate.
     *
     * @param iterable $source The source iterable.
     * @param ?callable $predicate The predicate the returned element must match; defaults to matching any element.
     *
     * @return mixed The last matching element.
     *
     * @throws UnderflowException if no element matches.
     *
     * @template T
     *
     * @phpstan-param iterable<mixed,T> $source
     * @phpstan-param ?callable(T,non-negative-int):bool $predicate
     *
     * @phpstan-return T
     */
    public static function last(iterable $source, ?callable $predicate = null): mixed
    {
        $found = [];
        $index = 0;
        foreach ($source as $element) {
            if ($predicate === null || $predicate($element, $index)) {
                $found = [$element];
            }
            $index++;
        }

        if (\count($found) === 0) {
            throw new UnderflowException('No matching element found.');
        }

        return $found[0];
    }

    /**
     * Returns the position of the last element strictly equal to a value.
     *
     * @param iterable $source The source iterable.
     * @param mixed $value The value to look for.
     *
     * @return ?int The position of the last strictly equal element, or null if there is none.
     *
     * @template T
     *
     * @phpstan-param iterable<mixed,T> $source
     *
     * @phpstan-return ?non-negative-int
     */
    public static function lastIndexOf(iterable $source, mixed $value): ?int
    {
        $result = null;
        $index = 0;
        foreach ($source as $element) {
            if ($element === $value) {
                $result = $index;
            }
            $index++;
        }

        return $result;
    }

    /**
     * Returns the last element, optionally matching a predicate, or null if none is found.
     *
     * @param iterable $source The source iterable.
     * @param ?callable $predicate The predicate the returned element must match; defaults to matching any element.
     *
     * @return mixed The last matching element, or null if none is found.
     *
     * @template T
     *
     * @phpstan-param iterable<mixed,T> $source
     * @phpstan-param ?callable(T,non-negative-int):bool $predicate
     *
     * @phpstan-return ?T
     */
    public static function lastOrNull(iterable $source, ?callable $predicate = null): mixed
    {
        $last = null;
        $index = 0;
        foreach ($source as $element) {
            if ($predicate === null || $predicate($element, $index)) {
                $last = $element;
            }
            $index++;
        }

        return $last;
    }

    /**
     * Transforms each element.
     *
     * @param iterable $source The source iterable.
     * @param callable $mapper The mapper applied to each element.
     *
     * @return array The transformed elements, one per source element, in order.
     *
     * @template T
     * @template TOut
     *
     * @phpstan-param iterable<mixed,T> $source
     * @phpstan-param callable(T,non-negative-int):TOut $mapper
     *
     * @phpstan-return list<TOut>
     */
    public static function map(iterable $source, callable $mapper): array
    {
        $result = [];
        $index = 0;
        foreach ($source as $element) {
            $result[] = $mapper($element, $index);
            $index++;
        }

        return $result;
    }

    /**
     * Orders the elements, ascending by default.
     *
     * The sort is stable, so elements which compare equal keep their original order. Order by a derived key with a
     * comparator such as `static fn ($a, $b) => $a->age <=> $b->age`; reverse the result for descending order.
     *
     * @param iterable $source The source iterable.
     * @param ?callable $comparator The comparator to order by; defaults to comparing elements with `<=>`.
     *
     * @return array The ordered elements.
     *
     * @template T
     *
     * @phpstan-param iterable<mixed,T> $source
     * @phpstan-param ?callable(T,T):int $comparator
     *
     * @phpstan-return list<T>
     */
    public static function orderBy(iterable $source, ?callable $comparator = null): array
    {
        $list = Iter::toList($source);
        \usort($list, $comparator ?? static fn (mixed $a, mixed $b): int => $a <=> $b);

        return $list;
    }

    /**
     * Reduces the elements to a single value.
     *
     * @param iterable $source The source iterable.
     * @param callable $reducer The reducer combining the running value with each element.
     * @param mixed $initial The initial accumulator value.
     *
     * @return mixed The final accumulated value.
     *
     * @template T
     * @template TCarry
     *
     * @phpstan-param iterable<mixed,T> $source
     * @phpstan-param callable(TCarry,T,non-negative-int):TCarry $reducer
     * @phpstan-param TCarry $initial
     *
     * @phpstan-return TCarry
     */
    public static function reduce(iterable $source, callable $reducer, mixed $initial): mixed
    {
        $carry = $initial;
        $index = 0;
        foreach ($source as $element) {
            $carry = $reducer($carry, $element, $index);
            $index++;
        }

        return $carry;
    }

    /**
     * Removes the element at the given position.
     *
     * @param iterable $source The source iterable.
     * @param int $index The position of the element to remove.
     *
     * @return array The list without that element.
     *
     * @throws OutOfBoundsException if $index is not a valid position.
     *
     * @template T
     *
     * @phpstan-param iterable<mixed,T> $source
     *
     * @phpstan-return list<T>
     */
    public static function removeAt(iterable $source, int $index): array
    {
        $list = Iter::toList($source);
        if ($index < 0 || $index >= \count($list)) {
            throw new OutOfBoundsException(\sprintf('Index %d is out of bounds.', $index));
        }
        \array_splice($list, $index, 1);

        return $list;
    }

    /**
     * Reverses the order of the elements.
     *
     * @param iterable $source The source iterable.
     *
     * @return array The elements in reverse order.
     *
     * @template T
     *
     * @phpstan-param iterable<mixed,T> $source
     *
     * @phpstan-return list<T>
     */
    public static function reverse(iterable $source): array
    {
        return \array_reverse(Iter::toList($source));
    }

    /**
     * Drops the first N elements.
     *
     * @param iterable $source The source iterable.
     * @param int $count The number of elements to drop.
     *
     * @return array The remaining elements.
     *
     * @throws InvalidArgumentException if $count is negative.
     *
     * @template T
     *
     * @phpstan-param iterable<mixed,T> $source
     * @phpstan-param non-negative-int $count
     *
     * @phpstan-return list<T>
     */
    public static function skip(iterable $source, int $count): array
    {
        if ($count < 0) {
            throw new InvalidArgumentException('Count must not be negative.');
        }

        return \array_slice(Iter::toList($source), $count);
    }

    /**
     * Drops the leading elements matching a predicate, keeping the remainder.
     *
     * @param iterable $source The source iterable.
     * @param callable $predicate The predicate tested against leading elements.
     *
     * @return array The remainder, starting from the first non-matching element.
     *
     * @template T
     *
     * @phpstan-param iterable<mixed,T> $source
     * @phpstan-param callable(T,non-negative-int):bool $predicate
     *
     * @phpstan-return list<T>
     */
    public static function skipWhile(iterable $source, callable $predicate): array
    {
        $result = [];
        $index = 0;
        $skipping = true;
        foreach ($source as $element) {
            if ($skipping && $predicate($element, $index)) {
                $index++;

                continue;
            }
            $skipping = false;
            $result[] = $element;
            $index++;
        }

        return $result;
    }

    /**
     * Returns a portion of the list.
     *
     * @param iterable $source The source iterable.
     * @param int $offset The position to start at, counted from the end when negative.
     * @param ?int $length The number of elements to take; null takes everything to the end, and a negative value
     *                     stops that many elements from the end.
     *
     * @return array The selected elements; empty when $offset is out of range.
     *
     * @template T
     *
     * @phpstan-param iterable<mixed,T> $source
     *
     * @phpstan-return list<T>
     */
    public static function slice(iterable $source, int $offset, ?int $length = null): array
    {
        return \array_slice(Iter::toList($source), $offset, $length);
    }

    /**
     * Keeps at most the first N elements.
     *
     * @param iterable $source The source iterable.
     * @param int $count The maximum number of elements to keep.
     *
     * @return array At most $count elements.
     *
     * @throws InvalidArgumentException if $count is negative.
     *
     * @template T
     *
     * @phpstan-param iterable<mixed,T> $source
     * @phpstan-param non-negative-int $count
     *
     * @phpstan-return list<T>
     */
    public static function take(iterable $source, int $count): array
    {
        if ($count < 0) {
            throw new InvalidArgumentException('Count must not be negative.');
        }

        return \array_slice(Iter::toList($source), 0, $count);
    }

    /**
     * Keeps the leading elements matching a predicate, stopping at the first non-matching one.
     *
     * @param iterable $source The source iterable.
     * @param callable $predicate The predicate tested against each element.
     *
     * @return array The leading matching elements.
     *
     * @template T
     *
     * @phpstan-param iterable<mixed,T> $source
     * @phpstan-param callable(T,non-negative-int):bool $predicate
     *
     * @phpstan-return list<T>
     */
    public static function takeWhile(iterable $source, callable $predicate): array
    {
        $result = [];
        $index = 0;
        foreach ($source as $element) {
            if (!$predicate($element, $index)) {
                break;
            }
            $result[] = $element;
            $index++;
        }

        return $result;
    }

    /**
     * Turns the source iterables into tuples, taking one element from each per step.
     *
     * @param iterable ...$sources The source iterables to read in lockstep.
     *
     * @return array The tuples, each a list with one element per source; as long as the shortest source.
     *
     * @phpstan-param iterable<mixed> ...$sources
     *
     * @phpstan-return list<list<mixed>>
     */
    public static function transpose(iterable ...$sources): array
    {
        return Iter::toList(Iter::transpose(...$sources));
    }

    /**
     * Keeps only the first occurrence of each distinct element.
     *
     * @param iterable $source The source iterable.
     * @param ?callable $keySelector The selector deriving a comparison key; defaults to the element itself.
     *
     * @return array The first-seen element for each distinct key.
     *
     * @throws TypeError if an element is not a valid array key and no $keySelector is given.
     *
     * @template T
     *
     * @phpstan-param iterable<mixed,T> $source
     * @phpstan-param ?callable(T):array-key $keySelector
     *
     * @phpstan-return list<T>
     */
    public static function unique(iterable $source, ?callable $keySelector = null): array
    {
        return Iter::toList(Iter::unique($source, $keySelector));
    }
}
