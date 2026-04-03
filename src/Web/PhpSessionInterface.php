<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\Web;

use Manychois\PhpStrong\ArrayReaderInterface as IArrayReader;
use Manychois\PhpStrong\Collections\MapInterface as IMap;

/**
 * Represents an abstraction of the PHP session and a wrapper of `$_SESSION`.
 */
interface PhpSessionInterface extends IArrayReader
{
    /**
     * Clears all PHP session values.
     */
    public function clear(): void;

    /**
     * Gets the PHP session value with the given name.
     *
     * @param string $name The PHP session name.
     *
     * @return mixed The PHP session value, or `null` if the value is not set.
     */
    public function get(string $name): mixed;

    /**
     * Removes the PHP session value with the given name.
     *
     * @param string $name The PHP session name.
     */
    public function remove(string $name): void;

    /**
     * Sets the PHP session value with the given name.
     *
     * @param string $name The PHP session name.
     * @param mixed $value The PHP session value.
     */
    public function set(string $name, mixed $value): void;

    #region extends IArrayReader

    /**
     * Applies the given overrides to the session.
     *
     * @param array<string,mixed>|IMap<string,mixed> $overrides The overrides to apply.
     */
    public function with(array|IMap $overrides): PhpSessionInterface;

    #endregion extends IArrayReader
}
