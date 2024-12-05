<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\Defaults;

use DateTimeInterface;
use Manychois\PhpStrong\EqualInterface;
use Manychois\PhpStrong\EqualityComparerInterface;
use TypeError;

/**
 * Provides a default implementation of the EqualityComparer interface.
 */
class DefaultEqualityComparer implements EqualityComparerInterface
{
    #region implements EqualityComparerInterface

    /**
     * @inheritDoc
     */
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

    /**
     * @inheritDoc
     */
    public function hash(mixed $x): int|string
    {
        if (\is_int($x)) {
            return $x;
        }

        if (\is_string($x)) {
            if (\preg_match('/^[+-]?\d+(\.0+)?$/', $x) === 1) {
                return \intval($x);
            }

            return $x;
        }

        if ($x instanceof DateTimeInterface) {
            return $x->getTimestamp();
        }

        if (\is_object($x)) {
            return \spl_object_id($x);
        }

        if (\is_float($x)) {
            if ($x < \PHP_INT_MIN) {
                return \PHP_INT_MIN;
            }
            if ($x > \PHP_INT_MAX) {
                return \PHP_INT_MAX;
            }

            return \intval($x);
        }

        if (\is_bool($x)) {
            return $x ? 1 : 0;
        }

        throw new TypeError(\sprintf('Hashing of type %s is not supported.', \get_debug_type($x)));
    }

    #endregion implements EqualityComparerInterface
}
