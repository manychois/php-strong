# Seq: eager list utilities — design

## Goal

Provide a static utility class for manipulating `list<T>` values: the eager,
positional counterpart to `Iter`. `Iter` covers lazy traversal over arbitrary
`iterable`s with arbitrary keys; `Seq` covers the ordered, random-access work
that only a materialized list can do (sorting, slicing, index lookup,
insertion/removal), and re-exposes `Iter`'s transformations in a form that
returns a `list<T>` directly instead of a lazy `iterable`.

## Location

`src/Collections/Seq.php`, tests mirrored at `tests/Collections/SeqTest.php`.

## Class

`Manychois\PhpStrong\Collections\Seq`

- `final class Seq`, private constructor (uninstantiable, static-only), with
  the same `@codeCoverageIgnore` treatment as `Iter`'s constructor.
- Implements no interface — all methods are public static, so the coding
  standard's ordering collapses to a single alphabetical list, with private
  helpers after the public methods.
- Generic over `T` per method via `@template T` PHPDoc.

## Core contract

- **Input:** every source parameter is typed `iterable`
  (`@phpstan-param iterable<mixed,T> $source`). It is normalized to a list on
  entry via `Iter::toList()`, so arrays (list- or map-keyed), Generators, and
  any other Traversable are all accepted, and source keys are discarded.
- **Output:** every sequence-returning method returns a genuine `list<T>`
  (`@phpstan-return list<T>`) — reindexed from 0, no gaps.
- **Callbacks:** receive `(T $value, int $index)` where `$index` is the
  element's position in the normalized list
  (`@phpstan-param callable(T,non-negative-int):bool $predicate`). This is the
  deliberate difference from `Iter`, whose callbacks receive an arbitrary key
  and which therefore carries a `TKey` template. `Seq` has no `TKey`.
- **Non-mutating:** no method modifies the caller's array. Ordering and
  insertion operate on the normalized copy.

## Methods

All public and static. Grouped below for readability and alphabetical within
each group; in the class itself the two groups merge into one alphabetical
list, as the coding standard requires.

### Mirrored from `Iter`, returning eager results

- `all(iterable $source, callable $predicate): bool`
  Short-circuits on the first non-match. True for an empty source.
- `any(iterable $source, callable $predicate): bool`
  Short-circuits on the first match. False for an empty source.
- `chunk(iterable $source, int $size): array`
  Returns `list<list<T>>`; the last chunk may be shorter. Throws
  `InvalidArgumentException` if `$size <= 0`.
- `filter(iterable $source, callable $predicate): array`
  Returns the matching elements as a `list<T>`.
- `first(iterable $source, ?callable $predicate = null): mixed`
  First element, optionally matching `$predicate`. Throws
  `UnderflowException` if none matches.
- `firstOrNull(iterable $source, ?callable $predicate = null): mixed`
  As `first`, returning `null` instead of throwing.
- `flatMap(iterable $source, callable $mapper): array`
  `$mapper` returns an `iterable<TOut>` per element; results concatenated into
  a `list<TOut>`.
- `flatten(iterable $source): array`
  Flattens one level of `iterable<iterable<T>>` into a `list<T>`.
- `map(iterable $source, callable $mapper): array`
  Returns a `list<TOut>`, one element per source element, same order.
- `reduce(iterable $source, callable $reducer, mixed $initial): mixed`
  `@phpstan-param callable(TCarry,T,non-negative-int):TCarry $reducer`.
- `skip(iterable $source, int $count): array`
  Drops the first `$count` elements. Throws `InvalidArgumentException` if
  `$count < 0`.
- `skipWhile(iterable $source, callable $predicate): array`
  Drops leading matches, keeps the remainder including later matches.
- `take(iterable $source, int $count): array`
  Keeps at most the first `$count` elements. Throws
  `InvalidArgumentException` if `$count < 0`.
- `takeWhile(iterable $source, callable $predicate): array`
  Keeps leading matches, stops at the first non-match.
- `transpose(iterable ...$sources): array`
  Returns `list<list<mixed>>` tuples, one element per source per step,
  stopping at the shortest source. Empty when no sources are given.
- `unique(iterable $source, ?callable $keySelector = null): array`
  Keeps the first occurrence of each distinct element as a `list<T>`. When
  `$keySelector` is null, comparison uses PHP array-key coercion (the same
  rules as using the element as an array index), not `==`; on that path `T`
  must be `array-key` and a non-`array-key` element throws a native
  `TypeError`. `@phpstan-param ?callable(T):array-key $keySelector`.

### List-only operations

- `at(iterable $source, int $index): mixed`
  Element at position `$index`. A negative `$index` counts from the end, so
  `-1` is the last element and `-count($source)` the first — the semantics of
  JavaScript's `Array.prototype.at`, which PHP's `$list[$i]` does not offer.
  Throws `OutOfBoundsException` if `$index` resolves outside the list, which
  is always the case for an empty source.
- `concat(iterable ...$sources): array`
  Appends the sources end to end into one `list<T>`. Empty when no sources are
  given.
- `contains(iterable $source, mixed $value): bool`
  Strict (`===`) membership test. Short-circuits on the first match.
- `indexOf(iterable $source, mixed $value): ?int`
  Position of the first strictly equal element, or `null` if absent.
- `insertAt(iterable $source, int $index, mixed ...$values): array`
  Returns a new list with `$values` inserted before position `$index`.
  `$index === count($source)` appends. Throws `OutOfBoundsException` if
  `$index < 0` or `$index > count($source)`.
- `last(iterable $source, ?callable $predicate = null): mixed`
  Last element, optionally matching `$predicate`. Throws
  `UnderflowException` if none matches.
- `lastIndexOf(iterable $source, mixed $value): ?int`
  Position of the last strictly equal element, or `null` if absent.
- `lastOrNull(iterable $source, ?callable $predicate = null): mixed`
  As `last`, returning `null` instead of throwing.
- `orderBy(iterable $source, ?callable $comparator = null): array`
  Ascending by `<=>` when `$comparator` is null, otherwise by the comparator
  (`@phpstan-param ?callable(T,T):int $comparator`). Stable, per PHP 8.0+ sort
  guarantees. Ordering by a derived key is
  `Seq::orderBy($users, fn ($a, $b) => $a->age <=> $b->age)`; descending is
  `Seq::reverse(Seq::orderBy(...))`.
- `removeAt(iterable $source, int $index): array`
  Returns a new list with the element at `$index` removed. Throws
  `OutOfBoundsException` if `$index` is not a valid position.
- `reverse(iterable $source): array`
  Returns the elements in reverse order.
- `slice(iterable $source, int $offset, ?int $length = null): array`
  `array_slice` semantics for `$offset` (negative counts from the end) and
  `$length` (`null` means to the end; negative stops that many from the end).
  An out-of-range `$offset` yields an empty list rather than throwing, matching
  `array_slice`.

## Implementation

Each method normalizes once with `Iter::toList($source)`, then operates on the
resulting array with native functions where they fit (`array_map`,
`array_filter` + `array_values`, `array_slice`, `array_reverse`, `array_splice`
on the copy, `usort`), and a plain loop otherwise. Delegating to `Iter`'s
generators only to materialize them immediately would add generator overhead
for no benefit.

The three methods whose logic is non-trivial and already tested in `Iter` —
`flatten`, `transpose`, `unique` — delegate: `Iter::toList(Iter::unique(...))`
and so on.

Argument validation (`$size`, `$count`, `$index`) happens before any work, so
every method fails at the boundary; no lazy-wrapper split is needed since
everything here is eager.

## Error handling

- `InvalidArgumentException` — non-positive `chunk` size, negative `skip`/
  `take` count.
- `OutOfBoundsException` — `at`/`insertAt`/`removeAt` index outside the valid
  range. `at` resolves a negative index first and reports the original value in
  the message.
- `UnderflowException` — `first`/`last` with no matching element.
- `TypeError` — propagated natively from `unique` when an element is not a
  valid array key and no `$keySelector` is given.

## Testing

TDD in `tests/Collections/SeqTest.php`, mirroring `IterTest`'s style
(`#[Test]` attributes, one behaviour per test), targeting 100% coverage. Each
method is covered for: the happy path, an empty source, a non-list iterable
source (map-keyed array or Generator) proving normalization and the
positional `$index` passed to callbacks, and every documented exception.

## Out of scope

- No fluent/instance wrapper — `Seq` is stateless and static, like `Iter`. A
  chaining wrapper is a separate decision if nesting proves painful.
- No `count` — that is `Iter::count` for an `iterable`, and `\count()` for a
  materialized list.
- No `groupBy` — its result is a map keyed by the derived key, not a `list`,
  so it does not fit this class's contract. Deferred to its own decision.
- No `pad`/`fill` — no current need.
- No mutation-in-place variants (e.g. `orderByInPlace`); every method returns a
  new list.
