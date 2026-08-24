<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\Collections;

use ArrayIterator;
use Countable;
use InvalidArgumentException;
use Iterator;
use IteratorAggregate;
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
     * @template T
     *
     * @phpstan-param iterable<int|string,T> $source
     * @phpstan-param callable(T,int|string):bool $predicate
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
     * @template T
     *
     * @phpstan-param iterable<int|string,T> $source
     * @phpstan-param callable(T,int|string):bool $predicate
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
     * @phpstan-param iterable<int|string,T> $source
     * @phpstan-param positive-int $size
     *
     * @phpstan-return iterable<int,list<T>>
     */
    public static function chunk(iterable $source, int $size): iterable
    {
        if ($size <= 0) {
            throw new InvalidArgumentException('Size must be positive.');
        }

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
        foreach ($source as $ignored) {
            $count++;
        }

        return $count;
    }

    /**
     * Lazily filters elements of an iterable, keeping only those matching the predicate.
     *
     * @param iterable $source    The source iterable.
     * @param callable $predicate The predicate to test each element.
     *
     * @return iterable The filtered elements, preserving original keys.
     *
     * @template T
     *
     * @phpstan-param iterable<int|string,T> $source
     * @phpstan-param callable(T,int|string):bool $predicate
     *
     * @phpstan-return iterable<int|string,T>
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
     * @template T
     *
     * @phpstan-param iterable<int|string,T> $source
     * @phpstan-param ?callable(T,int|string):bool $predicate
     *
     * @phpstan-return T
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
     * @template T
     *
     * @phpstan-param iterable<int|string,T> $source
     * @phpstan-param ?callable(T,int|string):bool $predicate
     *
     * @phpstan-return ?T
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
     * @template T
     * @template TOut
     *
     * @phpstan-param iterable<int|string,T> $source
     * @phpstan-param callable(T,int|string):iterable<TOut> $mapper
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
     * @phpstan-param iterable<int|string,iterable<int|string,T>> $source
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
     * Lazily transforms each element of an iterable.
     *
     * @param iterable $source The source iterable.
     * @param callable $mapper The mapper applied to each element.
     *
     * @return iterable The transformed elements, preserving original keys.
     *
     * @template T
     * @template TOut
     *
     * @phpstan-param iterable<int|string,T> $source
     * @phpstan-param callable(T,int|string):TOut $mapper
     *
     * @phpstan-return iterable<int|string,TOut>
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
     * @template T
     * @template TCarry
     *
     * @phpstan-param iterable<int|string,T> $source
     * @phpstan-param callable(TCarry,T,int|string):TCarry $reducer
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
     * @template T
     *
     * @phpstan-param iterable<int|string,T> $source
     * @phpstan-param non-negative-int $count
     *
     * @phpstan-return iterable<int|string,T>
     */
    public static function skip(iterable $source, int $count): iterable
    {
        if ($count < 0) {
            throw new InvalidArgumentException('Count must not be negative.');
        }

        $index = 0;
        foreach ($source as $key => $value) {
            if ($index >= $count) {
                yield $key => $value;
            }
            $index++;
        }
    }

    /**
     * Lazily skips elements while the predicate holds, then yields the remainder.
     *
     * @param iterable $source The source iterable.
     * @param callable $predicate The predicate tested against leading elements.
     *
     * @return iterable The remainder starting from the first non-matching element, preserving keys.
     *
     * @template T
     *
     * @phpstan-param iterable<int|string,T> $source
     * @phpstan-param callable(T,int|string):bool $predicate
     *
     * @phpstan-return iterable<int|string,T>
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
     * @template T
     *
     * @phpstan-param iterable<int|string,T> $source
     * @phpstan-param non-negative-int $count
     *
     * @phpstan-return iterable<int|string,T>
     */
    public static function take(iterable $source, int $count): iterable
    {
        if ($count < 0) {
            throw new InvalidArgumentException('Count must not be negative.');
        }

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
     * Lazily takes elements while the predicate holds, stopping at the first non-matching element.
     *
     * @param iterable $source The source iterable.
     * @param callable $predicate The predicate tested against each element.
     *
     * @return iterable The leading matching elements, preserving keys.
     *
     * @template T
     *
     * @phpstan-param iterable<int|string,T> $source
     * @phpstan-param callable(T,int|string):bool $predicate
     *
     * @phpstan-return iterable<int|string,T>
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
     * Lazily invokes a side effect for each element, yielding it unchanged.
     *
     * @param iterable $source     The source iterable.
     * @param callable $sideEffect The side effect invoked with each element.
     *
     * @return iterable The original elements, preserving original keys.
     *
     * @template T
     *
     * @phpstan-param iterable<int|string,T> $source
     * @phpstan-param callable(T,int|string):void $sideEffect
     *
     * @phpstan-return iterable<int|string,T>
     */
    public static function tap(iterable $source, callable $sideEffect): iterable
    {
        foreach ($source as $key => $value) {
            $sideEffect($value, $key);

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
     * @template T
     *
     * @phpstan-param iterable<int|string,T> $source
     *
     * @phpstan-return array<int|string,T>
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
     * @phpstan-param iterable<int|string,T> $source
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
     * Lazily yields only the first occurrence of each distinct element.
     *
     * @param iterable $source The source iterable.
     * @param ?callable $keySelector The selector deriving a comparison key from each element; defaults to the
     *                               element itself.
     *
     * @return iterable The first-seen elements for each distinct key, preserving original keys.
     *
     * @template T
     *
     * @phpstan-param iterable<int|string,T> $source
     * @phpstan-param ?callable(T):array-key $keySelector
     *
     * @phpstan-return iterable<int|string,T>
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
     * Lazily combines multiple iterables into tuples, stopping at the shortest one.
     *
     * @param iterable ...$sources The source iterables to zip together.
     *
     * @return iterable The tuples, each a list with one element per source, with reindexed integer keys.
     *
     * @phpstan-param iterable<mixed> ...$sources
     *
     * @phpstan-return iterable<int,list<mixed>>
     */
    public static function zip(iterable ...$sources): iterable
    {
        if (\count($sources) === 0) {
            return;
        }

        $iterators = [];
        foreach ($sources as $source) {
            $iterator = self::toGenerator($source);
            $iterator->rewind();
            $iterators[] = $iterator;
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
     * Normalizes an iterable into a rewindable iterator.
     *
     * @param iterable $source The iterable to normalize.
     *
     * @return Iterator The normalized iterator.
     *
     * @phpstan-param iterable<mixed> $source
     *
     * @phpstan-return Iterator<mixed>
     */
    private static function toGenerator(iterable $source): Iterator
    {
        if ($source instanceof Iterator) {
            return $source;
        }
        if ($source instanceof IteratorAggregate) {
            return self::toGenerator($source->getIterator());
        }

        return new ArrayIterator((array) $source);
    }
}
