<?php

declare(strict_types=1);

namespace Manychois\PhpStrong;

use DateTimeInterface;
use OutOfBoundsException;

/**
 * Provides a default implementation of the EqualityComparer interface.
 */
class DefaultEqualityComparer implements EqualityComparerInterface
{
    #region implements EqualityComparerInterface

    public function equals(mixed $x, mixed $y): bool
    {
        if ($x === $y) {
            return true;
        }

        if ((\is_int($x) || \is_float($x)) && (\is_int($y) || \is_float($y))) {
            return \abs($x - $y) < \PHP_FLOAT_EPSILON;
        }

        if ($x instanceof DateTimeInterface && $y instanceof DateTimeInterface) {
            return $x->getTimestamp() === $y->getTimestamp();
        }

        if ($x instanceof EqualInterface) {
            return $x->equals($y);
        }

        if ($y instanceof EqualInterface) {
            return $y->equals($x);
        }

        return false;
    }

    public function hash(mixed $x): int|string
    {
        if (\is_int($x) || \is_string($x)) {
            return $x;
        }

        if ($x instanceof DateTimeInterface) {
            return $x->getTimestamp();
        }

        if (\is_object($x)) {
            return \spl_object_hash($x);
        }

        if (\is_float($x)) {
            if ($x >= \PHP_INT_MAX) {
                return \PHP_INT_MAX;
            }
            if ($x <= \PHP_INT_MIN) {
                return \PHP_INT_MIN;
            }

            return \intval($x);
        }

        throw new OutOfBoundsException(\sprintf('Type %s is not supported.', \get_debug_type($x)));
    }

    #endregion implements EqualityComparerInterface
}
