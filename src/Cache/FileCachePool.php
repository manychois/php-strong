<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\Cache;

use DateTimeImmutable;
use Manychois\PhpStrong\Clock\UtcClock;
use Override;
use Psr\Cache\CacheItemInterface as ICacheItem;
use Psr\Cache\CacheItemPoolInterface as ICacheItemPool;
use Psr\Clock\ClockInterface as IClock;

/**
 * PSR-6 cache pool that stores one file per key under a root directory.
 */
final class FileCachePool implements ICacheItemPool
{
    private const string RESERVED_CHARS = '{}()/\\@:';

    private readonly string $directory;
    private readonly IClock $clock;
    /**
     * @var array<string, ICacheItem> Items awaiting `commit()`, keyed by cache key.
     */
    private array $deferred = [];
    /**
     * @var array<string, array{hit: bool, value: mixed, expiry: ?DateTimeImmutable}> Decoded state per cache key.
     */
    private array $memo = [];

    /**
     * Creates a file-backed cache pool.
     *
     * @param string $directory The root directory holding the cache files; created when missing.
     * @param ?IClock $clock The clock expiry is measured against; defaults to a `UtcClock`.
     *
     * @throws CacheException if the directory cannot be created or is not writable.
     */
    public function __construct(string $directory, ?IClock $clock = null)
    {
        if (!is_dir($directory) && !@mkdir($directory, 0o777, true) && !is_dir($directory)) {
            throw new CacheException(sprintf('Cannot create the cache directory "%s".', $directory));
        }

        if (!is_writable($directory)) {
            throw new CacheException(sprintf('The cache directory "%s" is not writable.', $directory));
        }

        $this->directory = rtrim($directory, '/\\');
        $this->clock = $clock ?? new UtcClock();
    }

    private function assertValidKey(string $key): void
    {
        if ($key === '' || strpbrk($key, self::RESERVED_CHARS) !== false) {
            throw new InvalidArgumentException(sprintf('Invalid cache key "%s".', $key));
        }
    }

    /**
     * @return ?array{expiry: ?DateTimeImmutable, value: mixed} The decoded body, or `null` when it is malformed.
     */
    private function parse(string $raw): ?array
    {
        $newline = strpos($raw, "\n");
        if ($newline === false) {
            return null;
        }

        $stamp = substr($raw, 0, $newline);
        if ($stamp === '' || !ctype_digit($stamp)) {
            return null;
        }

        $body = substr($raw, $newline + 1);
        if ($body === serialize(false)) {
            $value = false;
        } else {
            $value = @unserialize($body);
            if ($value === false) {
                return null;
            }
        }

        $timestamp = (int) $stamp;

        return [
            'expiry' => $timestamp === 0 ? null : new DateTimeImmutable('@' . $timestamp),
            'value' => $value,
        ];
    }

    private function pathOf(string $key): string
    {
        $hash = hash('sha256', $key);

        return sprintf('%s/%s/%s/%s.cache', $this->directory, substr($hash, 0, 2), substr($hash, 2, 2), $hash);
    }

    /**
     * @return array{hit: bool, value: mixed, expiry: ?DateTimeImmutable} The decoded state of the key.
     */
    private function read(string $key): array
    {
        if (array_key_exists($key, $this->memo)) {
            return $this->memo[$key];
        }

        $state = $this->readFile($this->pathOf($key));
        $this->memo[$key] = $state;

        return $state;
    }

    /**
     * @return array{hit: bool, value: mixed, expiry: ?DateTimeImmutable} The decoded state of the file.
     */
    private function readFile(string $path): array
    {
        $miss = ['hit' => false, 'value' => null, 'expiry' => null];
        if (!is_file($path)) {
            return $miss;
        }

        $raw = @file_get_contents($path);
        if ($raw === false) {
            return $miss;
        }

        $parsed = $this->parse($raw);
        if ($parsed === null || ($parsed['expiry'] !== null && $parsed['expiry'] <= $this->clock->now())) {
            @unlink($path);

            return $miss;
        }

        return ['hit' => true, 'value' => $parsed['value'], 'expiry' => $parsed['expiry']];
    }

    private function write(string $key, mixed $value, ?DateTimeImmutable $expiry): bool
    {
        $path = $this->pathOf($key);
        $dir = dirname($path);
        if (!is_dir($dir) && !@mkdir($dir, 0o777, true) && !is_dir($dir)) {
            return false;
        }

        $body = ($expiry?->getTimestamp() ?? 0) . "\n" . serialize($value);
        $temp = $path . '.' . bin2hex(random_bytes(6)) . '.tmp';
        if (@file_put_contents($temp, $body) === false) {
            return false;
        }

        if (!@rename($temp, $path)) {
            @unlink($temp);

            return false;
        }

        $this->memo[$key] = ['hit' => true, 'value' => $value, 'expiry' => $expiry];

        return true;
    }

    #region implements ICacheItemPool

    /**
     * @inheritDoc
     */
    #[Override]
    public function clear(): bool
    {
        return true;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function commit(): bool
    {
        return true;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function deleteItem(string $key): bool
    {
        $this->assertValidKey($key);
        unset($this->deferred[$key], $this->memo[$key]);
        $path = $this->pathOf($key);

        return !is_file($path) || @unlink($path);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function deleteItems(array $keys): bool
    {
        return true;
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
        $this->assertValidKey($key);

        $pending = $this->deferred[$key] ?? null;
        if ($pending !== null) {
            $expiry = $pending instanceof CacheItem ? $pending->getExpiry() : null;

            return new CacheItem($key, $pending->get(), true, $expiry, $this->clock);
        }

        $state = $this->read($key);

        return new CacheItem($key, $state['value'], $state['hit'], $state['expiry'], $this->clock);
    }

    /**
     * Returns the items stored under the given keys.
     *
     * @param array<string> $keys The cache keys.
     *
     * @return array<string, CacheItem> The items, keyed by cache key.
     */
    #[Override]
    public function getItems(array $keys = []): array
    {
        return [];
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function hasItem(string $key): bool
    {
        $this->assertValidKey($key);

        return isset($this->deferred[$key]) || $this->read($key)['hit'];
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function save(ICacheItem $item): bool
    {
        $key = $item->getKey();
        $this->assertValidKey($key);
        unset($this->deferred[$key]);
        $expiry = $item instanceof CacheItem ? $item->getExpiry() : null;

        if ($expiry !== null && $expiry <= $this->clock->now()) {
            return $this->deleteItem($key);
        }

        return $this->write($key, $item->get(), $expiry);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function saveDeferred(ICacheItem $item): bool
    {
        return true;
    }

    #endregion implements ICacheItemPool
}
