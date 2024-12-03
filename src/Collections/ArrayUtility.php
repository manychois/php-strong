<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\Collections;

/**
 * Provides utility methods for arrays.
 */
final class ArrayUtility
{
    /**
     * Finds the first element in an array that satisfies a specified condition.
     *
     * @template TKey
     * @template TValue
     *
     * @param array<TKey,TValue> $array     The array to search.
     * @param callable           $predicate A function to test each element for a condition.
     *
     * @return TValue|null The first element in the array that satisfies the condition, or
     * null if no element satisfies the condition.
     *
     * @phpstan-param callable(TValue,TKey):bool $predicate
     */
    public static function find(array $array, callable $predicate): mixed
    {
        foreach ($array as $key => $item) {
            if ($predicate($item, $key)) {
                return $item;
            }
        }

        return null;
    }
}
