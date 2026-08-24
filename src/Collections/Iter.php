<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\Collections;

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
