<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\Container;

use NoDiscard;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface as IContainer;
use Psr\Container\NotFoundExceptionInterface;
use UnexpectedValueException;

/**
 * A PSR-11 container that resolves entries with a verified object type.
 */
interface StrongContainerInterface extends IContainer
{
    /**
     * Gets the container entry by identifier and asserts it is an instance of the given class.
     *
     * @template T of object
     *
     * @param string $id The entry identifier.
     * @param string $class The class or interface to check against.
     *
     * @return T The resolved entry.
     *
     * @throws ContainerExceptionInterface If an error occurs while retrieving the entry.
     * @throws NotFoundExceptionInterface If no entry exists for `$id`.
     * @throws UnexpectedValueException If the entry is not an object or not an instance of `$class`.
     *
     * @phpstan-param class-string<T> $class
     *
     * @phpstan-return T
     */
    #[NoDiscard]
    public function getObject(string $id, string $class): object;
}
