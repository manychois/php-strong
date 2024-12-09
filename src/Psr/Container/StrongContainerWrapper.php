<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\Psr\Container;

use Psr\Container\ContainerInterface;

/**
 * A wrapper for a container that retrieve values with strong types.
 */
class StrongContainerWrapper implements StrongContainerInterface
{
    private readonly ContainerInterface $container;

    /**
     * Initializes a new instance of the StrongContainerWrapper class.
     *
     * @param ContainerInterface $container The container to wrap.
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    #region implements StrongContainerInterface

    /**
     * @inheritDoc
     */
    public function getObject(string $className): mixed
    {
        $value = $this->container->get($className);
        if (!($value instanceof $className)) {
            throw new MismatchEntryTypeException(\sprintf(
                'The entry "%1$s" is not an instance of "%1$s". Type %2$s found.',
                $className,
                \get_debug_type($value)
            ));
        }

        return $value;
    }

    /**
     * @inheritDoc
     */
    public function get(string $id)
    {
        return $this->container->get($id);
    }

    /**
     * @inheritDoc
     */
    public function has(string $id): bool
    {
        return $this->container->has($id);
    }

    #endregion implements StrongContainerInterface
}
