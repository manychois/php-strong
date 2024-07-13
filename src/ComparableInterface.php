<?php

declare(strict_types=1);

namespace Manychois\PhpStrong;

/**
 * Exposes a method that compares the current object with another object.
 */
interface ComparableInterface extends EqualInterface
{
    /**
     * Compares the current object with another object.
     *
     * @param mixed $other The object to compare with this object.
     *
     * @return int A signed integer that indicates the relative values of the two objects.
     */
    public function compareTo(mixed $other): int;
}
