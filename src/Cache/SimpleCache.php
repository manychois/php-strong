<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\Cache;

use DateInterval;
use Manychois\PhpStrong\Cache\Internal\CacheKey;
use Override;
use Psr\Cache\CacheItemPoolInterface as ICacheItemPool;
use Psr\SimpleCache\CacheInterface as ISimpleCache;

/**
 * PSR-16 cache backed by any PSR-6 cache item pool.
 *
 * The pool supplies the storage; this class only trades PSR-6's item objects for PSR-16's direct key/value calls.
 */
final class SimpleCache implements ISimpleCache
{
    private readonly ICacheItemPool $pool;

    /**
     * Creates a PSR-16 view of a PSR-6 pool.
     *
     * @param ICacheItemPool $pool The pool holding the cached values.
     */
    public function __construct(ICacheItemPool $pool)
    {
        $this->pool = $pool;
    }

    /**
     * @param iterable<mixed> $keys The keys to check.
     *
     * @return list<string> The keys, in the order given.
     *
     * @throws InvalidArgumentException if a key is not a string, is empty, or contains a reserved character.
     */
    private static function assertKeys(iterable $keys): array
    {
        $checked = [];
        foreach ($keys as $key) {
            if (!is_string($key)) {
                throw new InvalidArgumentException(
                    sprintf('A cache key must be a string, %s given.', get_debug_type($key))
                );
            }

            CacheKey::assert($key);
            $checked[] = $key;
        }

        return $checked;
    }

    #region implements ISimpleCache

    /**
     * @inheritDoc
     */
    #[Override]
    public function clear(): bool
    {
        return $this->pool->clear();
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function delete(string $key): bool
    {
        CacheKey::assert($key);

        return $this->pool->deleteItem($key);
    }

    /**
     * Deletes the given keys.
     *
     * @param iterable<mixed> $keys The keys to delete.
     *
     * @return bool `true` when every key was removed or was already absent.
     *
     * @throws InvalidArgumentException if a key is not a string, is empty, or contains a reserved character.
     */
    #[Override]
    public function deleteMultiple(iterable $keys): bool
    {
        return $this->pool->deleteItems(self::assertKeys($keys));
    }

    /**
     * Returns the value stored under the key.
     *
     * @param string $key The cache key.
     * @param mixed $default The value to return when the key holds nothing.
     *
     * @return mixed The cached value, or `$default` on a cache miss. A stored `null` is a hit, so it is returned
     * instead of `$default`.
     *
     * @throws InvalidArgumentException if the key is empty or contains a reserved character.
     */
    #[Override]
    public function get(string $key, mixed $default = null): mixed
    {
        CacheKey::assert($key);
        $item = $this->pool->getItem($key);

        return $item->isHit() ? $item->get() : $default;
    }

    /**
     * Returns the values stored under the given keys.
     *
     * @param iterable<mixed> $keys The cache keys.
     * @param mixed $default The value to use for a key that holds nothing.
     *
     * @return array<string, mixed> The values, keyed by cache key, in the order the keys were given.
     *
     * @throws InvalidArgumentException if a key is not a string, is empty, or contains a reserved character.
     */
    #[Override]
    public function getMultiple(iterable $keys, mixed $default = null): array
    {
        // Read per key rather than through the pool's getItems(): PSR-6 declares that method as a bare `iterable`,
        // so its keys and values are untyped. Our pools implement it as this very loop.
        $values = [];
        foreach (self::assertKeys($keys) as $key) {
            $item = $this->pool->getItem($key);
            $values[$key] = $item->isHit() ? $item->get() : $default;
        }

        return $values;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function has(string $key): bool
    {
        CacheKey::assert($key);

        return $this->pool->hasItem($key);
    }

    /**
     * Stores a value under the key.
     *
     * @param string $key The cache key.
     * @param mixed $value The value to store.
     * @param DateInterval|int|null $ttl How long the value stays fresh; `null` caches it for as long as the pool
     * allows, and a zero or negative number of seconds deletes the key instead.
     *
     * @return bool `true` on success.
     *
     * @throws InvalidArgumentException if the key is empty or contains a reserved character.
     */
    #[Override]
    public function set(string $key, mixed $value, DateInterval|int|null $ttl = null): bool
    {
        CacheKey::assert($key);
        $item = $this->pool->getItem($key)->set($value)->expiresAfter($ttl);

        return $this->pool->save($item);
    }

    /**
     * Stores every key/value pair of the given iterable.
     *
     * @param iterable<mixed, mixed> $values The pairs to store, keyed by cache key.
     * @param DateInterval|int|null $ttl How long the values stay fresh; `null` caches them for as long as the pool
     * allows, and a zero or negative number of seconds deletes the keys instead.
     *
     * @return bool `true` when every pair was stored.
     *
     * @throws InvalidArgumentException if a key is empty or contains a reserved character.
     */
    #[Override]
    public function setMultiple(iterable $values, DateInterval|int|null $ttl = null): bool
    {
        // Collected as tuples, not as an array keyed by cache key: PHP would cast a numeric key such as "1" back
        // to an int, and the pools only accept string keys.
        $pairs = [];
        foreach ($values as $key => $value) {
            if (is_int($key)) {
                $key = (string) $key;
            }

            if (!is_string($key)) {
                throw new InvalidArgumentException(
                    sprintf('A cache key must be a string, %s given.', get_debug_type($key))
                );
            }

            CacheKey::assert($key);
            $pairs[] = [$key, $value];
        }

        $ok = true;
        foreach ($pairs as [$key, $value]) {
            $item = $this->pool->getItem($key)->set($value)->expiresAfter($ttl);
            $ok = $this->pool->saveDeferred($item) && $ok;
        }

        return $this->pool->commit() && $ok;
    }

    #endregion implements ISimpleCache
}
