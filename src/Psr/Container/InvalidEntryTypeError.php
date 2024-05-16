<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\Psr\Container;

use Psr\Container\ContainerExceptionInterface;
use TypeError;

/**
 * The type of the entry is invalid.
 */
final class InvalidEntryTypeError extends TypeError implements ContainerExceptionInterface
{
}
