<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\Container\Internal;

use Closure;
use Psr\Container\ContainerInterface as IContainer;

/**
 * How a service is produced and whether the result is shared.
 *
 * @internal
 */
final readonly class Definition
{
    /**
     * @param Closure $factory Produces the service.
     * @param bool $shared Whether the produced value is cached for subsequent lookups.
     *
     * @phpstan-param Closure(IContainer):mixed $factory
     */
    public function __construct(
        public Closure $factory,
        public bool $shared,
    ) {
    }
}
