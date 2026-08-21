<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\Links;

use InvalidArgumentException as SplInvalidArgumentException;

/**
 * Thrown when a link relation or attribute does not satisfy PSR-13.
 *
 * PSR-13 declares no exception interface, so this class implements none.
 */
final class InvalidArgumentException extends SplInvalidArgumentException
{
}
