<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\Defaults;

use DateTimeInterface;
use Manychois\PhpStrong\ComparableInterface;
use Manychois\PhpStrong\ComparerInterface;
use Manychois\PhpStrong\EqualityComparerInterface;
use TypeError;

/**
 * Represents a default comparer that can compare scalar values and ComparableInterface.
 *
 * @implements ComparerInterface<mixed>
 */
class DefaultComparer implements ComparerInterface
{
    private readonly EqualityComparerInterface $equalityComparer;

    /**
     * Initializes a new instance of the DefaultComparer class.
     */
    public function __construct()
    {
        $this->equalityComparer = new DefaultEqualityComparer();
    }

    #region implements ComparerInterface

    public function compare(mixed $x, mixed $y): int
    {
        if ($this->equalityComparer->equals($x, $y)) {
            return 0;
        }

        if (
            \is_bool($x) && \is_bool($y) ||
            \is_int($x) && \is_int($y) ||
            \is_float($x) && \is_float($y) ||
            $x instanceof DateTimeInterface && $y instanceof DateTimeInterface
        ) {
            return $x <=> $y;
        }

        if (\is_string($x) && \is_string($y)) {
            return \strcmp($x, $y);
        }

        if ($x instanceof ComparableInterface) {
            return $x->compareTo($y);
        }

        if ($y instanceof ComparableInterface) {
            return -$y->compareTo($x);
        }

        throw new TypeError('The arguments $x and $y cannot be compared.');
    }

    #endregion implements ComparerInterface
}
