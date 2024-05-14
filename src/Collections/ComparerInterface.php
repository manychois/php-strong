<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\Collections;

/**
 * Exposes a method that compares two objects.
 *
 * @template T
 */
interface ComparerInterface
{
    /**
     * Returns a value indicating whether the first object is less than, equal to, or greater than the second object.
     *
     * @param T $x The first object to compare.
     * @param T $y The second object to compare.
     *
     * @return int A signed integer that indicates the relative values of `x` and `y`.
     */
    public function compare(mixed $x, mixed $y): int;
}
