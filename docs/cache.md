# PSR-6 and PSR-16 Cache — `Manychois\PhpStrong\Cache`

Two implementations of `Psr\Cache\CacheItemPoolInterface`: `FileCachePool`, which stores one file per key under a
directory you choose, and `MemoryCachePool`, which keeps its entries in memory for the lifetime of the pool object.
Both measure expiry against an injected `Psr\Clock\ClockInterface`, so a test can move time with
`Manychois\PhpStrong\Time\TestClock` instead of sleeping. `CacheItem` is the item type of both pools; the pool
creates it for you. `SimpleCache` puts the PSR-16 interface over either pool.

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

`get()` returns `null` for an item that is not a hit, as PSR-6 requires — including one you have just called `set()`
on. Read the value you are caching from your own variable, not back out of a freshly-set item.

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
| `save(CacheItemInterface $item): bool` | Writes the item immediately. An item that has already expired is not written: any existing file is deleted instead, and the result of that deletion is returned. Returns `false` when the value cannot be serialised or the file could not be written. |
| `saveDeferred(CacheItemInterface $item): bool` | Queues the item for `commit()`. Always returns `true`. |

Every method that takes a key throws `InvalidArgumentException` before doing any work when the key is invalid.

## `MemoryCachePool`

The same surface as `FileCachePool`, minus the directory: `__construct(?ClockInterface $clock = null)`. Keys, expiry
and deferred items behave identically; `prune()` drops expired entries only, as there are no unreadable files to
remove. `CacheException` never fires because there is no filesystem to fail.

```php
use Manychois\PhpStrong\Cache\MemoryCachePool;

$pool = new MemoryCachePool();
```

One behavioural difference is worth knowing. `FileCachePool` round-trips every value through `serialize()`, so what
you read back is a copy. `MemoryCachePool` stores the value as given, so an object you save and later retrieve is the
same instance — mutating it also mutates what is cached:

```php
$object = new stdClass();
$pool->save($pool->getItem('k')->set($object));
$object->n = 1;

$pool->getItem('k')->get()->n; // 1 — the very same object
```

That also means `MemoryCachePool` accepts values `FileCachePool` refuses, such as a closure or an open resource.
Where the two pools must behave alike — swapping one for the other in a test — keep to serialisable, immutable values.

Nothing is shared between pool objects and nothing survives the request.

## `CacheItem`

| Method | Notes |
| ------ | ----- |
| `getKey(): string` | The key the item is stored under. |
| `get(): mixed` | The stored value when the item is a hit, `null` otherwise — PSR-6 mandates the `null`, so a value you pass to `set()` is readable only after saving and fetching the item again. Use `isHit()` to tell a stored `null` from a miss. |
| `isHit(): bool` | Whether the value came from the cache. `set()` does **not** make this `true`. |
| `set(mixed $value): static` | Replaces the value. Returns `$this`. |
| `expiresAt(?DateTimeInterface $expiration): static` | Sets the expiry moment; `null` means the item never expires. |
| `expiresAfter(DateInterval\|int\|null $time): static` | Sets the expiry relative to the pool's clock; `null` means the item never expires. A zero or negative number of seconds produces an already-expired item. |

`set()` never makes `isHit()` true: an item is a hit only when its value came out of the cache. The pools read the
value of their own items through an internal accessor rather than `get()`, which is what lets `get()` keep to the
PSR-6 rule while `$item->set($v); $pool->save($item);` still stores `$v`.

## `SimpleCache` (PSR-16)

`Psr\SimpleCache\CacheInterface` is the same job without the item objects: keys and values go in and out directly.
`SimpleCache` is an adapter, not a third backend — hand it any PSR-6 pool, ours or anyone's.

```php
use Manychois\PhpStrong\Cache\FileCachePool;
use Manychois\PhpStrong\Cache\SimpleCache;

$cache = new SimpleCache(new FileCachePool(__DIR__ . '/var/cache'));

$cache->set('user.1', $user, 300);
$user = $cache->get('user.1', $fallback);
```

| Method | Notes |
| ------ | ----- |
| `__construct(CacheItemPoolInterface $pool)` | The pool holding the values. |
| `get(string $key, mixed $default = null): mixed` | The stored value, or `$default` on a miss. |
| `set(string $key, mixed $value, DateInterval\|int\|null $ttl = null): bool` | `null` caches for as long as the pool allows; a zero or negative TTL saves an already-expired item, which both bundled pools turn into deleting the key, as PSR-16 requires — a third-party pool decides for itself what an expired save means. |
| `delete(string $key): bool` | `true` when the key is gone, including when it was never there. |
| `clear(): bool` | Empties the whole pool. |
| `getMultiple(iterable $keys, mixed $default = null): array` | Key ⇒ value, with `$default` for each key that holds nothing. |
| `setMultiple(iterable $values, DateInterval\|int\|null $ttl = null): bool` | Queues every pair with `saveDeferred()` and writes them in one `commit()`. |
| `deleteMultiple(iterable $keys): bool` | Delegates to the pool's `deleteItems()`. |
| `has(string $key): bool` | Subject to a race with anything that deletes the key; PSR-16 recommends it only for cache warming. |

Keys follow the same rules as the pools, and every method validates them before touching the pool — the adapter has
to do that itself, because a third-party pool raises an exception implementing `Psr\Cache\InvalidArgumentException`
but not the PSR-16 one.

### Where it deviates from a literal reading of PSR-16

A stored `null` is returned as `null`, not as your `$default`. PSR-16 notes that a caller cannot normally tell the two
apart, but the underlying item knows, so a hit is reported as a hit.

PSR-16 requires an `InvalidArgumentException` when `$keys` or `$values` is neither an array nor a `Traversable`.
`psr/simple-cache` 3.0 declares those parameters as native `iterable`, so PHP raises a `TypeError` before the adapter
runs. Nothing else can be done from inside the method. A `Traversable` yielding a non-string key *is* reachable:
`getMultiple()` and `deleteMultiple()` raise `InvalidArgumentException` for one, while `setMultiple()` accepts an
integer key by casting it to string — PHP arrays already store `'1'` as `1`, so refusing it would reject ordinary
array input — and throws for any other non-string key.

`getMultiple()` reads one key at a time instead of calling the pool's `getItems()`, because PSR-6 declares that method
as a bare `iterable` whose keys and values are untyped. Both of our pools implement `getItems()` as exactly that loop,
so nothing is lost; a third-party pool with a genuine bulk-read optimisation would not be used to its full advantage.
`setMultiple()` and `deleteMultiple()` do use the pool's bulk operations.

An exception thrown by a third-party pool mid-operation propagates as it is, rather than being re-wrapped as a PSR-16
exception.

## Cache keys

A key is invalid when it is the empty string or contains one of the characters PSR-6 and PSR-16 reserve: `{`, `}`, `(`, `)`,
`/`, `\`, `@`, `:`. Those raise `Manychois\PhpStrong\Cache\InvalidArgumentException`, which implements both
`Psr\Cache\InvalidArgumentException` and `Psr\SimpleCache\InvalidArgumentException` — the two PSRs declare separate
marker interfaces for the same condition. `CacheException` likewise implements both PSRs' marker.

Every other UTF-8 string is accepted, of any length — keys are hashed before they reach the filesystem, so nothing
about the key needs to be path-safe.

## On-disk format

Each key maps to `sha256($key)`, stored at `<directory>/<first 2 hex>/<next 2 hex>/<full hash>.cache`. The two levels
of sharding keep any single directory small. The file body is the expiry as a Unix timestamp (`0` when the item never
expires), a newline, then `serialize($value)` — so values must be serialisable.

Writes go to a temporary file in the same shard directory and are then renamed onto the target, so a concurrent reader
never sees a half-written file. A file that has expired, or that cannot be parsed, is deleted the moment a read
touches it; `prune()` deletes the same files without waiting for a read.

A value the pool cannot store faithfully is refused rather than mangled: `save()` returns `false` for a resource and
for anything `serialize()` rejects, such as a closure. On the way back, an entry whose class no longer exists
deserialises to `__PHP_Incomplete_Class`, and PSR-6 requires a miss rather than corrupted data, so that entry is
reported as a miss and deleted.

## Deferred items

`saveDeferred()` queues an item instead of writing it. Until `commit()` runs, the queued item is visible to
`getItem()`, `getItems()` and `hasItem()`, and it shadows whatever is on disk for that key. `deleteItem()`,
`deleteItems()` and `clear()` drop queued items; `save()` for the same key replaces the queued one.

PSR-6 requires a pool to make sure deferred data is not lost, so `FileCachePool` commits anything still pending when
it is destroyed. Any failure raised by that commit is swallowed, because a destructor must not throw. Committing
explicitly is still better: it is the only way to learn whether the writes succeeded.

`MemoryCachePool` has no such destructor. Its store dies with the pool object either way, so there is nothing a
last-moment commit could preserve.

## Limitations

`FileCachePool` keeps a per-instance memo of the decoded state of every key it has read, so a second `getItem()` for the same
key costs nothing. The memo is refreshed by `save()`/`commit()` and dropped by `deleteItem()`, `clear()` and
`prune()`, but it is not shared between pool objects: a long-lived `FileCachePool` will not observe another process's
write to a key it has already read. Create a new pool when that matters. `MemoryCachePool` has no such memo — its
store *is* the memo — but by the same token it never sees anything another process wrote.

Each `getItem()` returns a fresh item, so mutating one item never affects an item handed out by another call.
