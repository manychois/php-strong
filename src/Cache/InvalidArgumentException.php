<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\Cache;

use InvalidArgumentException as SplInvalidArgumentException;
use Psr\Cache\InvalidArgumentException as IPsrInvalidArgument;
use Psr\SimpleCache\InvalidArgumentException as IPsrSimpleInvalidArgument;

/**
 * Thrown when a cache key is empty or contains a character PSR-6 and PSR-16 reserve.
 *
 * The two PSRs declare separate marker interfaces for the same condition, so this class implements both.
 */
final class InvalidArgumentException extends SplInvalidArgumentException implements
    IPsrInvalidArgument,
    IPsrSimpleInvalidArgument
{
}
