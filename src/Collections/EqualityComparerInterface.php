<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\Collections;

/**
 * Defines methods to support the comparison of two values for equality.
 */
interface EqualityComparerInterface
{
    /**
     * Determines whether the specified objects are equal.
     *
     * @param mixed $x The first value to compare.
     * @param mixed $y The second value to compare.
     *
     * @return bool `true` if the specified values are equal; otherwise, `false`.
     */
    public function equals(mixed $x, mixed $y): bool;
}
