<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\Collections;

use InvalidArgumentException;
use Manychois\PhpStrong\AbstractObject;

/**
 * Represents a default comparer that can compare scalar values and ComparableInterface.
 *
 * @implements ComparerInterface<mixed>
 */
class DefaultComparer extends AbstractObject implements ComparerInterface
{
    /**
     * Determines whether two objects are equal.
     *
     * @param mixed $x The first object to compare.
     * @param mixed $y The second object to compare.
     *
     * @return bool true if the specified objects are considered equal; otherwise, false.
     */
    public static function areEqual(mixed $x, mixed $y): bool
    {
        if ($x === $y) {
            return true;
        }

        if ($x instanceof AbstractObject) {
            return $x->equals($y);
        }

        if ($y instanceof AbstractObject) {
            return $y->equals($x);
        }

        return false;
    }

    #region implements ComparerInterface

    /**
     * @inheritDoc
     *
     * @throws InvalidArgumentException if the arguments $x and $y cannot be compared.
     */
    public function compare(mixed $x, mixed $y): int
    {
        if ((\is_int($x) || \is_float($x)) && (\is_int($y) || \is_float($y))) {
            return $x <=> $y;
        }

        if (\is_string($x) && \is_string($y)) {
            return \strcmp($x, $y);
        }

        if (\is_bool($x) && \is_bool($y)) {
            return $x <=> $y;
        }

        if ($x instanceof ComparableInterface) {
            return $x->compareTo($y);
        }

        if ($y instanceof ComparableInterface) {
            return -$y->compareTo($x);
        }

        throw new InvalidArgumentException('The arguments $x and $y cannot be compared.');
    }

    #endregion implements ComparerInterface
}
