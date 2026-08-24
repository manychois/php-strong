# Iter Lazy-Iterable Utilities Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add `Manychois\PhpStrong\Collections\Iter`, a final static utility class providing lazy (generator-based) and eager terminal operations over PHP `iterable`s.

**Architecture:** Single static class, one file, `#[Override]`-free (no interface implemented). All lazy methods are `yield`-based generators returning `iterable`; eager terminals consume the source and return a concrete value. Methods are added incrementally in small groups, each with its own tests, following TDD (test written and observed to fail before implementation).

**Tech Stack:** PHP 8.5+, PHPUnit, PHPStan (max level), PHPCS (this project's `phpcs.xml`).

**Spec:** `docs/superpowers/specs/2026-08-24-iter-collections-design.md`

## Global Constraints

- Namespace `Manychois\PhpStrong\Collections`; class file `src/Collections/Iter.php`; tests in `tests/Collections/IterTest.php`.
- `final class Iter` with a `private function __construct()` (uninstantiable, static-only) — this constructor needs no test since it can never be called from outside the class; PHPStan/PHPCS will not flag it as dead code because it enforces non-instantiability.
- All public methods are `static`. No interface implemented, so no `#region` blocks — methods ordered **alphabetically** for the whole class (per `docs/internal/php-coding-standard.md`, "static then instance" collapses to a flat alphabetical list when every method is static).
- Full PHPDoc on every public method: `@template T` (and `@template TOut`/`TCarry` where relevant), `@param`/`@return`, `@phpstan-param` for precise callable signatures, `@throws` where applicable. One blank line between different annotation types.
- `declare(strict_types=1);` at the top of every new PHP file.
- Global imports: none needed beyond what's in the `Manychois\PhpStrong\Collections` namespace itself; `InvalidArgumentException` and `UnderflowException` are in the PHP global namespace, imported with a plain `use InvalidArgumentException;` / `use UnderflowException;` (no alias — they aren't `XxxInterface` types).
- Quality gates run at the end of **every** task, in order: `composer phpcbf` → `composer phpcs` → `composer phpstan` → `composer test`. All four must be clean before that task's commit.
- Target 100% line/branch coverage on `src/Collections/Iter.php` by the end of Task 9 (checked via `composer test`, which runs PHPUnit with `XDEBUG_MODE=coverage`).

---

## File Structure

- **Create `src/Collections/Iter.php`** — the whole class, built up incrementally across tasks (each task adds one method group).
- **Create `tests/Collections/IterTest.php`** — one test class, built up incrementally in step with the source file, one `#[Test]`-per-behavior style matching existing test files in this repo (check `tests/Time/` or `tests/Events/` for the exact PHPUnit attribute style used before writing Task 1's tests).

Both files are touched by every task in this plan; there is no other file structure decomposition to make.

---

## Task 1: Class scaffold + `map`, `filter`, `tap`

**Files:**
- Create: `src/Collections/Iter.php`
- Test: `tests/Collections/IterTest.php`

**Interfaces:**
- Consumes: nothing (first task).
- Produces:
  - `Iter::map(iterable $source, callable $mapper): iterable` — `@phpstan-param callable(T,int|string):TOut $mapper`, preserves keys.
  - `Iter::filter(iterable $source, callable $predicate): iterable` — `@phpstan-param callable(T,int|string):bool $predicate`, preserves keys.
  - `Iter::tap(iterable $source, callable $sideEffect): iterable` — `@phpstan-param callable(T,int|string):void $sideEffect`, preserves keys, calls side effect lazily per element before yielding it.

- [ ] **Step 1: Check existing test file conventions**

Read one existing small test file, e.g. `tests/Time/` or `tests/Events/`, to confirm the exact PHPUnit attribute style (`#[Test]` vs `test` method prefix), namespace declaration, and assertion style used in this repo. Match it exactly in `IterTest.php`.

- [ ] **Step 2: Write failing tests for `map`, `filter`, `tap`**

Create `tests/Collections/IterTest.php`:

```php
<?php

declare(strict_types=1);

namespace Manychois\PhpStrongTests\Collections;

use Manychois\PhpStrong\Collections\Iter;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class IterTest extends TestCase
{
    #[Test]
    public function mapTransformsEachElementPreservingKeys(): void
    {
        $source = ['a' => 1, 'b' => 2, 'c' => 3];
        $result = Iter::map($source, static fn (int $v): int => $v * 10);

        static::assertSame(['a' => 10, 'b' => 20, 'c' => 30], iterator_to_array($result));
    }

    #[Test]
    public function mapIsLazy(): void
    {
        $calls = 0;
        $source = [1, 2, 3];
        $result = Iter::map($source, static function (int $v) use (&$calls): int {
            $calls++;

            return $v;
        });

        static::assertSame(0, $calls);
        iterator_to_array($result);
        static::assertSame(3, $calls);
    }

    #[Test]
    public function filterKeepsOnlyMatchingElementsPreservingKeys(): void
    {
        $source = ['a' => 1, 'b' => 2, 'c' => 3, 'd' => 4];
        $result = Iter::filter($source, static fn (int $v): bool => $v % 2 === 0);

        static::assertSame(['b' => 2, 'd' => 4], iterator_to_array($result));
    }

    #[Test]
    public function filterIsLazy(): void
    {
        $calls = 0;
        $source = [1, 2, 3];
        $result = Iter::filter($source, static function (int $v) use (&$calls): bool {
            $calls++;

            return true;
        });

        static::assertSame(0, $calls);
        iterator_to_array($result);
        static::assertSame(3, $calls);
    }

    #[Test]
    public function tapCallsSideEffectAndYieldsElementsUnchanged(): void
    {
        $seen = [];
        $source = ['a' => 1, 'b' => 2];
        $result = Iter::tap($source, static function (int $v, int|string $k) use (&$seen): void {
            $seen[] = [$k, $v];
        });

        static::assertSame(['a' => 1, 'b' => 2], iterator_to_array($result));
        static::assertSame([['a', 1], ['b', 2]], $seen);
    }

    #[Test]
    public function tapIsLazy(): void
    {
        $calls = 0;
        $source = [1, 2];
        $result = Iter::tap($source, static function () use (&$calls): void {
            $calls++;
        });

        static::assertSame(0, $calls);
        iterator_to_array($result);
        static::assertSame(2, $calls);
    }
}
```

- [ ] **Step 3: Run tests to verify they fail**

Run: `./vendor/bin/phpunit tests/Collections/IterTest.php`
Expected: FAIL — class `Manychois\PhpStrong\Collections\Iter` not found.

- [ ] **Step 4: Implement the class scaffold with `map`, `filter`, `tap`**

Create `src/Collections/Iter.php`:

```php
<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\Collections;

/**
 * Provides lazy, generator-based utilities for manipulating iterables.
 */
final class Iter
{
    private function __construct()
    {
    }

    /**
     * Lazily filters elements of an iterable, keeping only those matching the predicate.
     *
     * @param iterable $source    The source iterable.
     * @param callable $predicate The predicate to test each element.
     *
     * @return iterable The filtered elements, preserving original keys.
     *
     * @template T
     *
     * @phpstan-param iterable<int|string,T> $source
     * @phpstan-param callable(T,int|string):bool $predicate
     * @phpstan-return iterable<int|string,T>
     */
    public static function filter(iterable $source, callable $predicate): iterable
    {
        foreach ($source as $key => $value) {
            if ($predicate($value, $key)) {
                yield $key => $value;
            }
        }
    }

    /**
     * Lazily transforms each element of an iterable.
     *
     * @param iterable $source The source iterable.
     * @param callable $mapper The mapper applied to each element.
     *
     * @return iterable The transformed elements, preserving original keys.
     *
     * @template T
     * @template TOut
     *
     * @phpstan-param iterable<int|string,T> $source
     * @phpstan-param callable(T,int|string):TOut $mapper
     * @phpstan-return iterable<int|string,TOut>
     */
    public static function map(iterable $source, callable $mapper): iterable
    {
        foreach ($source as $key => $value) {
            yield $key => $mapper($value, $key);
        }
    }

    /**
     * Lazily invokes a side effect for each element, yielding it unchanged.
     *
     * @param iterable $source     The source iterable.
     * @param callable $sideEffect The side effect invoked with each element.
     *
     * @return iterable The original elements, preserving original keys.
     *
     * @template T
     *
     * @phpstan-param iterable<int|string,T> $source
     * @phpstan-param callable(T,int|string):void $sideEffect
     * @phpstan-return iterable<int|string,T>
     */
    public static function tap(iterable $source, callable $sideEffect): iterable
    {
        foreach ($source as $key => $value) {
            $sideEffect($value, $key);

            yield $key => $value;
        }
    }
}
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `./vendor/bin/phpunit tests/Collections/IterTest.php`
Expected: PASS (6 tests).

- [ ] **Step 6: Run quality gates**

Run in order: `composer phpcbf`, `composer phpcs`, `composer phpstan`, `composer test`.
Fix anything reported (e.g. PHPDoc spacing, method ordering) before proceeding. `map`/`filter`/`tap` are already alphabetical.

- [ ] **Step 7: Commit**

```bash
git add src/Collections/Iter.php tests/Collections/IterTest.php
git commit -m "feat(collections): add Iter::map, Iter::filter, Iter::tap"
```

---

## Task 2: `flatMap`, `take`, `skip`

**Files:**
- Modify: `src/Collections/Iter.php`
- Test: `tests/Collections/IterTest.php`

**Interfaces:**
- Consumes: nothing new from Task 1's methods directly (independent additions to the same class).
- Produces:
  - `Iter::flatMap(iterable $source, callable $mapper): iterable` — `@phpstan-param callable(T,int|string):iterable<TOut> $mapper`, reindexes with int keys.
  - `Iter::take(iterable $source, int $count): iterable` — preserves keys; throws `InvalidArgumentException` if `$count < 0`.
  - `Iter::skip(iterable $source, int $count): iterable` — preserves keys; throws `InvalidArgumentException` if `$count < 0`.

- [ ] **Step 1: Write failing tests**

Add to `IterTest.php` (inside the class, methods placed alphabetically is not required in tests — append at the end):

```php
    #[Test]
    public function flatMapConcatenatesMappedIterablesWithReindexedKeys(): void
    {
        $source = [1, 2];
        $result = Iter::flatMap($source, static fn (int $v): array => [$v, $v * 10]);

        static::assertSame([1, 10, 2, 20], iterator_to_array($result));
    }

    #[Test]
    public function flatMapIsLazy(): void
    {
        $calls = 0;
        $source = [1, 2];
        $result = Iter::flatMap($source, static function (int $v) use (&$calls): array {
            $calls++;

            return [$v];
        });

        static::assertSame(0, $calls);
        iterator_to_array($result);
        static::assertSame(2, $calls);
    }

    #[Test]
    public function takeYieldsAtMostCountElementsPreservingKeys(): void
    {
        $source = ['a' => 1, 'b' => 2, 'c' => 3];
        $result = Iter::take($source, 2);

        static::assertSame(['a' => 1, 'b' => 2], iterator_to_array($result));
    }

    #[Test]
    public function takeYieldsFewerElementsIfSourceIsShorter(): void
    {
        $source = [1, 2];
        $result = Iter::take($source, 5);

        static::assertSame([1, 2], iterator_to_array($result));
    }

    #[Test]
    public function takeThrowsForNegativeCount(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        iterator_to_array(Iter::take([1, 2], -1));
    }

    #[Test]
    public function takeDoesNotIterateSourceBeyondCount(): void
    {
        $calls = 0;
        $source = (static function () use (&$calls): \Generator {
            while (true) {
                $calls++;

                yield $calls;
            }
        })();

        $result = iterator_to_array(Iter::take($source, 3));

        static::assertSame([1, 2, 3], $result);
        static::assertSame(3, $calls);
    }

    #[Test]
    public function skipOmitsFirstCountElementsPreservingKeys(): void
    {
        $source = ['a' => 1, 'b' => 2, 'c' => 3];
        $result = Iter::skip($source, 1);

        static::assertSame(['b' => 2, 'c' => 3], iterator_to_array($result));
    }

    #[Test]
    public function skipYieldsNothingIfCountExceedsSourceLength(): void
    {
        $source = [1, 2];
        $result = Iter::skip($source, 5);

        static::assertSame([], iterator_to_array($result));
    }

    #[Test]
    public function skipThrowsForNegativeCount(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        iterator_to_array(Iter::skip([1, 2], -1));
    }
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `./vendor/bin/phpunit tests/Collections/IterTest.php`
Expected: FAIL — `Iter::flatMap`/`take`/`skip` do not exist.

- [ ] **Step 3: Implement `flatMap`, `take`, `skip`**

Add to `src/Collections/Iter.php`, keeping the whole class alphabetically ordered (`filter`, `flatMap`, `map`, `skip`, `take`, `tap`):

```php
    /**
     * Lazily maps each element to an iterable and concatenates the results.
     *
     * @param iterable $source The source iterable.
     * @param callable $mapper The mapper producing an iterable per element.
     *
     * @return iterable The concatenated results, reindexed with integer keys.
     *
     * @template T
     * @template TOut
     *
     * @phpstan-param iterable<int|string,T> $source
     * @phpstan-param callable(T,int|string):iterable<TOut> $mapper
     * @phpstan-return iterable<int,TOut>
     */
    public static function flatMap(iterable $source, callable $mapper): iterable
    {
        foreach ($source as $key => $value) {
            foreach ($mapper($value, $key) as $inner) {
                yield $inner;
            }
        }
    }
```

Insert `skip` before `take` alphabetically:

```php
    /**
     * Lazily skips the first N elements of an iterable.
     *
     * @param iterable $source The source iterable.
     * @param int      $count  The number of elements to skip.
     *
     * @return iterable The remaining elements, preserving original keys.
     *
     * @throws InvalidArgumentException if $count is negative.
     *
     * @template T
     *
     * @phpstan-param iterable<int|string,T> $source
     * @phpstan-param non-negative-int $count
     * @phpstan-return iterable<int|string,T>
     */
    public static function skip(iterable $source, int $count): iterable
    {
        if ($count < 0) {
            throw new InvalidArgumentException('Count must not be negative.');
        }

        $index = 0;
        foreach ($source as $key => $value) {
            if ($index >= $count) {
                yield $key => $value;
            }
            $index++;
        }
    }

    /**
     * Lazily takes at most N elements of an iterable.
     *
     * @param iterable $source The source iterable.
     * @param int      $count  The maximum number of elements to take.
     *
     * @return iterable At most $count elements, preserving original keys.
     *
     * @throws InvalidArgumentException if $count is negative.
     *
     * @template T
     *
     * @phpstan-param iterable<int|string,T> $source
     * @phpstan-param non-negative-int $count
     * @phpstan-return iterable<int|string,T>
     */
    public static function take(iterable $source, int $count): iterable
    {
        if ($count < 0) {
            throw new InvalidArgumentException('Count must not be negative.');
        }

        if ($count === 0) {
            return;
        }

        $index = 0;
        foreach ($source as $key => $value) {
            yield $key => $value;
            $index++;
            if ($index >= $count) {
                break;
            }
        }
    }
```

Add the import near the top of the file (alphabetical with any existing use statements — currently none needed besides this):

```php
use InvalidArgumentException;
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `./vendor/bin/phpunit tests/Collections/IterTest.php`
Expected: PASS.

- [ ] **Step 5: Run quality gates**

Run: `composer phpcbf`, `composer phpcs`, `composer phpstan`, `composer test`. Fix any reported issues.

- [ ] **Step 6: Commit**

```bash
git add src/Collections/Iter.php tests/Collections/IterTest.php
git commit -m "feat(collections): add Iter::flatMap, Iter::take, Iter::skip"
```

---

## Task 3: `takeWhile`, `skipWhile`

**Files:**
- Modify: `src/Collections/Iter.php`
- Test: `tests/Collections/IterTest.php`

**Interfaces:**
- Consumes: nothing new.
- Produces:
  - `Iter::takeWhile(iterable $source, callable $predicate): iterable` — stops before the first element for which predicate is false; preserves keys.
  - `Iter::skipWhile(iterable $source, callable $predicate): iterable` — skips while predicate is true, then yields the remainder (including the first failing element); preserves keys.

- [ ] **Step 1: Write failing tests**

Append to `IterTest.php`:

```php
    #[Test]
    public function takeWhileStopsBeforeFirstNonMatchingElement(): void
    {
        $source = ['a' => 1, 'b' => 2, 'c' => 5, 'd' => 1];
        $result = Iter::takeWhile($source, static fn (int $v): bool => $v < 3);

        static::assertSame(['a' => 1, 'b' => 2], iterator_to_array($result));
    }

    #[Test]
    public function takeWhileYieldsNothingIfFirstElementFails(): void
    {
        $source = [5, 1, 2];
        $result = Iter::takeWhile($source, static fn (int $v): bool => $v < 3);

        static::assertSame([], iterator_to_array($result));
    }

    #[Test]
    public function skipWhileSkipsLeadingMatchesThenYieldsRemainder(): void
    {
        $source = ['a' => 1, 'b' => 2, 'c' => 5, 'd' => 1];
        $result = Iter::skipWhile($source, static fn (int $v): bool => $v < 3);

        static::assertSame(['c' => 5, 'd' => 1], iterator_to_array($result));
    }

    #[Test]
    public function skipWhileYieldsEverythingIfFirstElementFails(): void
    {
        $source = [5, 1, 2];
        $result = Iter::skipWhile($source, static fn (int $v): bool => $v < 3);

        static::assertSame([5, 1, 2], iterator_to_array($result));
    }
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `./vendor/bin/phpunit tests/Collections/IterTest.php`
Expected: FAIL — `Iter::takeWhile`/`skipWhile` do not exist.

- [ ] **Step 3: Implement `takeWhile`, `skipWhile`**

Insert alphabetically (`skip`, `skipWhile`, `take`, `takeWhile`, `tap`):

```php
    /**
     * Lazily skips elements while the predicate holds, then yields the remainder.
     *
     * @param iterable $source    The source iterable.
     * @param callable $predicate The predicate tested against leading elements.
     *
     * @return iterable The remainder starting from the first non-matching element, preserving keys.
     *
     * @template T
     *
     * @phpstan-param iterable<int|string,T> $source
     * @phpstan-param callable(T,int|string):bool $predicate
     * @phpstan-return iterable<int|string,T>
     */
    public static function skipWhile(iterable $source, callable $predicate): iterable
    {
        $skipping = true;
        foreach ($source as $key => $value) {
            if ($skipping && $predicate($value, $key)) {
                continue;
            }
            $skipping = false;

            yield $key => $value;
        }
    }
```

```php
    /**
     * Lazily takes elements while the predicate holds, stopping at the first non-matching element.
     *
     * @param iterable $source    The source iterable.
     * @param callable $predicate The predicate tested against each element.
     *
     * @return iterable The leading matching elements, preserving keys.
     *
     * @template T
     *
     * @phpstan-param iterable<int|string,T> $source
     * @phpstan-param callable(T,int|string):bool $predicate
     * @phpstan-return iterable<int|string,T>
     */
    public static function takeWhile(iterable $source, callable $predicate): iterable
    {
        foreach ($source as $key => $value) {
            if (!$predicate($value, $key)) {
                break;
            }

            yield $key => $value;
        }
    }
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `./vendor/bin/phpunit tests/Collections/IterTest.php`
Expected: PASS.

- [ ] **Step 5: Run quality gates**

Run: `composer phpcbf`, `composer phpcs`, `composer phpstan`, `composer test`. Fix any reported issues.

- [ ] **Step 6: Commit**

```bash
git add src/Collections/Iter.php tests/Collections/IterTest.php
git commit -m "feat(collections): add Iter::takeWhile, Iter::skipWhile"
```

---

## Task 4: `chunk`, `flatten`, `zip`

**Files:**
- Modify: `src/Collections/Iter.php`
- Test: `tests/Collections/IterTest.php`

**Interfaces:**
- Consumes: nothing new.
- Produces:
  - `Iter::chunk(iterable $source, int $size): iterable` — yields `list<T>` chunks of `$size` (last may be smaller), reindexed int outer keys; throws `InvalidArgumentException` if `$size <= 0`.
  - `Iter::flatten(iterable $source): iterable` — one level, `iterable<iterable<T>>` → `iterable<int,T>`, reindexed int keys.
  - `Iter::zip(iterable ...$sources): iterable` — yields `list<mixed>` tuples, stops at shortest source, empty if `$sources` is empty; reindexed int keys.

- [ ] **Step 1: Write failing tests**

Append to `IterTest.php`:

```php
    #[Test]
    public function chunkGroupsElementsIntoListsOfGivenSize(): void
    {
        $source = [1, 2, 3, 4, 5];
        $result = Iter::chunk($source, 2);

        static::assertSame([[1, 2], [3, 4], [5]], iterator_to_array($result));
    }

    #[Test]
    public function chunkThrowsForNonPositiveSize(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        iterator_to_array(Iter::chunk([1, 2], 0));
    }

    #[Test]
    public function flattenConcatenatesOneLevelWithReindexedKeys(): void
    {
        $source = [[1, 2], ['a' => 3], [4]];
        $result = Iter::flatten($source);

        static::assertSame([1, 2, 3, 4], iterator_to_array($result));
    }

    #[Test]
    public function zipYieldsTuplesStoppingAtShortestSource(): void
    {
        $result = Iter::zip([1, 2, 3], ['a', 'b']);

        static::assertSame([[1, 'a'], [2, 'b']], iterator_to_array($result));
    }

    #[Test]
    public function zipYieldsNothingWithNoSources(): void
    {
        $result = Iter::zip();

        static::assertSame([], iterator_to_array($result));
    }
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `./vendor/bin/phpunit tests/Collections/IterTest.php`
Expected: FAIL — `Iter::chunk`/`flatten`/`zip` do not exist.

- [ ] **Step 3: Implement `chunk`, `flatten`, `zip`**

Insert alphabetically (`chunk` before `filter`; `flatMap`, `flatten` after `filter`; `zip` last):

```php
    /**
     * Lazily groups elements of an iterable into fixed-size lists.
     *
     * @param iterable $source The source iterable.
     * @param int      $size   The maximum size of each chunk.
     *
     * @return iterable The chunks, each a list of at most $size elements, with reindexed integer keys.
     *
     * @throws InvalidArgumentException if $size is not positive.
     *
     * @template T
     *
     * @phpstan-param iterable<int|string,T> $source
     * @phpstan-param positive-int $size
     * @phpstan-return iterable<int,list<T>>
     */
    public static function chunk(iterable $source, int $size): iterable
    {
        if ($size <= 0) {
            throw new InvalidArgumentException('Size must be positive.');
        }

        $buffer = [];
        foreach ($source as $value) {
            $buffer[] = $value;
            if (\count($buffer) >= $size) {
                yield $buffer;
                $buffer = [];
            }
        }
        if (\count($buffer) > 0) {
            yield $buffer;
        }
    }
```

```php
    /**
     * Lazily flattens one level of a nested iterable.
     *
     * @param iterable $source The source iterable of iterables.
     *
     * @return iterable The concatenated inner elements, with reindexed integer keys.
     *
     * @template T
     *
     * @phpstan-param iterable<int|string,iterable<int|string,T>> $source
     * @phpstan-return iterable<int,T>
     */
    public static function flatten(iterable $source): iterable
    {
        foreach ($source as $inner) {
            foreach ($inner as $value) {
                yield $value;
            }
        }
    }
```

```php
    /**
     * Lazily combines multiple iterables into tuples, stopping at the shortest one.
     *
     * @param iterable ...$sources The source iterables to zip together.
     *
     * @return iterable The tuples, each a list with one element per source, with reindexed integer keys.
     *
     * @phpstan-param iterable<mixed> ...$sources
     * @phpstan-return iterable<int,list<mixed>>
     */
    public static function zip(iterable ...$sources): iterable
    {
        if (\count($sources) === 0) {
            return;
        }

        $iterators = [];
        foreach ($sources as $source) {
            $iterator = self::toGenerator($source);
            $iterator->rewind();
            $iterators[] = $iterator;
        }

        while (true) {
            $tuple = [];
            foreach ($iterators as $iterator) {
                if (!$iterator->valid()) {
                    return;
                }
                $tuple[] = $iterator->current();
            }
            yield $tuple;
            foreach ($iterators as $iterator) {
                $iterator->next();
            }
        }
    }
```

`zip` needs a small private helper to normalize any `iterable` into a rewindable `Generator`-like cursor (plain arrays and `Iterator`s both work with `Iterator`-style `rewind`/`valid`/`current`/`next`, but a bare `Generator` from another source is not rewindable after use, and an `IteratorAggregate` is not directly usable this way). Add this private static helper, placed at the end of the class per the coding standard's `private` ordering:

```php
    /**
     * Normalizes an iterable into a rewindable iterator.
     *
     * @param iterable $source The iterable to normalize.
     *
     * @return Iterator The normalized iterator.
     *
     * @template T
     *
     * @phpstan-param iterable<int|string,T> $source
     * @phpstan-return Iterator<int|string,T>
     */
    private static function toGenerator(iterable $source): Iterator
    {
        if ($source instanceof Iterator) {
            return $source;
        }
        if ($source instanceof IteratorAggregate) {
            return self::toGenerator($source->getIterator());
        }

        return new ArrayIterator($source);
    }
```

Add imports (alphabetical, near existing `use InvalidArgumentException;`):

```php
use ArrayIterator;
use Iterator;
use IteratorAggregate;
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `./vendor/bin/phpunit tests/Collections/IterTest.php`
Expected: PASS.

- [ ] **Step 5: Run quality gates**

Run: `composer phpcbf`, `composer phpcs`, `composer phpstan`, `composer test`. Fix any reported issues (PHPStan may need `@phpstan-return` refinement on `toGenerator` — adjust if it flags the `Iterator` invariance).

- [ ] **Step 6: Commit**

```bash
git add src/Collections/Iter.php tests/Collections/IterTest.php
git commit -m "feat(collections): add Iter::chunk, Iter::flatten, Iter::zip"
```

---

## Task 5: `unique`

**Files:**
- Modify: `src/Collections/Iter.php`
- Test: `tests/Collections/IterTest.php`

**Interfaces:**
- Consumes: nothing new.
- Produces: `Iter::unique(iterable $source, ?callable $keySelector = null): iterable` — yields elements whose derived key (via `$keySelector`, or the element itself when null, compared by identity in an internal set) has not been seen before; preserves original keys.

- [ ] **Step 1: Write failing tests**

Append to `IterTest.php`:

```php
    #[Test]
    public function uniqueYieldsFirstOccurrenceOfEachElementByDefault(): void
    {
        $source = ['a' => 1, 'b' => 2, 'c' => 1, 'd' => 3];
        $result = Iter::unique($source);

        static::assertSame(['a' => 1, 'b' => 2, 'd' => 3], iterator_to_array($result));
    }

    #[Test]
    public function uniqueYieldsFirstOccurrenceByKeySelector(): void
    {
        $source = ['a' => [1, 'x'], 'b' => [2, 'x'], 'c' => [3, 'y']];
        $result = Iter::unique($source, static fn (array $v): string => $v[1]);

        static::assertSame(['a' => [1, 'x'], 'c' => [3, 'y']], iterator_to_array($result));
    }
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `./vendor/bin/phpunit tests/Collections/IterTest.php`
Expected: FAIL — `Iter::unique` does not exist.

- [ ] **Step 3: Implement `unique`**

Insert alphabetically (after `toGenerator`'s spot is private/last; `unique` goes after `takeWhile`/`tap`, before `zip`, in the public alphabetical ordering — public method order is `chunk, filter, flatMap, flatten, map, skip, skipWhile, take, takeWhile, tap, unique, zip`, with `toGenerator` (private) placed after all public methods per the standard's public-before-private ordering):

```php
    /**
     * Lazily yields only the first occurrence of each distinct element.
     *
     * @param iterable      $source      The source iterable.
     * @param ?callable     $keySelector The selector deriving a comparison key from each element; defaults to the
     *                                   element itself.
     *
     * @return iterable The first-seen elements for each distinct key, preserving original keys.
     *
     * @template T
     *
     * @phpstan-param iterable<int|string,T> $source
     * @phpstan-param ?callable(T):array-key $keySelector
     * @phpstan-return iterable<int|string,T>
     */
    public static function unique(iterable $source, ?callable $keySelector = null): iterable
    {
        $seen = [];
        foreach ($source as $key => $value) {
            $uniqueKey = $keySelector === null ? $value : $keySelector($value);
            if (\array_key_exists($uniqueKey, $seen)) {
                continue;
            }
            $seen[$uniqueKey] = true;

            yield $key => $value;
        }
    }
```

Note: without a `$keySelector`, `$value` itself must be a valid PHP array key (`int|string`); this matches the spec's documented default of comparing the element itself and keeps the implementation a single simple path. If callers pass non-scalar elements without a `$keySelector`, PHP will throw a native `TypeError` on the array key — this is acceptable per the "fail at the boundary" principle and does not need special handling here.

- [ ] **Step 4: Run tests to verify they pass**

Run: `./vendor/bin/phpunit tests/Collections/IterTest.php`
Expected: PASS.

- [ ] **Step 5: Run quality gates**

Run: `composer phpcbf`, `composer phpcs`, `composer phpstan`, `composer test`. Fix any reported issues.

- [ ] **Step 6: Commit**

```bash
git add src/Collections/Iter.php tests/Collections/IterTest.php
git commit -m "feat(collections): add Iter::unique"
```

---

## Task 6: `toArray`, `toList`

**Files:**
- Modify: `src/Collections/Iter.php`
- Test: `tests/Collections/IterTest.php`

**Interfaces:**
- Consumes: nothing new.
- Produces:
  - `Iter::toArray(iterable $source): array` — materializes preserving keys (last-write-wins on collision).
  - `Iter::toList(iterable $source): array` — materializes into a reindexed `list<T>`.

- [ ] **Step 1: Write failing tests**

Append to `IterTest.php`:

```php
    #[Test]
    public function toArrayPreservesKeysWithLastWriteWinningOnCollision(): void
    {
        $source = (static function (): \Generator {
            yield 'a' => 1;
            yield 'a' => 2;
            yield 'b' => 3;
        })();

        static::assertSame(['a' => 2, 'b' => 3], Iter::toArray($source));
    }

    #[Test]
    public function toListReindexesElements(): void
    {
        $source = ['a' => 1, 'b' => 2, 'c' => 3];

        static::assertSame([1, 2, 3], Iter::toList($source));
    }
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `./vendor/bin/phpunit tests/Collections/IterTest.php`
Expected: FAIL — `Iter::toArray`/`toList` do not exist.

- [ ] **Step 3: Implement `toArray`, `toList`**

Insert alphabetically (`toArray`, `toGenerator` stays private/last, `toList` right after `toArray`, both before `unique`):

```php
    /**
     * Eagerly materializes an iterable into an array, preserving keys.
     *
     * @param iterable $source The source iterable.
     *
     * @return array The materialized elements. On key collision, the last value wins.
     *
     * @template T
     *
     * @phpstan-param iterable<int|string,T> $source
     * @phpstan-return array<int|string,T>
     */
    public static function toArray(iterable $source): array
    {
        $result = [];
        foreach ($source as $key => $value) {
            $result[$key] = $value;
        }

        return $result;
    }

    /**
     * Eagerly materializes an iterable into a reindexed list.
     *
     * @param iterable $source The source iterable.
     *
     * @return array The materialized elements, reindexed starting from 0.
     *
     * @template T
     *
     * @phpstan-param iterable<int|string,T> $source
     * @phpstan-return list<T>
     */
    public static function toList(iterable $source): array
    {
        $result = [];
        foreach ($source as $value) {
            $result[] = $value;
        }

        return $result;
    }
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `./vendor/bin/phpunit tests/Collections/IterTest.php`
Expected: PASS.

- [ ] **Step 5: Run quality gates**

Run: `composer phpcbf`, `composer phpcs`, `composer phpstan`, `composer test`. Fix any reported issues.

- [ ] **Step 6: Commit**

```bash
git add src/Collections/Iter.php tests/Collections/IterTest.php
git commit -m "feat(collections): add Iter::toArray, Iter::toList"
```

---

## Task 7: `reduce`, `count`

**Files:**
- Modify: `src/Collections/Iter.php`
- Test: `tests/Collections/IterTest.php`

**Interfaces:**
- Consumes: nothing new.
- Produces:
  - `Iter::reduce(iterable $source, callable $reducer, mixed $initial): mixed` — `@phpstan-param callable(TCarry,T,int|string):TCarry $reducer`.
  - `Iter::count(iterable $source): int` — uses native `count()` when `$source` is `array`/`Countable`, otherwise iterates.

- [ ] **Step 1: Write failing tests**

Append to `IterTest.php`:

```php
    #[Test]
    public function reduceAccumulatesFromInitialValue(): void
    {
        $source = [1, 2, 3, 4];
        $result = Iter::reduce($source, static fn (int $carry, int $v): int => $carry + $v, 0);

        static::assertSame(10, $result);
    }

    #[Test]
    public function countReturnsElementCountForArray(): void
    {
        static::assertSame(3, Iter::count([1, 2, 3]));
    }

    #[Test]
    public function countReturnsElementCountForGenerator(): void
    {
        $source = (static function (): \Generator {
            yield 1;
            yield 2;
        })();

        static::assertSame(2, Iter::count($source));
    }

    #[Test]
    public function countReturnsElementCountForCountable(): void
    {
        $countable = new \ArrayObject([1, 2, 3, 4]);

        static::assertSame(4, Iter::count($countable));
    }
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `./vendor/bin/phpunit tests/Collections/IterTest.php`
Expected: FAIL — `Iter::reduce`/`count` do not exist.

- [ ] **Step 3: Implement `reduce`, `count`**

Insert alphabetically (`count` first in the whole class since it now sorts before `chunk`... verify: alphabetically `count` < `chunk`? 'c','o' vs 'c','h' — "ch" < "co", so `chunk` comes before `count`. Final public order: `chunk, count, filter, flatMap, flatten, map, reduce, skip, skipWhile, take, takeWhile, tap, toArray, toList, unique, zip`):

```php
    /**
     * Eagerly counts the elements of an iterable.
     *
     * @param iterable $source The source iterable.
     *
     * @return int The number of elements.
     *
     * @phpstan-param iterable<mixed> $source
     * @phpstan-return non-negative-int
     */
    public static function count(iterable $source): int
    {
        if (\is_array($source) || $source instanceof Countable) {
            return \count($source);
        }

        $count = 0;
        foreach ($source as $ignored) {
            $count++;
        }

        return $count;
    }
```

```php
    /**
     * Eagerly reduces an iterable to a single value.
     *
     * @param iterable $source  The source iterable.
     * @param callable $reducer The reducer combining the running value with each element.
     * @param mixed    $initial The initial accumulator value.
     *
     * @return mixed The final accumulated value.
     *
     * @template T
     * @template TCarry
     *
     * @phpstan-param iterable<int|string,T> $source
     * @phpstan-param callable(TCarry,T,int|string):TCarry $reducer
     * @phpstan-param TCarry $initial
     * @phpstan-return TCarry
     */
    public static function reduce(iterable $source, callable $reducer, mixed $initial): mixed
    {
        $carry = $initial;
        foreach ($source as $key => $value) {
            $carry = $reducer($carry, $value, $key);
        }

        return $carry;
    }
```

Add import: `use Countable;` (alphabetical among existing imports).

- [ ] **Step 4: Run tests to verify they pass**

Run: `./vendor/bin/phpunit tests/Collections/IterTest.php`
Expected: PASS.

- [ ] **Step 5: Run quality gates**

Run: `composer phpcbf`, `composer phpcs`, `composer phpstan`, `composer test`. Fix any reported issues.

- [ ] **Step 6: Commit**

```bash
git add src/Collections/Iter.php tests/Collections/IterTest.php
git commit -m "feat(collections): add Iter::reduce, Iter::count"
```

---

## Task 8: `first`, `firstOrNull`

**Files:**
- Modify: `src/Collections/Iter.php`
- Test: `tests/Collections/IterTest.php`

**Interfaces:**
- Consumes: nothing new.
- Produces:
  - `Iter::first(iterable $source, ?callable $predicate = null): mixed` — throws `UnderflowException` if no element matches (or source is empty when `$predicate` is null).
  - `Iter::firstOrNull(iterable $source, ?callable $predicate = null): mixed` — same, returns `null` instead of throwing.

- [ ] **Step 1: Write failing tests**

Append to `IterTest.php`:

```php
    #[Test]
    public function firstReturnsFirstElementWithoutPredicate(): void
    {
        static::assertSame(1, Iter::first([1, 2, 3]));
    }

    #[Test]
    public function firstReturnsFirstMatchingElement(): void
    {
        $result = Iter::first([1, 2, 3, 4], static fn (int $v): bool => $v % 2 === 0);

        static::assertSame(2, $result);
    }

    #[Test]
    public function firstThrowsWhenSourceIsEmpty(): void
    {
        $this->expectException(\UnderflowException::class);

        Iter::first([]);
    }

    #[Test]
    public function firstThrowsWhenNoElementMatches(): void
    {
        $this->expectException(\UnderflowException::class);

        Iter::first([1, 3, 5], static fn (int $v): bool => $v % 2 === 0);
    }

    #[Test]
    public function firstOrNullReturnsNullWhenSourceIsEmpty(): void
    {
        static::assertNull(Iter::firstOrNull([]));
    }

    #[Test]
    public function firstOrNullReturnsNullWhenNoElementMatches(): void
    {
        $result = Iter::firstOrNull([1, 3, 5], static fn (int $v): bool => $v % 2 === 0);

        static::assertNull($result);
    }

    #[Test]
    public function firstOrNullReturnsFirstMatchingElement(): void
    {
        $result = Iter::firstOrNull([1, 2, 3, 4], static fn (int $v): bool => $v % 2 === 0);

        static::assertSame(2, $result);
    }
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `./vendor/bin/phpunit tests/Collections/IterTest.php`
Expected: FAIL — `Iter::first`/`firstOrNull` do not exist.

- [ ] **Step 3: Implement `first`, `firstOrNull`**

Insert alphabetically. Full public order now: `chunk, count, filter, first, firstOrNull, flatMap, flatten, map, reduce, skip, skipWhile, take, takeWhile, tap, toArray, toList, unique, zip`.

```php
    /**
     * Eagerly returns the first element, optionally matching a predicate.
     *
     * @param iterable  $source    The source iterable.
     * @param ?callable $predicate The predicate the returned element must match; defaults to matching any element.
     *
     * @return mixed The first matching element.
     *
     * @throws UnderflowException if no element matches.
     *
     * @template T
     *
     * @phpstan-param iterable<int|string,T> $source
     * @phpstan-param ?callable(T,int|string):bool $predicate
     * @phpstan-return T
     */
    public static function first(iterable $source, ?callable $predicate = null): mixed
    {
        foreach ($source as $key => $value) {
            if ($predicate === null || $predicate($value, $key)) {
                return $value;
            }
        }

        throw new UnderflowException('No matching element found.');
    }

    /**
     * Eagerly returns the first element, optionally matching a predicate, or null if none is found.
     *
     * @param iterable  $source    The source iterable.
     * @param ?callable $predicate The predicate the returned element must match; defaults to matching any element.
     *
     * @return mixed The first matching element, or null if none is found.
     *
     * @template T
     *
     * @phpstan-param iterable<int|string,T> $source
     * @phpstan-param ?callable(T,int|string):bool $predicate
     * @phpstan-return ?T
     */
    public static function firstOrNull(iterable $source, ?callable $predicate = null): mixed
    {
        foreach ($source as $key => $value) {
            if ($predicate === null || $predicate($value, $key)) {
                return $value;
            }
        }

        return null;
    }
```

Add import: `use UnderflowException;` (alphabetical among existing imports).

- [ ] **Step 4: Run tests to verify they pass**

Run: `./vendor/bin/phpunit tests/Collections/IterTest.php`
Expected: PASS.

- [ ] **Step 5: Run quality gates**

Run: `composer phpcbf`, `composer phpcs`, `composer phpstan`, `composer test`. Fix any reported issues.

- [ ] **Step 6: Commit**

```bash
git add src/Collections/Iter.php tests/Collections/IterTest.php
git commit -m "feat(collections): add Iter::first, Iter::firstOrNull"
```

---

## Task 9: `any`, `all`

**Files:**
- Modify: `src/Collections/Iter.php`
- Test: `tests/Collections/IterTest.php`

**Interfaces:**
- Consumes: nothing new.
- Produces:
  - `Iter::any(iterable $source, callable $predicate): bool` — short-circuits on first match.
  - `Iter::all(iterable $source, callable $predicate): bool` — short-circuits on first non-match; `true` for an empty source.

- [ ] **Step 1: Write failing tests**

Append to `IterTest.php`:

```php
    #[Test]
    public function anyReturnsTrueIfAnyElementMatches(): void
    {
        static::assertTrue(Iter::any([1, 2, 3], static fn (int $v): bool => $v === 2));
    }

    #[Test]
    public function anyReturnsFalseIfNoElementMatches(): void
    {
        static::assertFalse(Iter::any([1, 2, 3], static fn (int $v): bool => $v === 5));
    }

    #[Test]
    public function anyShortCircuitsOnFirstMatch(): void
    {
        $calls = 0;
        $source = [1, 2, 3];
        Iter::any($source, static function (int $v) use (&$calls): bool {
            $calls++;

            return $v === 1;
        });

        static::assertSame(1, $calls);
    }

    #[Test]
    public function allReturnsTrueIfEveryElementMatches(): void
    {
        static::assertTrue(Iter::all([2, 4, 6], static fn (int $v): bool => $v % 2 === 0));
    }

    #[Test]
    public function allReturnsFalseIfAnyElementFailsToMatch(): void
    {
        static::assertFalse(Iter::all([2, 3, 6], static fn (int $v): bool => $v % 2 === 0));
    }

    #[Test]
    public function allReturnsTrueForEmptySource(): void
    {
        static::assertTrue(Iter::all([], static fn (int $v): bool => $v % 2 === 0));
    }

    #[Test]
    public function allShortCircuitsOnFirstNonMatch(): void
    {
        $calls = 0;
        $source = [2, 3, 4];
        Iter::all($source, static function (int $v) use (&$calls): bool {
            $calls++;

            return $v % 2 === 0;
        });

        static::assertSame(2, $calls);
    }
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `./vendor/bin/phpunit tests/Collections/IterTest.php`
Expected: FAIL — `Iter::any`/`all` do not exist.

- [ ] **Step 3: Implement `any`, `all`**

Insert alphabetically at the very top of the public methods: `all, any, chunk, count, filter, first, firstOrNull, flatMap, flatten, map, reduce, skip, skipWhile, take, takeWhile, tap, toArray, toList, unique, zip`.

```php
    /**
     * Eagerly checks whether every element matches a predicate.
     *
     * @param iterable $source    The source iterable.
     * @param callable $predicate The predicate to test each element.
     *
     * @return bool True if every element matches (including an empty source); false otherwise.
     *
     * @template T
     *
     * @phpstan-param iterable<int|string,T> $source
     * @phpstan-param callable(T,int|string):bool $predicate
     */
    public static function all(iterable $source, callable $predicate): bool
    {
        foreach ($source as $key => $value) {
            if (!$predicate($value, $key)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Eagerly checks whether any element matches a predicate.
     *
     * @param iterable $source    The source iterable.
     * @param callable $predicate The predicate to test each element.
     *
     * @return bool True if any element matches; false otherwise (including an empty source).
     *
     * @template T
     *
     * @phpstan-param iterable<int|string,T> $source
     * @phpstan-param callable(T,int|string):bool $predicate
     */
    public static function any(iterable $source, callable $predicate): bool
    {
        foreach ($source as $key => $value) {
            if ($predicate($value, $key)) {
                return true;
            }
        }

        return false;
    }
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `./vendor/bin/phpunit tests/Collections/IterTest.php`
Expected: PASS.

- [ ] **Step 5: Run quality gates including coverage check**

Run: `composer phpcbf`, `composer phpcs`, `composer phpstan`, `composer test`.
Check the coverage output (or generated report) confirms 100% line/branch coverage for `src/Collections/Iter.php`. If any line/branch is uncovered (e.g. the `IteratorAggregate` branch in `toGenerator`, or the empty-`$buffer` skip in `chunk`), add a targeted test for that case and re-run.

- [ ] **Step 6: Commit**

```bash
git add src/Collections/Iter.php tests/Collections/IterTest.php
git commit -m "feat(collections): add Iter::any, Iter::all"
```

---

## Task 10: Final review pass

**Files:**
- Modify (if needed): `src/Collections/Iter.php`, `tests/Collections/IterTest.php`

**Interfaces:**
- Consumes: the complete `Iter` class from Tasks 1–9.
- Produces: nothing new — this task verifies and polishes the finished class.

- [ ] **Step 1: Verify full alphabetical method ordering**

Open `src/Collections/Iter.php` and confirm the public methods appear in this exact order (all static, so a flat alphabetical list per the coding standard): `all, any, chunk, count, filter, first, firstOrNull, flatMap, flatten, map, reduce, skip, skipWhile, take, takeWhile, tap, toArray, toList, unique, zip`, followed by the private `toGenerator` helper at the end (public before private, per the coding standard's ordering rule). Reorder if any task above landed a method out of place.

- [ ] **Step 2: Verify PHPDoc completeness**

Confirm every public method has a full PHPDoc block: description, `@param` for every parameter, `@return`, `@throws` where applicable, `@template` tags, and `@phpstan-param`/`@phpstan-return` for precise generic/callable signatures — one blank line between different annotation types, per `docs/internal/php-coding-standard.md`.

- [ ] **Step 3: Run full quality gate suite one more time**

Run in order: `composer phpcbf`, `composer phpcs`, `composer phpstan`, `composer test`.
All four must be clean, with 100% coverage on `src/Collections/Iter.php`.

- [ ] **Step 4: Commit any final fixes**

If Steps 1–3 required changes:

```bash
git add src/Collections/Iter.php tests/Collections/IterTest.php
git commit -m "chore(collections): polish Iter method ordering and docs"
```

If no changes were needed, skip this commit — the plan is complete.
