<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\Cache;

use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;
use Override;
use Psr\Cache\CacheItemInterface as ICacheItem;
use Psr\Clock\ClockInterface as IClock;

/**
 * A single entry of a PSR-6 cache pool.
 *
 * Items are created by the pool; construct one directly only in tests.
 */
final class CacheItem implements ICacheItem
{
    private readonly string $key;
    private readonly IClock $clock;
    private mixed $value;
    private bool $isHit;
    private ?DateTimeImmutable $expiry;

    /**
     * Creates a cache item.
     *
     * @param string $key The key the item is stored under.
     * @param mixed $value The value the item holds.
     * @param bool $isHit Whether the value came from the cache.
     * @param ?DateTimeImmutable $expiry The moment the item expires, or `null` if it never expires.
     * @param IClock $clock The clock `expiresAfter()` measures from.
     *
     * @internal Created by {@see FileCachePool}.
     */
    public function __construct(string $key, mixed $value, bool $isHit, ?DateTimeImmutable $expiry, IClock $clock)
    {
        $this->key = $key;
        $this->value = $value;
        $this->isHit = $isHit;
        $this->expiry = $expiry;
        $this->clock = $clock;
    }

    /**
     * Returns the moment the item expires.
     *
     * @return ?DateTimeImmutable The expiry moment, or `null` if the item never expires.
     *
     * @internal Read by {@see FileCachePool} when storing the item.
     */
    public function getExpiry(): ?DateTimeImmutable
    {
        return $this->expiry;
    }

    #region implements ICacheItem

    /**
     * @inheritDoc
     */
    #[Override]
    public function expiresAfter(DateInterval|int|null $time): static
    {
        if ($time === null) {
            $this->expiry = null;

            return $this;
        }

        $now = DateTimeImmutable::createFromInterface($this->clock->now());
        if ($time instanceof DateInterval) {
            $this->expiry = $now->add($time);

            return $this;
        }

        $interval = new DateInterval('PT' . abs($time) . 'S');
        $this->expiry = $time < 0 ? $now->sub($interval) : $now->add($interval);

        return $this;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function expiresAt(?DateTimeInterface $expiration): static
    {
        $this->expiry = $expiration === null ? null : DateTimeImmutable::createFromInterface($expiration);

        return $this;
    }

    /**
     * Returns the value the item currently holds.
     *
     * @return mixed The cached value for a hit, `null` for a miss, or the value last passed to `set()`.
     */
    #[Override]
    public function get(): mixed
    {
        return $this->value;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function getKey(): string
    {
        return $this->key;
    }

    /**
     * Tells whether the value came from the cache.
     *
     * @return bool `true` if the pool found a live entry for the key, `false` otherwise. Calling `set()` does not
     * change this.
     */
    #[Override]
    public function isHit(): bool
    {
        return $this->isHit;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function set(mixed $value): static
    {
        $this->value = $value;

        return $this;
    }

    #endregion implements ICacheItem
}
