<?php

declare(strict_types=1);

namespace Manychois\PhpStrong;

/**
 * Exposes a method that compares two objects.
 */
interface ComparerInterface
{
    /**
     * Returns a value indicating whether the first object is less than, equal to, or greater than the second object.
     *
     * @param mixed $x The first object to compare.
     * @param mixed $y The second object to compare.
     *
     * @return int A signed integer that indicates the relative values of `x` and `y`.
     */
    public function compare(mixed $x, mixed $y): int;
}
