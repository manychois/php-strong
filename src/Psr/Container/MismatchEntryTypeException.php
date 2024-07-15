<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\Psr\Container;

use LogicException;
use Psr\Container\ContainerExceptionInterface;

/**
 * The type of the entry retrieved from the container does not match the expected type.
 */
final class MismatchEntryTypeException extends LogicException implements ContainerExceptionInterface
{
}
