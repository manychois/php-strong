# PSR-6 Cache — `Manychois\PhpStrong\Cache`

An implementation of `Psr\Cache\CacheItemPoolInterface` that stores one file per key under a directory you choose.
`FileCachePool` measures expiry against an injected `Psr\Clock\ClockInterface`, so a test can move time with
`Manychois\PhpStrong\Clock\TestClock` instead of sleeping. `CacheItem` is the pool's item type; the pool creates it
for you.

```php
use Manychois\PhpStrong\Cache\FileCachePool;

$pool = new FileCachePool(__DIR__ . '/var/cache');

$item = $pool->getItem('user.1');
if (!$item->isHit()) {
    $item->set($repository->find(1))->expiresAfter(300);
    $pool->save($item);
}

$user = $item->get();
```

## `FileCachePool`

| Method | Notes |
| ------ | ----- |
| `__construct(string $directory, ?ClockInterface $clock = null)` | Creates `$directory` when it is missing. Throws `CacheException` if it cannot be created or is not writable. `$clock` defaults to a `UtcClock`. |
| `clear(): bool` | Removes every stored item and drops all pending deferred items. Leaves the root directory and any unrelated file in it untouched. Returns `false` if a deletion failed. |
| `commit(): bool` | Writes every deferred item and empties the queue. Returns `false` if any single write failed; the other items are still attempted. |
| `deleteItem(string $key): bool` | Removes the file and any pending deferred item for the key. Returns `true` when the key was not stored. |
| `deleteItems(array $keys): bool` | Validates every key before deleting anything, then deletes each key. |
| `getItem(string $key): CacheItem` | Returns a `CacheItem` — narrower than the `CacheItemInterface` the PSR declares. A pending deferred item wins over what is on disk. |
| `getItems(array $keys = []): array` | Returns `array<string, CacheItem>` keyed by the requested key, in the order given. Validates every key first. `getItems()` returns `[]`. |
| `hasItem(string $key): bool` | `true` when a live entry or a pending deferred item exists for the key. |
| `prune(): int` | Deletes every expired or unreadable file and returns how many were deleted. |
| `save(CacheItemInterface $item): bool` | Writes the item immediately. An item that has already expired is not written: any existing file is deleted and `true` is returned. Returns `false` when the file could not be written. |
| `saveDeferred(CacheItemInterface $item): bool` | Queues the item for `commit()`. Always returns `true`. |

Every method that takes a key throws `InvalidArgumentException` before doing any work when the key is invalid.

## `CacheItem`

| Method | Notes |
| ------ | ----- |
| `getKey(): string` | The key the item is stored under. |
| `get(): mixed` | The value the item currently holds: the stored value for a hit, `null` for a miss, or the value last passed to `set()`. |
| `isHit(): bool` | Whether the value came from the cache. `set()` does **not** make this `true`. |
| `set(mixed $value): static` | Replaces the value. Returns `$this`. |
| `expiresAt(?DateTimeInterface $expiration): static` | Sets the expiry moment; `null` means the item never expires. |
| `expiresAfter(DateInterval\|int\|null $time): static` | Sets the expiry relative to the pool's clock; `null` means the item never expires. A zero or negative number of seconds produces an already-expired item. |

A literal reading of PSR-6 would have `get()` return `null` whenever `isHit()` is `false`. That would break the
specification's own `if (!$item->isHit()) { $item->set($v); $pool->save($item); }` pattern, because the pool reads the
value back through `get()`, so `get()` returns whatever the item holds and `isHit()` reports only where the value came
from.

## Cache keys

A key is invalid when it is the empty string or contains one of the characters PSR-6 reserves: `{`, `}`, `(`, `)`,
`/`, `\`, `@`, `:`. Those raise `Manychois\PhpStrong\Cache\InvalidArgumentException`, which implements
`Psr\Cache\InvalidArgumentException`.

Every other UTF-8 string is accepted, of any length — keys are hashed before they reach the filesystem, so nothing
about the key needs to be path-safe.

## On-disk format

Each key maps to `sha256($key)`, stored at `<directory>/<first 2 hex>/<next 2 hex>/<full hash>.cache`. The two levels
of sharding keep any single directory small. The file body is the expiry as a Unix timestamp (`0` when the item never
expires), a newline, then `serialize($value)` — so values must be serialisable.

Writes go to a temporary file in the same shard directory and are then renamed onto the target, so a concurrent reader
never sees a half-written file. A file that has expired, or that cannot be parsed, is deleted the moment a read
touches it; `prune()` deletes the same files without waiting for a read.

## Deferred items

`saveDeferred()` queues an item instead of writing it. Until `commit()` runs, the queued item is visible to
`getItem()`, `getItems()` and `hasItem()`, and it shadows whatever is on disk for that key. `deleteItem()`,
`deleteItems()` and `clear()` drop queued items; `save()` for the same key replaces the queued one.

There is no auto-commit on destruction — throwing from a destructor is worse than a missing write — so call `commit()`
explicitly.

## Limitations

The pool keeps a per-instance memo of the decoded state of every key it has read, so a second `getItem()` for the same
key costs nothing. The memo is refreshed by `save()`/`commit()` and dropped by `deleteItem()`, `clear()` and
`prune()`, but it is not shared between pool objects: a long-lived `FileCachePool` will not observe another process's
write to a key it has already read. Create a new pool when that matters.

Each `getItem()` returns a fresh item, so mutating one item never affects an item handed out by another call.
