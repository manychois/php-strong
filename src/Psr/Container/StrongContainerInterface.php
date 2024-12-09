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
     * Finds an entry of the container by its identifier and confirms that it is an object of the specified class.
     *
     * @param class-string<T> $className The identifier of the entry to look for, which is the class name.
     *
     * @return T The entry.
     *
     * @template T of object The type of the object.
     */
    public function getObject(string $className): mixed;
}
