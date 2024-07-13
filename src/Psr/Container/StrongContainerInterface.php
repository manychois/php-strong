<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\Psr\Container;

use Psr\Container\ContainerInterface;

/**
 * A container that retrieve values with strong types.
 */
interface StrongContainerInterface extends ContainerInterface
{
    /**
     * Finds an entry of the container by its identifier and returns it.
     *
     * @param string $id Identifier of the entry to look for.
     *
     * @return bool The entry.
     */
    public function getBool(string $id): bool;

    /**
     * Finds an entry of the container by its identifier and returns it.
     *
     * @param string $id Identifier of the entry to look for.
     *
     * @return float The entry.
     */
    public function getFloat(string $id): float;

    /**
     * Finds an entry of the container by its identifier and returns it.
     *
     * @param string $id Identifier of the entry to look for.
     *
     * @return int The entry.
     */
    public function getInt(string $id): int;

    /**
     * Finds an entry of the container by its identifier and returns it.
     *
     * @param string $className The identifier of the entry to look for, which is the class name.
     *
     * @return T The entry.
     *
     * @template T of object The type of the object.
     *
     * @phpstan-param class-string<T> $className
     *
     * @phpstan-return T
     */
    public function getObject(string $className): mixed;

    /**
     * Finds an entry of the container by its identifier and returns it.
     *
     * @param string $id Identifier of the entry to look for.
     *
     * @return string The entry.
     */
    public function getString(string $id): string;
}
