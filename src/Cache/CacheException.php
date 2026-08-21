<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\Cache;

use Psr\Cache\CacheException as IPsrCacheException;
use RuntimeException;

/**
 * Thrown when the cache pool itself cannot operate, for example when its directory is unusable.
 */
final class CacheException extends RuntimeException implements IPsrCacheException
{
}
