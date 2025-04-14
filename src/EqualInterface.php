<?php

declare(strict_types=1);

namespace Manychois\PhpStrong;

/**
 * Exposes a method that checks equality between two objects.
 */
interface EqualInterface
{
    /**
     * Returns a value indicating whether the current object is equal to another object.
     *
     * @param mixed $other The object to compare with this object.
     *
     * @return bool `true` if the current object is equal to the other object; otherwise, `false`.
     *              Implementations should perform value-based equality checking rather than reference equality.
     */
    public function equals(mixed $other): bool;
}
