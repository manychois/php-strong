<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\Collections;

use InvalidArgumentException;

/**
 * Provides lazy, generator-based utilities for manipulating iterables.
 */
final class Iter
{
    private function __construct()
    {
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
}
