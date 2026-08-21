# PSR-6 Cache — Design

Date: 2026-08-21
Module: `Manychois\PhpStrong\Cache`

## Goal

Ship a concrete, strongly-typed implementation of PSR-6 (`psr/cache`): a cache item and a single file-based cache
item pool, with expiry driven by an injected PSR-20 clock so it is testable without sleeping.

## Scope

In scope:

- `CacheItem` — implements `Psr\Cache\CacheItemInterface`.
- `FileCachePool` — implements `Psr\Cache\CacheItemPoolInterface`, storing one file per key under a root directory.
- `InvalidArgumentException` and `CacheException` — the two marker exception types PSR-6 mandates.
- `FileCachePool::prune()` — deletes expired files.
- A request-scoped memo of decoded item state inside the pool.

Out of scope (deliberately):

- Any second backend (APCu, array, Redis) and any storage abstraction/seam for one. A seam is added when a second
  backend actually arrives, not before.
- PSR-16 `SimpleCache`.
- Tag-based invalidation, cache stampede protection, hierarchical keys, chained pools.
- Auto-commit of deferred items on destruction.

## Dependencies and layout

- `composer.json`: require `psr/cache: ^3`; add `psr-6` and `cache` keywords. `psr/clock` is already required.
- `src/Cache/CacheItem.php`, `FileCachePool.php`, `InvalidArgumentException.php`, `CacheException.php`.
- `tests/Cache/` mirrors `src/Cache/`.
- `docs/cache.md` — reference page in the style of `docs/events.md`.
- `README.md` — add the module to the module list.

The module depends on the PSR-20 *interface* (`Psr\Clock\ClockInterface`); it defaults to
`Manychois\PhpStrong\Clock\UtcClock` when no clock is supplied, which is the only intra-package dependency.

Imports follow the project standard: `use Psr\Cache\CacheItemInterface as ICacheItem;`,
`CacheItemPoolInterface as ICacheItemPool`, `Psr\Clock\ClockInterface as IClock`.

## Exceptions

```php
final class InvalidArgumentException extends \InvalidArgumentException implements PsrInvalidArgumentException {}
final class CacheException extends \RuntimeException implements PsrCacheException {}
```

PSR-6 requires that an invalid key raise an exception implementing `Psr\Cache\InvalidArgumentException`, and that
other pool failures raise one implementing `Psr\Cache\CacheException`. Both PSR interfaces are imported aliased
(`Psr\Cache\InvalidArgumentException as IPsrInvalidArgument`, `Psr\Cache\CacheException as IPsrCacheException`) to
avoid colliding with the class names being declared.

## `CacheItem`

```php
final class CacheItem implements ICacheItem
{
    /** @internal Created by FileCachePool. */
    public function __construct(string $key, mixed $value, bool $isHit, ?DateTimeImmutable $expiry, IClock $clock);

    public function getKey(): string;
    public function get(): mixed;
    public function isHit(): bool;
    public function set(mixed $value): static;
    public function expiresAt(?DateTimeInterface $expiration): static;
    public function expiresAfter(DateInterval|int|null $time): static;
}
```

- `$key` is stored read-only; `$value` and `$expiry` are mutable through the setters.
- `isHit()` reflects retrieval only. It is `true` when the pool found a live entry (or a pending deferred item) for
  the key, and stays whatever it was when the item was created — `set()` does not flip it.
- `get()` returns the value the item currently holds: the stored value for a hit, `null` for a miss, or the
  value most recently passed to `set()`. `set()` does not make `isHit()` true — `isHit()` reports only whether
  the value came from the cache. (A literal reading of PSR-6 would have `get()` return `null` whenever
  `isHit()` is `false`, which breaks the spec's own `if (!$item->isHit()) { $item->set(...); $pool->save($item); }`
  pattern, since the pool reads the value back through `get()`.)
- `expiresAt(null)` and `expiresAfter(null)` mean "never expires".
- `expiresAfter(int $seconds)` and `expiresAfter(DateInterval $i)` resolve against `$clock->now()`, which is why the
  item holds the clock. A negative or zero `int` yields an already-expired item, i.e. saving it stores nothing
  retrievable.
- Expiry is exposed to the pool through an `@internal` accessor `getExpiry(): ?DateTimeImmutable`; it is not part of
  the PSR-6 surface and is documented as internal.

## `FileCachePool`

```php
final class FileCachePool implements ICacheItemPool
{
    public function __construct(string $directory, ?IClock $clock = null);

    public function getItem(string $key): CacheItem;
    /** @return iterable<string, CacheItem> */
    public function getItems(array $keys = []): iterable;
    public function hasItem(string $key): bool;
    public function clear(): bool;
    public function deleteItem(string $key): bool;
    public function deleteItems(array $keys): bool;
    public function save(ICacheItem $item): bool;
    public function saveDeferred(ICacheItem $item): bool;
    public function commit(): bool;

    public function prune(): int;
}
```

Return types are narrowed where PSR-6 allows it: `getItem()` returns `CacheItem`, and `getItems()` is documented as
`iterable<string, CacheItem>` keyed by the requested key.

### Construction

`$directory` is created recursively if absent. If it exists but is not a directory, or cannot be created, or is not
writable, the constructor throws `CacheException`. `$clock` defaults to a new `UtcClock`.

### Key validation

A key is invalid when it is the empty string or contains any of the PSR-6 reserved characters `{}()/\@:`. Every
method that accepts a key (`getItem`, `getItems`, `hasItem`, `deleteItem`, `deleteItems`) throws
`InvalidArgumentException` on the first invalid key, before performing any work. All other UTF-8 characters are
accepted; the key is hashed, so length is unbounded.

### On-disk format

Path: `$h = hash('sha256', $key)`, file at `<directory>/{substr($h,0,2)}/{substr($h,2,2)}/{$h}.cache`.

Body:

```
<expiry unix timestamp, or 0 for never>\n
<serialize($value)>
```

- Writes go to a temporary file in the same shard directory, then `rename()` onto the target, so a concurrent reader
  never observes a partial file.
- A read splits on the first `\n`. A body that does not parse (missing newline, non-numeric expiry, `unserialize()`
  failure) is treated as a miss and the file is deleted.
- A read of an entry whose expiry is non-zero and `<= $clock->now()` reports a miss and unlinks the file.
- The item key is not stored in the file; sha-256 collisions are treated as impossible.

### Request-scoped memo

The pool keeps `array<string, array{hit: bool, value: mixed, expiry: ?DateTimeImmutable}>` keyed by cache key. It
caches *decoded state*, never `CacheItem` instances, so each `getItem()` returns a fresh mutable item and a caller
mutating one item cannot affect a later call.

- Populated by every read (hit or miss) in `getItem`/`getItems`/`hasItem`.
- Replaced on a successful `save()` and on each item written by `commit()`.
- The entry is dropped by `deleteItem`/`deleteItems`; the whole memo is dropped by `clear()`.
- `prune()` also drops the whole memo, since it may delete entries the memo still describes.

The memo lives for the lifetime of the pool object. It makes a second `getItem()` for the same key free but means a
long-lived pool will not observe writes made by another process to a key it has already read — documented as a
limitation in `docs/cache.md`.

### Deferred items

`saveDeferred()` stores the item in `array<string, ICacheItem> $deferred` keyed by its key, and returns `true`.
Deferred items are visible to `getItem`, `getItems`, and `hasItem` before `commit()` — a lookup consults `$deferred`
first, then the memo, then disk. This is the behaviour the PSR-6 integration test suite expects.

`deleteItem`/`deleteItems` remove any matching pending deferred item as well as the file; `clear()` empties
`$deferred` entirely.

`commit()` writes every pending item, empties `$deferred`, and returns `false` if any single write failed (it still
attempts the rest). There is no `__destruct` auto-commit: throwing from a destructor is worse than requiring an
explicit `commit()`.

An item passed to `save()`/`saveDeferred()` that is not a `Manychois\PhpStrong\Cache\CacheItem` is still accepted —
only `getKey()`, `get()` and `isHit()` are used, and a foreign item's expiry is unknown, so it is stored as
never-expiring.

### Writes

`save()` encodes the item and writes it immediately, returning `true` on success and `false` if the directory or
file could not be written. An item whose expiry is already in the past is not written; `save()` deletes any existing
file for the key and returns `true`.

### `clear()` and `prune()`

`clear()` recursively deletes the two-level shard directories under `$directory` and their contents, leaving
`$directory` itself in place, and returns `false` if any deletion failed. It never touches files sitting directly in
`$directory` that are not shard directories.

`prune(): int` walks the shard tree, deletes every file whose stored expiry is non-zero and `<= now`, and returns the
number of files deleted. Unreadable or malformed files are deleted and counted.

## Testing

`tests/Cache/CacheItemTest.php` and `tests/Cache/FileCachePoolTest.php`, mirroring `src/`.

- Expiry is driven by `Manychois\PhpStrong\Clock\TestClock`; no test sleeps.
- Each pool test uses a unique temporary directory under `sys_get_temp_dir()`, removed in `tearDown()`.
- Coverage target: 100% of `src/Cache`, matching the other modules.

Cases to cover: key validation for every key-taking method; hit/miss; expiry at, before and after the boundary;
`expiresAfter` with `int`, `DateInterval` and `null`; already-expired save; deferred visibility before commit;
delete and clear dropping deferred items; memo behaviour across repeated `getItem`; malformed and truncated files;
unwritable directory at construction; `prune()` counts; a foreign `CacheItemInterface` implementation passed to
`save()`.

## Documentation

`docs/cache.md`, following `docs/events.md`: a short intro, a usage snippet, one table per class, then sections for
key rules, the on-disk format, deferred semantics, and the memo limitation. `README.md` gains the module row.

## Quality gates

`composer phpcbf` → `composer phpcs` → `composer phpstan` → `composer test`, all clean before the work is called
done.
