<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\Cache;

use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;
use Override;
use Psr\Cache\CacheItemInterface as ICacheItem;
use Psr\Clock\ClockInterface as IClock;
use Throwable;

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

    /**
     * Returns the value the item holds, whether or not it came from the cache.
     *
     * @return mixed The stored value, or the value last passed to `set()`.
     *
     * @internal Read by the pools when storing the item; `get()` cannot serve this because PSR-6 requires it to
     * return `null` for an item that is not a hit.
     */
    public function getRawValue(): mixed
    {
        return $this->value;
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

        $negative = $time < 0;
        try {
            $interval = new DateInterval('PT' . ltrim((string) $time, '-') . 'S');
            $this->expiry = $negative ? $now->sub($interval) : $now->add($interval);
        } catch (Throwable) {
            // A second count too large for DateInterval; clamp instead of leaking a non-PSR exception.
            $this->expiry = $negative ? $now : $now->setDate(9999, 12, 31)->setTime(23, 59, 59);
        }

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
     * Returns the value of a cache hit.
     *
     * @return mixed The cached value, or `null` when the item is not a hit. PSR-6 mandates the `null`, so check
     * `isHit()` to tell a stored `null` from a miss; a value passed to `set()` is readable only after the item has
     * been saved and fetched again.
     */
    #[Override]
    public function get(): mixed
    {
        return $this->isHit ? $this->value : null;
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
