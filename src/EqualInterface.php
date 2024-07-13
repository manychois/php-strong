<?php

declare(strict_types=1);

namespace Manychois\PhpStrong;

/**
 * Exposes a method that check equality between two objects.
 */
interface EqualInterface
{
    /**
     * Returns a value indicating whether the current object is equal to another object.
     *
     * @param mixed $other The object to compare with this object.
     *
     * @return bool `true` if the current object is equal to the other object; otherwise, `false`.
     */
    public function equals(mixed $other): bool;
}
