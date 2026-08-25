<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\Cache;

use __PHP_Incomplete_Class;
use DateTimeImmutable;
use Manychois\PhpStrong\Cache\Internal\CacheKey;
use Manychois\PhpStrong\Time\UtcClock;
use Override;
use Psr\Cache\CacheItemInterface as ICacheItem;
use Psr\Cache\CacheItemPoolInterface as ICacheItemPool;
use Psr\Clock\ClockInterface as IClock;
use Throwable;

/**
 * PSR-6 cache pool that stores one file per key under a root directory.
 */
final class FileCachePool implements ICacheItemPool
{
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

    /**
     * Persists any item still awaiting `commit()`, so deferred data is not lost with the pool.
     */
    public function __destruct()
    {
        if ($this->deferred === []) {
            return;
        }

        try {
            $this->commit();
        } catch (Throwable) {
            // A destructor must not throw, and a failed cache write is never worth breaking shutdown over.
        }
    }

    /**
     * Deletes every stored entry that has expired or become unreadable.
     *
     * @return int The number of files deleted.
     *
     * @phpstan-return non-negative-int
     */
    public function prune(): int
    {
        $found = glob($this->directory . '/[0-9a-f][0-9a-f]/[0-9a-f][0-9a-f]/*.cache');
        $files = $found === false ? [] : $found;
        $now = $this->clock->now();

        $count = 0;
        foreach ($files as $path) {
            $raw = @file_get_contents($path);
            if ($raw !== false) {
                $parsed = $this->parse($raw);
                if ($parsed !== null && ($parsed['expiry'] === null || $parsed['expiry'] > $now)) {
                    continue;
                }
            }

            if (@unlink($path)) {
                $count++;
            }
        }

        $this->memo = [];

        return $count;
    }

    #region implements ICacheItemPool

    /**
     * @inheritDoc
     */
    #[Override]
    public function clear(): bool
    {
        $this->memo = [];
        $this->deferred = [];

        $ok = true;
        foreach ($this->shardDirs() as $dir) {
            $ok = $this->removeTree($dir) && $ok;
        }

        return $ok;
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

        $state = $this->read($key);

        return new CacheItem($key, $state['value'], $state['hit'], $state['expiry'], $this->clock);
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

        return isset($this->deferred[$key]) || $this->read($key)['hit'];
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

        return $this->write($key, $value, $expiry);
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
            if ($value === false || $value instanceof __PHP_Incomplete_Class) {
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

    private function removeTree(string $dir): bool
    {
        $entries = @scandir($dir);
        if ($entries === false) {
            return false;
        }

        $ok = true;
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $path = $dir . '/' . $entry;
            $ok = (is_dir($path) ? $this->removeTree($path) : @unlink($path)) && $ok;
        }

        return @rmdir($dir) && $ok;
    }

    /**
     * @return list<string> The two-hex-character shard directories directly under the root.
     */
    private function shardDirs(): array
    {
        $found = glob($this->directory . '/[0-9a-f][0-9a-f]', \GLOB_ONLYDIR);

        return $found === false ? [] : $found;
    }

    private function write(string $key, mixed $value, ?DateTimeImmutable $expiry): bool
    {
        $path = $this->pathOf($key);
        $dir = dirname($path);
        if (!is_dir($dir) && !@mkdir($dir, 0o777, true) && !is_dir($dir)) {
            return false;
        }

        if (is_resource($value)) {
            return false;
        }

        try {
            $body = ($expiry?->getTimestamp() ?? 0) . "\n" . serialize($value);
        } catch (Throwable) {
            return false;
        }

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
}
