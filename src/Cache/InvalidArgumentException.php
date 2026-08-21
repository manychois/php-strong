<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\Cache;

use InvalidArgumentException as SplInvalidArgumentException;
use Psr\Cache\InvalidArgumentException as IPsrInvalidArgument;

/**
 * Thrown when a cache key is empty or contains a character PSR-6 reserves.
 */
final class InvalidArgumentException extends SplInvalidArgumentException implements IPsrInvalidArgument
{
}
