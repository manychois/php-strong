<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\Cache;

use DateTimeImmutable;
use Manychois\PhpStrong\Cache\Internal\CacheKey;
use Manychois\PhpStrong\Time\UtcClock;
use Override;
use Psr\Cache\CacheItemInterface as ICacheItem;
use Psr\Cache\CacheItemPoolInterface as ICacheItemPool;
use Psr\Clock\ClockInterface as IClock;

/**
 * PSR-6 cache pool that keeps its entries in memory for the lifetime of the pool object.
 *
 * Values are stored as given, so an object retrieved from the pool is the object that was saved.
 */
final class MemoryCachePool implements ICacheItemPool
{
    private readonly IClock $clock;
    /**
     * @var array<string, ICacheItem> Items awaiting `commit()`, keyed by cache key.
     */
    private array $deferred = [];
    /**
     * @var array<string, array{value: mixed, expiry: ?DateTimeImmutable}> Stored entries, keyed by cache key.
     */
    private array $entries = [];

    /**
     * Creates an in-memory cache pool.
     *
     * @param ?IClock $clock The clock expiry is measured against; defaults to a `UtcClock`.
     */
    public function __construct(?IClock $clock = null)
    {
        $this->clock = $clock ?? new UtcClock();
    }

    /**
     * Drops every stored entry that has expired.
     *
     * @return int The number of entries dropped.
     *
     * @phpstan-return non-negative-int
     */
    public function prune(): int
    {
        $now = $this->clock->now();

        $count = 0;
        foreach ($this->entries as $key => $entry) {
            if ($entry['expiry'] === null || $entry['expiry'] > $now) {
                continue;
            }

            unset($this->entries[$key]);
            $count++;
        }

        return $count;
    }

    /**
     * @return ?array{value: mixed, expiry: ?DateTimeImmutable} The entry, or `null` when it is absent or expired.
     */
    private function liveEntry(string $key): ?array
    {
        $entry = $this->entries[$key] ?? null;
        if ($entry === null) {
            return null;
        }

        if ($entry['expiry'] !== null && $entry['expiry'] <= $this->clock->now()) {
            unset($this->entries[$key]);

            return null;
        }

        return $entry;
    }

    #region implements ICacheItemPool

    /**
     * @inheritDoc
     */
    #[Override]
    public function clear(): bool
    {
        $this->entries = [];
        $this->deferred = [];

        return true;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function commit(): bool
    {
        $pending = $this->deferred;
        $this->deferred = [];

        $ok = true;
        foreach ($pending as $item) {
            $ok = $this->save($item) && $ok;
        }

        return $ok;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function deleteItem(string $key): bool
    {
        CacheKey::assert($key);
        unset($this->deferred[$key], $this->entries[$key]);

        return true;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function deleteItems(array $keys): bool
    {
        foreach ($keys as $key) {
            CacheKey::assert($key);
        }

        $ok = true;
        foreach ($keys as $key) {
            $ok = $this->deleteItem($key) && $ok;
        }

        return $ok;
    }

    /**
     * Returns the item stored under the key.
     *
     * @param string $key The cache key.
     *
     * @return CacheItem The item; a miss when nothing live is stored under the key.
     *
     * @throws InvalidArgumentException if the key is empty or contains a reserved character.
     */
    #[Override]
    public function getItem(string $key): CacheItem
    {
        CacheKey::assert($key);

        $pending = $this->deferred[$key] ?? null;
        if ($pending !== null) {
            $expiry = $pending instanceof CacheItem ? $pending->getExpiry() : null;
            $value = $pending instanceof CacheItem ? $pending->getRawValue() : $pending->get();

            return new CacheItem($key, $value, true, $expiry, $this->clock);
        }

        $entry = $this->liveEntry($key);
        if ($entry === null) {
            return new CacheItem($key, null, false, null, $this->clock);
        }

        return new CacheItem($key, $entry['value'], true, $entry['expiry'], $this->clock);
    }

    /**
     * Returns the items stored under the given keys.
     *
     * @param array<string> $keys The cache keys.
     *
     * @return array<string, CacheItem> The items, keyed by cache key, in the order the keys were given.
     *
     * @throws InvalidArgumentException if any key is empty or contains a reserved character.
     */
    #[Override]
    public function getItems(array $keys = []): array
    {
        foreach ($keys as $key) {
            CacheKey::assert($key);
        }

        $items = [];
        foreach ($keys as $key) {
            $items[$key] = $this->getItem($key);
        }

        return $items;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function hasItem(string $key): bool
    {
        CacheKey::assert($key);

        return isset($this->deferred[$key]) || $this->liveEntry($key) !== null;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function save(ICacheItem $item): bool
    {
        $key = $item->getKey();
        CacheKey::assert($key);
        unset($this->deferred[$key]);
        $expiry = $item instanceof CacheItem ? $item->getExpiry() : null;

        if ($expiry !== null && $expiry <= $this->clock->now()) {
            return $this->deleteItem($key);
        }

        $value = $item instanceof CacheItem ? $item->getRawValue() : $item->get();
        $this->entries[$key] = ['value' => $value, 'expiry' => $expiry];

        return true;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function saveDeferred(ICacheItem $item): bool
    {
        $key = $item->getKey();
        CacheKey::assert($key);
        $this->deferred[$key] = $item;

        return true;
    }

    #endregion implements ICacheItemPool
}
