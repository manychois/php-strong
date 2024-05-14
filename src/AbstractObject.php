<?php

declare(strict_types=1);

namespace Manychois\PhpStrong;

use Stringable;

/**
 * The base class for all other classes defined in this library.
 */
abstract class AbstractObject implements Stringable
{
    #region implements Stringable

    final public function __toString(): string
    {
        return $this->toString();
    }

    #endregion implements Stringable

    /**
     * Determines whether the specified object is equal to the current object.
     *
     * @param mixed $other The object to compare with the current object.
     *
     * @return bool `true` if the objects are considered equal; otherwise, `false`.
     */
    public function equals(mixed $other): bool
    {
        return $this === $other;
    }

    /**
     * Returns a string that represents the current object.
     *
     * @return string A string that represents the current object.
     */
    public function toString(): string
    {
        return \sprintf('%s@%s', static::class, \spl_object_id($this));
    }
}
