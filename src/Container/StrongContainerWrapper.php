<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\Container;

use Manychois\PhpStrong\Container\StrongContainerInterface as IStrongContainer;
use NoDiscard;
use Override;
use Psr\Container\ContainerInterface as IContainer;
use UnexpectedValueException;

/**
 * Wraps a PSR-11 container and adds type-safe {@see IStrongContainer::getObject} resolution.
 */
class StrongContainerWrapper implements IStrongContainer
{
    /**
     * @param IContainer $inner The underlying container.
     */
    public function __construct(
        private readonly IContainer $inner,
    ) {
    }

    #region implements IStrongContainer

    /**
     * @inheritDoc
     */
    #[NoDiscard]
    #[Override]
    public function get(string $id): mixed
    {
        return $this->inner->get($id);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function getObject(string $id, string $class): object
    {
        $value = $this->inner->get($id);
        if (!is_object($value)) {
            throw new UnexpectedValueException(
                sprintf('Container entry "%s" is not an object; got %s', $id, get_debug_type($value))
            );
        }
        if (!$value instanceof $class) {
            throw new UnexpectedValueException(
                sprintf(
                    'Container entry "%s" is not an instance of %s; got %s',
                    $id,
                    $class,
                    $value::class
                )
            );
        }

        return $value;
    }

    /**
     * @inheritDoc
     */
    #[NoDiscard]
    #[Override]
    public function has(string $id): bool
    {
        return $this->inner->has($id);
    }

    #endregion implements IStrongContainer
}
