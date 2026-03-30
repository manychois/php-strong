<?php

declare(strict_types=1);

namespace Manychois\PhpStrong;

/**
 * An interface for objects that can be compared for equality.
 *
 * @template T of object
 */
interface EquatableInterface
{
    /**
     * Checks if the current object is equal to the given object.
     *
     * @param null|T $other The object to compare with
     *
     * @return bool `true` if the objects are equal; otherwise, `false`.
     */
    public function equals(?object $other): bool;
}
