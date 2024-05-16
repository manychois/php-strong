<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\Psr\Container;

use Manychois\PhpStrong\Psr\Container\InvalidEntryTypeError;
use Manychois\PhpStrong\Psr\Container\StrongContainerInterface;
use Psr\Container\ContainerInterface;

/**
 * A wrapper for a container that retrieve values with strong types.
 */
class StrongContainerWrapper implements StrongContainerInterface
{
    private readonly ContainerInterface $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    #region implements StrongContainerInterface

    public function getArray(string $id): array
    {
        $value = $this->container->get($id);
        if (!\is_array($value)) {
            throw new InvalidEntryTypeError(\sprintf('The entry "%s" is not an array.', $id));
        }

        return $value;
    }

    public function getBool(string $id): bool
    {
        $value = $this->container->get($id);
        if (!\is_bool($value)) {
            throw new InvalidEntryTypeError(\sprintf('The entry "%s" is not a boolean.', $id));
        }

        return $value;
    }

    public function getFloat(string $id): float
    {
        $value = $this->container->get($id);
        if (!\is_float($value)) {
            throw new InvalidEntryTypeError(\sprintf('The entry "%s" is not a float.', $id));
        }

        return $value;
    }

    public function getInt(string $id): int
    {
        $value = $this->container->get($id);
        if (!\is_int($value)) {
            throw new InvalidEntryTypeError(\sprintf('The entry "%s" is not an integer.', $id));
        }

        return $value;
    }

    public function getObject(string $className): mixed
    {
        $value = $this->container->get($className);
        if (!\is_object($value)) {
            throw new InvalidEntryTypeError(\sprintf('The entry "%s" is not an object.', $className));
        }
        if (!($value instanceof $className)) {
            throw new InvalidEntryTypeError(\sprintf('The entry "%1$s" is not an instance of "%1$s".', $className));
        }

        return $value;
    }

    public function getString(string $id): string
    {
        $value = $this->container->get($id);
        if (!\is_string($value)) {
            throw new InvalidEntryTypeError(\sprintf('The entry "%s" is not a string.', $id));
        }

        return $value;
    }

    public function get(string $id)
    {
        return $this->container->get($id);
    }

    public function has(string $id): bool
    {
        return $this->container->has($id);
    }

    #endregion implements StrongContainerInterface
}
