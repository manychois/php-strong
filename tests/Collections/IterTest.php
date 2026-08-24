<?php

declare(strict_types=1);

namespace Manychois\PhpStrongTests\Collections;

use ArrayObject;
use Generator;
use InvalidArgumentException;
use Manychois\PhpStrong\Collections\Iter;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TypeError;
use UnderflowException;

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
        $this->expectException(InvalidArgumentException::class);

        Iter::take([1, 2], -1);
    }

    #[Test]
    public function takeYieldsNothingForZeroCount(): void
    {
        $result = Iter::take([1, 2, 3], 0);

        static::assertSame([], iterator_to_array($result));
    }

    #[Test]
    public function takeDoesNotIterateSourceBeyondCount(): void
    {
        $calls = 0;
        $source = (static function () use (&$calls): Generator {
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
        $this->expectException(InvalidArgumentException::class);

        Iter::skip([1, 2], -1);
    }

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
        $this->expectException(InvalidArgumentException::class);

        Iter::chunk([1, 2], 0);
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

    #[Test]
    public function zipAcceptsAnIteratorSource(): void
    {
        $generator = (static function (): Generator {
            yield 1;
            yield 2;
        })();
        $result = Iter::zip($generator, ['a', 'b']);

        static::assertSame([[1, 'a'], [2, 'b']], iterator_to_array($result));
    }

    #[Test]
    public function zipAcceptsAnIteratorAggregateSource(): void
    {
        $result = Iter::zip(new ArrayObject([1, 2]), ['a', 'b']);

        static::assertSame([[1, 'a'], [2, 'b']], iterator_to_array($result));
    }

    #[Test]
    public function zipAcceptsAPartiallyConsumedGeneratorSource(): void
    {
        $generator = (static function (): Generator {
            yield 1;
            yield 2;
            yield 3;
        })();
        $generator->current();
        $generator->next();

        $result = Iter::zip($generator, ['a', 'b']);

        static::assertSame([[2, 'a'], [3, 'b']], iterator_to_array($result));
    }

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

    #[Test]
    public function uniqueByDefaultCollapsesElementsThatCoerceToTheSameArrayKey(): void
    {
        // 1, '1', true and 1.0 all coerce to the same int array key; '1.0' does not.
        $source = [1, '1', true, 1.0, '1.0'];
        $result = Iter::unique($source);

        static::assertSame([0 => 1, 4 => '1.0'], iterator_to_array($result));
    }

    #[Test]
    public function uniqueThrowsTypeErrorForNonArrayKeyElementWithoutKeySelector(): void
    {
        $this->expectException(TypeError::class);

        iterator_to_array(Iter::unique([(object) []]));
    }

    #[Test]
    public function toArrayPreservesKeysWithLastWriteWinningOnCollision(): void
    {
        $source = (static function (): Generator {
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
        $source = (static function (): Generator {
            yield 1;
            yield 2;
        })();

        static::assertSame(2, Iter::count($source));
    }

    #[Test]
    public function countReturnsElementCountForCountable(): void
    {
        $countable = new ArrayObject([1, 2, 3, 4]);

        static::assertSame(4, Iter::count($countable));
    }

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
        $this->expectException(UnderflowException::class);

        Iter::first([]);
    }

    #[Test]
    public function firstThrowsWhenNoElementMatches(): void
    {
        $this->expectException(UnderflowException::class);

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
}
