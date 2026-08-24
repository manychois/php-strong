<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\Collections;

use ArrayIterator;
use Countable;
use InvalidArgumentException;
use Iterator;
use IteratorAggregate;
use TypeError;
use UnderflowException;

/**
 * Provides lazy, generator-based utilities for manipulating iterables.
 */
final class Iter
{
    // This class is a static-only utility; the private constructor blocks instantiation and is never called.
    // @codeCoverageIgnoreStart
    private function __construct()
    {
    }
    // @codeCoverageIgnoreEnd

    /**
     * Eagerly checks whether every element matches a predicate.
     *
     * @param iterable $source The source iterable.
     * @param callable $predicate The predicate to test each element.
     *
     * @return bool True if every element matches (including an empty source); false otherwise.
     *
     * @template TKey
     * @template TValue
     *
     * @phpstan-param iterable<TKey,TValue> $source
     * @phpstan-param callable(TValue,TKey):bool $predicate
     */
    public static function all(iterable $source, callable $predicate): bool
    {
        foreach ($source as $key => $value) {
            if (!$predicate($value, $key)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Eagerly checks whether any element matches a predicate.
     *
     * @param iterable $source The source iterable.
     * @param callable $predicate The predicate to test each element.
     *
     * @return bool True if any element matches; false otherwise (including an empty source).
     *
     * @template TKey
     * @template TValue
     *
     * @phpstan-param iterable<TKey,TValue> $source
     * @phpstan-param callable(TValue,TKey):bool $predicate
     */
    public static function any(iterable $source, callable $predicate): bool
    {
        foreach ($source as $key => $value) {
            if ($predicate($value, $key)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Lazily groups elements of an iterable into fixed-size lists.
     *
     * @param iterable $source The source iterable.
     * @param int $size The maximum size of each chunk.
     *
     * @return iterable The chunks, each a list of at most $size elements, with reindexed integer keys.
     *
     * @throws InvalidArgumentException if $size is not positive.
     *
     * @template T
     *
     * @phpstan-param iterable<mixed,T> $source
     * @phpstan-param positive-int $size
     *
     * @phpstan-return iterable<int,list<T>>
     */
    public static function chunk(iterable $source, int $size): iterable
    {
        if ($size <= 0) {
            throw new InvalidArgumentException('Size must be positive.');
        }

        return self::chunkGenerator($source, $size);
    }

    /**
     * Eagerly counts the elements of an iterable.
     *
     * @param iterable $source The source iterable.
     *
     * @return int The number of elements.
     *
     * @phpstan-param iterable<mixed> $source
     *
     * @phpstan-return non-negative-int
     */
    public static function count(iterable $source): int
    {
        if (\is_array($source) || $source instanceof Countable) {
            return \count($source);
        }

        $count = 0;
        foreach ($source as $_) {
            $count++;
        }

        return $count;
    }

    /**
     * Lazily filters elements of an iterable, keeping only those matching the predicate.
     *
     * @param iterable $source The source iterable.
     * @param callable $predicate The predicate to test each element.
     *
     * @return iterable The filtered elements, preserving original keys.
     *
     * @template TKey
     * @template TValue
     *
     * @phpstan-param iterable<TKey,TValue> $source
     * @phpstan-param callable(TValue,TKey):bool $predicate
     *
     * @phpstan-return iterable<TKey,TValue>
     */
    public static function filter(iterable $source, callable $predicate): iterable
    {
        foreach ($source as $key => $value) {
            if ($predicate($value, $key)) {
                yield $key => $value;
            }
        }
    }

    /**
     * Eagerly returns the first element, optionally matching a predicate.
     *
     * @param iterable $source The source iterable.
     * @param ?callable $predicate The predicate the returned element must match; defaults to matching any element.
     *
     * @return mixed The first matching element.
     *
     * @throws UnderflowException if no element matches.
     *
     * @template TKey
     * @template TValue
     *
     * @phpstan-param iterable<TKey,TValue> $source
     * @phpstan-param ?callable(TValue,TKey):bool $predicate
     *
     * @phpstan-return TValue
     */
    public static function first(iterable $source, ?callable $predicate = null): mixed
    {
        foreach ($source as $key => $value) {
            if ($predicate === null || $predicate($value, $key)) {
                return $value;
            }
        }

        throw new UnderflowException('No matching element found.');
    }

    /**
     * Eagerly returns the first element, optionally matching a predicate, or null if none is found.
     *
     * @param iterable $source The source iterable.
     * @param ?callable $predicate The predicate the returned element must match; defaults to matching any element.
     *
     * @return mixed The first matching element, or null if none is found.
     *
     * @template TKey
     * @template TValue
     *
     * @phpstan-param iterable<TKey,TValue> $source
     * @phpstan-param ?callable(TValue,TKey):bool $predicate
     *
     * @phpstan-return ?TValue
     */
    public static function firstOrNull(iterable $source, ?callable $predicate = null): mixed
    {
        foreach ($source as $key => $value) {
            if ($predicate === null || $predicate($value, $key)) {
                return $value;
            }
        }

        return null;
    }

    /**
     * Lazily maps each element to an iterable and concatenates the results.
     *
     * @param iterable $source The source iterable.
     * @param callable $mapper The mapper producing an iterable per element.
     *
     * @return iterable The concatenated results, reindexed with integer keys.
     *
     * @template TKey
     * @template TOut
     * @template TValue
     *
     * @phpstan-param iterable<TKey,TValue> $source
     * @phpstan-param callable(TValue,TKey):iterable<TOut> $mapper
     *
     * @phpstan-return iterable<int,TOut>
     */
    public static function flatMap(iterable $source, callable $mapper): iterable
    {
        foreach ($source as $key => $value) {
            foreach ($mapper($value, $key) as $inner) {
                yield $inner;
            }
        }
    }

    /**
     * Lazily flattens one level of a nested iterable.
     *
     * @param iterable $source The source iterable of iterables.
     *
     * @return iterable The concatenated inner elements, with reindexed integer keys.
     *
     * @template T
     *
     * @phpstan-param iterable<mixed,iterable<mixed,T>> $source
     *
     * @phpstan-return iterable<int,T>
     */
    public static function flatten(iterable $source): iterable
    {
        foreach ($source as $inner) {
            foreach ($inner as $value) {
                yield $value;
            }
        }
    }

    /**
     * Eagerly returns the last element, optionally matching a predicate.
     *
     * Unlike first(), this cannot short-circuit: the whole source is consumed, so it never returns on an endless one.
     *
     * @param iterable $source The source iterable.
     * @param ?callable $predicate The predicate the returned element must match; defaults to matching any element.
     *
     * @return mixed The last matching element.
     *
     * @throws UnderflowException if no element matches.
     *
     * @template TKey
     * @template TValue
     *
     * @phpstan-param iterable<TKey,TValue> $source
     * @phpstan-param ?callable(TValue,TKey):bool $predicate
     *
     * @phpstan-return TValue
     */
    public static function last(iterable $source, ?callable $predicate = null): mixed
    {
        $found = [];
        foreach ($source as $key => $value) {
            if ($predicate !== null && !$predicate($value, $key)) {
                continue;
            }
            $found = [$value];
        }

        if (\count($found) === 0) {
            throw new UnderflowException('No matching element found.');
        }

        return $found[0];
    }

    /**
     * Eagerly returns the last element, optionally matching a predicate, or null if none is found.
     *
     * Unlike firstOrNull(), this cannot short-circuit: the whole source is consumed, so it never returns on an
     * endless one.
     *
     * @param iterable $source The source iterable.
     * @param ?callable $predicate The predicate the returned element must match; defaults to matching any element.
     *
     * @return mixed The last matching element, or null if none is found.
     *
     * @template TKey
     * @template TValue
     *
     * @phpstan-param iterable<TKey,TValue> $source
     * @phpstan-param ?callable(TValue,TKey):bool $predicate
     *
     * @phpstan-return ?TValue
     */
    public static function lastOrNull(iterable $source, ?callable $predicate = null): mixed
    {
        $last = null;
        foreach ($source as $key => $value) {
            if ($predicate !== null && !$predicate($value, $key)) {
                continue;
            }
            $last = $value;
        }

        return $last;
    }

    /**
     * Lazily transforms each element of an iterable.
     *
     * @param iterable $source The source iterable.
     * @param callable $mapper The mapper applied to each element.
     *
     * @return iterable The transformed elements, preserving original keys.
     *
     * @template TKey
     * @template TOut
     * @template TValue
     *
     * @phpstan-param iterable<TKey,TValue> $source
     * @phpstan-param callable(TValue,TKey):TOut $mapper
     *
     * @phpstan-return iterable<TKey,TOut>
     */
    public static function map(iterable $source, callable $mapper): iterable
    {
        foreach ($source as $key => $value) {
            yield $key => $mapper($value, $key);
        }
    }

    /**
     * Eagerly reduces an iterable to a single value.
     *
     * @param iterable $source The source iterable.
     * @param callable $reducer The reducer combining the running value with each element.
     * @param mixed $initial The initial accumulator value.
     *
     * @return mixed The final accumulated value.
     *
     * @template TCarry
     * @template TKey
     * @template TValue
     *
     * @phpstan-param iterable<TKey,TValue> $source
     * @phpstan-param callable(TCarry,TValue,TKey):TCarry $reducer
     * @phpstan-param TCarry $initial
     *
     * @phpstan-return TCarry
     */
    public static function reduce(iterable $source, callable $reducer, mixed $initial): mixed
    {
        $carry = $initial;
        foreach ($source as $key => $value) {
            $carry = $reducer($carry, $value, $key);
        }

        return $carry;
    }

    /**
     * Lazily skips the first N elements of an iterable.
     *
     * @param iterable $source The source iterable.
     * @param int $count The number of elements to skip.
     *
     * @return iterable The remaining elements, preserving original keys.
     *
     * @throws InvalidArgumentException if $count is negative.
     *
     * @template TKey
     * @template TValue
     *
     * @phpstan-param iterable<TKey,TValue> $source
     * @phpstan-param non-negative-int $count
     *
     * @phpstan-return iterable<TKey,TValue>
     */
    public static function skip(iterable $source, int $count): iterable
    {
        if ($count < 0) {
            throw new InvalidArgumentException('Count must not be negative.');
        }

        return self::skipGenerator($source, $count);
    }

    /**
     * Lazily skips elements while the predicate holds, then yields the remainder.
     *
     * @param iterable $source The source iterable.
     * @param callable $predicate The predicate tested against leading elements.
     *
     * @return iterable The remainder starting from the first non-matching element, preserving keys.
     *
     * @template TKey
     * @template TValue
     *
     * @phpstan-param iterable<TKey,TValue> $source
     * @phpstan-param callable(TValue,TKey):bool $predicate
     *
     * @phpstan-return iterable<TKey,TValue>
     */
    public static function skipWhile(iterable $source, callable $predicate): iterable
    {
        $skipping = true;
        foreach ($source as $key => $value) {
            if ($skipping && $predicate($value, $key)) {
                continue;
            }
            $skipping = false;

            yield $key => $value;
        }
    }

    /**
     * Lazily takes at most N elements of an iterable.
     *
     * @param iterable $source The source iterable.
     * @param int $count The maximum number of elements to take.
     *
     * @return iterable At most $count elements, preserving original keys.
     *
     * @throws InvalidArgumentException if $count is negative.
     *
     * @template TKey
     * @template TValue
     *
     * @phpstan-param iterable<TKey,TValue> $source
     * @phpstan-param non-negative-int $count
     *
     * @phpstan-return iterable<TKey,TValue>
     */
    public static function take(iterable $source, int $count): iterable
    {
        if ($count < 0) {
            throw new InvalidArgumentException('Count must not be negative.');
        }

        return self::takeGenerator($source, $count);
    }

    /**
     * Lazily takes elements while the predicate holds, stopping at the first non-matching element.
     *
     * @param iterable $source The source iterable.
     * @param callable $predicate The predicate tested against each element.
     *
     * @return iterable The leading matching elements, preserving keys.
     *
     * @template TKey
     * @template TValue
     *
     * @phpstan-param iterable<TKey,TValue> $source
     * @phpstan-param callable(TValue,TKey):bool $predicate
     *
     * @phpstan-return iterable<TKey,TValue>
     */
    public static function takeWhile(iterable $source, callable $predicate): iterable
    {
        foreach ($source as $key => $value) {
            if (!$predicate($value, $key)) {
                break;
            }

            yield $key => $value;
        }
    }

    /**
     * Eagerly materializes an iterable into an array, preserving keys.
     *
     * @param iterable $source The source iterable.
     *
     * @return array The materialized elements. On key collision, the last value wins.
     *
     * Keys are subject to PHP array-key coercion, so `1`, `'1'`, `1.0` and `true` all collapse to the same entry. A key
     * which is not a valid array key (e.g. an object, as a Traversable may yield) throws a native TypeError; use
     * toList() to discard keys instead.
     *
     * @throws TypeError if a key is not a valid array key.
     *
     * @template T
     *
     * @phpstan-param iterable<array-key,T> $source
     *
     * @phpstan-return array<array-key,T>
     */
    public static function toArray(iterable $source): array
    {
        $result = [];
        foreach ($source as $key => $value) {
            $result[$key] = $value;
        }

        return $result;
    }

    /**
     * Eagerly materializes an iterable into a reindexed list.
     *
     * @param iterable $source The source iterable.
     *
     * @return array The materialized elements, reindexed starting from 0.
     *
     * @template T
     *
     * @phpstan-param iterable<mixed,T> $source
     *
     * @phpstan-return list<T>
     */
    public static function toList(iterable $source): array
    {
        $result = [];
        foreach ($source as $value) {
            $result[] = $value;
        }

        return $result;
    }

    /**
     * Lazily turns the source iterables into tuples, taking one element from each per step.
     *
     * Sources are read in lockstep and their keys are discarded; iteration stops as soon as any source is exhausted,
     * so the result is as long as the shortest source. An Iterator source is read from its current position without
     * being rewound, which lets a partially consumed Generator take part.
     *
     * @param iterable ...$sources The source iterables to read in lockstep.
     *
     * @return iterable The tuples, each a list with one element per source, with reindexed integer keys.
     *
     * @phpstan-param iterable<mixed> ...$sources
     *
     * @phpstan-return iterable<int,list<mixed>>
     */
    public static function transpose(iterable ...$sources): iterable
    {
        if (\count($sources) === 0) {
            return;
        }

        $iterators = [];
        foreach ($sources as $source) {
            $iterators[] = self::toIterator($source);
        }

        while (true) {
            $tuple = [];
            foreach ($iterators as $iterator) {
                if (!$iterator->valid()) {
                    return;
                }
                $tuple[] = $iterator->current();
            }
            yield $tuple;
            foreach ($iterators as $iterator) {
                $iterator->next();
            }
        }
    }

    /**
     * Lazily yields only the first occurrence of each distinct element.
     *
     * @param iterable $source The source iterable.
     * @param ?callable $keySelector The selector deriving a comparison key; defaults to the element itself.
     *
     * @return iterable The first-seen elements for each distinct key, preserving original keys.
     *
     * When $keySelector is null, elements are compared by PHP array-key coercion (the same rules that apply when using
     * the element as an array index), not by `==`. For example `1`, `'1'`, `1.0` and `true` all coerce to the same
     * array key and are treated as duplicates, while `'1.0'` does not. In that case, TValue must be `array-key`; a
     * non-`array-key` element (e.g. an array or object) throws a native TypeError. Pass $keySelector to derive an
     * `array-key` from any element type instead.
     *
     * @throws TypeError if an element is not a valid array key and no $keySelector is given.
     *
     * @template TKey
     * @template TValue
     *
     * @phpstan-param iterable<TKey,TValue> $source
     * @phpstan-param ?callable(TValue):array-key $keySelector
     *
     * @phpstan-return iterable<TKey,TValue>
     */
    public static function unique(iterable $source, ?callable $keySelector = null): iterable
    {
        $seen = [];
        foreach ($source as $key => $value) {
            /** @var int|string $uniqueKey */
            $uniqueKey = $keySelector === null ? $value : $keySelector($value);
            if (\array_key_exists($uniqueKey, $seen)) {
                continue;
            }
            $seen[$uniqueKey] = true;

            yield $key => $value;
        }
    }

    /**
     * Lazily groups elements of an iterable into fixed-size lists.
     *
     * @param iterable $source The source iterable.
     * @param int $size The maximum size of each chunk.
     *
     * @return iterable The chunks, each a list of at most $size elements, with reindexed integer keys.
     *
     * @template T
     *
     * @phpstan-param iterable<mixed,T> $source
     * @phpstan-param positive-int $size
     *
     * @phpstan-return iterable<int,list<T>>
     */
    private static function chunkGenerator(iterable $source, int $size): iterable
    {
        $buffer = [];
        foreach ($source as $value) {
            $buffer[] = $value;
            if (\count($buffer) >= $size) {
                yield $buffer;
                $buffer = [];
            }
        }
        if (\count($buffer) > 0) {
            yield $buffer;
        }
    }

    /**
     * Lazily skips the first N elements of an iterable.
     *
     * @param iterable $source The source iterable.
     * @param int $count The number of elements to skip.
     *
     * @return iterable The remaining elements, preserving original keys.
     *
     * @template TKey
     * @template TValue
     *
     * @phpstan-param iterable<TKey,TValue> $source
     * @phpstan-param non-negative-int $count
     *
     * @phpstan-return iterable<TKey,TValue>
     */
    private static function skipGenerator(iterable $source, int $count): iterable
    {
        $index = 0;
        foreach ($source as $key => $value) {
            if ($index >= $count) {
                yield $key => $value;
            }
            $index++;
        }
    }

    /**
     * Lazily takes at most N elements of an iterable.
     *
     * @param iterable $source The source iterable.
     * @param int $count The maximum number of elements to take.
     *
     * @return iterable At most $count elements, preserving original keys.
     *
     * @template TKey
     * @template TValue
     *
     * @phpstan-param iterable<TKey,TValue> $source
     * @phpstan-param non-negative-int $count
     *
     * @phpstan-return iterable<TKey,TValue>
     */
    private static function takeGenerator(iterable $source, int $count): iterable
    {
        if ($count === 0) {
            return;
        }

        $index = 0;
        foreach ($source as $key => $value) {
            yield $key => $value;
            $index++;
            if ($index >= $count) {
                break;
            }
        }
    }

    /**
     * Normalizes an iterable into an iterator.
     *
     * @param iterable $source The iterable to normalize.
     *
     * @return Iterator The normalized iterator.
     *
     * @phpstan-param iterable<mixed> $source
     *
     * @phpstan-return Iterator<mixed>
     */
    private static function toIterator(iterable $source): Iterator
    {
        if ($source instanceof Iterator) {
            return $source;
        }
        if ($source instanceof IteratorAggregate) {
            return self::toIterator($source->getIterator());
        }

        return new ArrayIterator((array) $source);
    }
}
