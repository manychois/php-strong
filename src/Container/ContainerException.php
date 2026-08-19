<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\Container;

use Psr\Container\ContainerExceptionInterface as IContainerException;
use RuntimeException;

/**
 * Generic error raised while resolving a service, e.g. a circular dependency or a failing factory.
 */
class ContainerException extends RuntimeException implements IContainerException
{
}
