<?php

declare(strict_types=1);

namespace Manychois\PhpStrong;

/**
 * Defines methods to support the comparison of objects for equality.
 */
interface EqualityComparerInterface
{
    /**
     * Determines whether the specified objects are equal.
     *
     * @param mixed $x The first object to compare.
     * @param mixed $y The second object to compare.
     *
     * @return bool `true` if the specified objects are equal; otherwise, `false`.
     */
    public function equals(mixed $x, mixed $y): bool;

    /**
     * Returns a hash code for the specified object which can be used for native PHP array key.
     *
     * @param mixed $x The object for which to get a hash code.
     *
     * @return int|string The hash code for the specified object.
     */
    public function hash(mixed $x): int|string;
}
