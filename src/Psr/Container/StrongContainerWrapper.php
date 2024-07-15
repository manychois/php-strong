<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\Psr\Container;

use Manychois\PhpStrong\Psr\Container\MismatchEntryTypeException;
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

    public function getObject(string $className): mixed
    {
        $value = $this->container->get($className);
        if (!\is_object($value)) {
            throw new MismatchEntryTypeException(\sprintf(
                'The entry "%s" is not an object. Type %s found.',
                $className,
                \get_debug_type($value),
            ));
        }
        if (!($value instanceof $className)) {
            throw new MismatchEntryTypeException(\sprintf(
                'The entry "%1$s" is not an instance of "%1$s". Type %2$s found.',
                $className,
                \get_debug_type($value),
            ));
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
