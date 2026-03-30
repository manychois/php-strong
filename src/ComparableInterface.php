<?php

declare(strict_types=1);

namespace Manychois\PhpStrong;

/**
 * An interface for objects that can be compared.
 *
 * @template T of object
 */
interface ComparableInterface
{
    /**
     * Compares the current object with the given object.
     *
     * @param T $other The object to compare with
     *
     * @return int The comparison result:
     * @phpstan-return int<-1,1>
     *                   -1 if the current object is less than the given object,
     *                    0 if they are equal,
     *                    1 if the current object is greater than the given object
     */
    public function compareTo(object $other): int;
}
