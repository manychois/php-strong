<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\DependencyInjection;

use Psr\Container\NotFoundExceptionInterface as INotFoundException;

/**
 * Raised when a requested service identifier is not registered.
 */
class NotFoundException extends ContainerException implements INotFoundException
{
}
