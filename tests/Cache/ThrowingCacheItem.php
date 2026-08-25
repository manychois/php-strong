<?php

declare(strict_types=1);

namespace Manychois\PhpStrongTests\Cache;

use DateInterval;
use DateTimeInterface;
use Override;
use Psr\Cache\CacheItemInterface as IThrowingItem;
use RuntimeException;

/**
 * A PSR-6 item whose value cannot be read, used to check that a failing commit is contained.
 */
final class ThrowingCacheItem implements IThrowingItem
{
    private readonly string $key;

    public function __construct(string $key)
    {
        $this->key = $key;
    }

    #[Override]
    public function expiresAfter(DateInterval|int|null $time): static
    {
        return $this;
    }

    #[Override]
    public function expiresAt(?DateTimeInterface $expiration): static
    {
        return $this;
    }

    #[Override]
    public function get(): mixed
    {
        throw new RuntimeException('The value is unavailable.');
    }

    #[Override]
    public function getKey(): string
    {
        return $this->key;
    }

    #[Override]
    public function isHit(): bool
    {
        return false;
    }

    #[Override]
    public function set(mixed $value): static
    {
        return $this;
    }
}
