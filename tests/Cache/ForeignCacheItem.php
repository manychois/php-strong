<?php

declare(strict_types=1);

namespace Manychois\PhpStrongTests\Cache;

use DateInterval;
use DateTimeInterface;
use Override;
use Psr\Cache\CacheItemInterface as IForeignItem;

/**
 * A PSR-6 item that is not the pools' own `CacheItem`, used to check how a foreign item is handled.
 */
final class ForeignCacheItem implements IForeignItem
{
    private readonly string $key;
    private mixed $value;

    public function __construct(string $key, mixed $value)
    {
        $this->key = $key;
        $this->value = $value;
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
        return $this->value;
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
        $this->value = $value;

        return $this;
    }
}
