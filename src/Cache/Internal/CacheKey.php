<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\Cache\Internal;

use Manychois\PhpStrong\Cache\InvalidArgumentException;

/**
 * Validation of cache keys, shared by the pools and the PSR-16 adapter.
 *
 * @internal
 */
final class CacheKey
{
    private const string RESERVED_CHARS = '{}()/\\@:';

    /**
     * Throws unless the key is one PSR-6 and PSR-16 allow.
     *
     * @param string $key The key to check.
     *
     * @throws InvalidArgumentException if the key is empty or contains a reserved character.
     */
    public static function assert(string $key): void
    {
        if ($key === '' || strpbrk($key, self::RESERVED_CHARS) !== false) {
            throw new InvalidArgumentException(sprintf('Invalid cache key "%s".', $key));
        }
    }
}
