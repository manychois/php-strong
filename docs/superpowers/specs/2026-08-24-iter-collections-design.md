# Iter: lazy iterable utilities — design

## Goal

Provide a static utility class offering lazy, generator-based transformations
over PHP `iterable`s, plus a small set of eager terminal operations. Fills a
gap: the library currently has no general-purpose iterable-manipulation
helpers.

## Location

New problem area `src/Collections/`, tests mirrored under `tests/Collections/`.

## Class

`Manychois\PhpStrong\Collections\Iter`

- `final class Iter`, private constructor (uninstantiable, static-only).
- Implements no interface — methods are ordered alphabetically (all are
  static, so the usual static/instance split from the coding standard
  collapses to a single alphabetical list).
- Generic over `T` per-method via `@template T` PHPDoc, following the
  project's generics pattern (`@param iterable<T> $source`, etc.).

## Methods

### Lazy (generator-based, return `iterable`)

All lazy methods are implemented with `yield` and do not consume `$source`
until iterated.

- `map(iterable $source, callable $mapper): iterable`
  `@phpstan-param callable(T,int|string):TOut $mapper`, preserves keys.
- `filter(iterable $source, callable $predicate): iterable`
  `@phpstan-param callable(T,int|string):bool $predicate`, preserves keys.
- `flatMap(iterable $source, callable $mapper): iterable`
  Mapper returns an `iterable<TOut>` per element; results are concatenated,
  keys are reindexed (int keys only, since multiple sub-iterables can repeat
  keys).
- `take(iterable $source, int $count): iterable`
  Yields at most `$count` elements, preserves keys. Throws
  `InvalidArgumentException` if `$count < 0`.
- `takeWhile(iterable $source, callable $predicate): iterable`
  Stops at the first element for which the predicate is false.
- `skip(iterable $source, int $count): iterable`
  Skips the first `$count` elements, preserves keys thereafter. Throws
  `InvalidArgumentException` if `$count < 0`.
- `skipWhile(iterable $source, callable $predicate): iterable`
  Skips while the predicate is true, then yields the remainder (including
  the first failing element), preserves keys.
- `chunk(iterable $source, int $size): iterable`
  Yields `list<T>` chunks of `$size` (last chunk may be smaller). Throws
  `InvalidArgumentException` if `$size <= 0`. Reindexed int keys for the
  outer sequence.
- `flatten(iterable $source): iterable`
  One level: `$source` is an `iterable<iterable<T>>`, yields `T` reindexed
  with int keys.
- `zip(iterable ...$sources): iterable`
  Yields `list<mixed>` tuples, one element from each source per tuple, in
  source order. Stops at the shortest source (including immediately if
  `$sources` is empty, in which case it yields nothing). Reindexed int keys.
- `unique(iterable $source, ?callable $keySelector = null): iterable`
  Yields elements whose derived key (via `$keySelector`, or the element
  itself compared loosely with `==` when null) has not been seen before.
  Preserves original keys. `@phpstan-param ?callable(T):array-key $keySelector`.
- `tap(iterable $source, callable $sideEffect): iterable`
  Calls `$sideEffect($value, $key)` lazily as each element is yielded, then
  yields the element unchanged, preserving keys.

### Eager terminals (consume `$source`, return a concrete value)

- `toArray(iterable $source): array`
  Materializes preserving keys (last-write-wins on key collision, matching
  native array semantics).
- `toList(iterable $source): array`
  Materializes into a `list<T>` (reindexed int keys via `array_values`
  semantics).
- `reduce(iterable $source, callable $reducer, mixed $initial): mixed`
  `@phpstan-param callable(TCarry,T,int|string):TCarry $reducer`.
- `count(iterable $source): int`
  If `$source` is `Countable` or an `array`, uses `count()` directly;
  otherwise iterates and counts.
- `first(iterable $source, ?callable $predicate = null): mixed`
  Returns the first element (optionally matching `$predicate`). Throws
  `UnderflowException` if none found.
- `firstOrNull(iterable $source, ?callable $predicate = null): mixed`
  Same as `first`, returns `null` instead of throwing.
- `any(iterable $source, callable $predicate): bool`
  Short-circuits on first match.
- `all(iterable $source, callable $predicate): bool`
  Short-circuits on first non-match.

## Error handling

- `InvalidArgumentException` for negative `take`/`skip` counts and
  non-positive `chunk` size.
- `UnderflowException` from `first` when no element matches and no default
  is requested.
- No other validation — inputs are trusted `iterable`/`callable` per the
  project's "fail at the boundary" principle; boundaries here are the
  count/size arguments only.

## Testing

TDD, `tests/Collections/IterTest.php`, targeting 100% coverage consistent
with the rest of the codebase. Lazy methods are tested both for correct
output and for actual laziness (e.g. asserting a side-effect counter hasn't
incremented before iteration begins).

## Out of scope

- No stateful `Sequence`/object wrapper — this is a stateless static utility
  class only, matching the "no abstract base classes / composition over
  inheritance" principle without introducing a new object type.
- No parallel/eager variants of the lazy methods (e.g. no `mapEager`) —
  callers can wrap with `toArray`/`toList` themselves.
- No `sort`/`groupBy` in this pass — deferred to a future iteration if
  needed, to keep this change focused.
