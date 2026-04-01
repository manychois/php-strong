<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\Collections;

use NoDiscard;

/**
 * Defines methods to compare two values of the same type.
 *
 * @template T
 */
interface ComparerInterface
{
    /**
     * Compares the two values.
     *
     * @param T $x The first value to compare.
     * @param T $y The second value to compare.
     *
     * @return int The comparison result:
     *                   -1 if the first value is less than the second value,
     *                    0 if the two values are equal,
     *                    1 if the first value is greater than the second value
     *
     * @phpstan-return int<-1,1>
     */
    #[NoDiscard]
    public function compare(mixed $x, mixed $y): int;
}
