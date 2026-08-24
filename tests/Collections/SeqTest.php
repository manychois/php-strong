<?php

declare(strict_types=1);

namespace Manychois\PhpStrongTests\Collections;

use Generator;
use InvalidArgumentException;
use Manychois\PhpStrong\Collections\Seq;
use OutOfBoundsException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use UnderflowException;

final class SeqTest extends TestCase
{
    #[Test]
    public function allReturnsTrueWhenEveryElementMatches(): void
    {
        static::assertTrue(Seq::all([2, 4, 6], static fn (int $v): bool => $v % 2 === 0));
        static::assertTrue(Seq::all([], static fn (int $v): bool => false));
    }

    #[Test]
    public function allReturnsFalseWhenAnyElementFailsToMatch(): void
    {
        static::assertFalse(Seq::all([2, 3, 6], static fn (int $v): bool => $v % 2 === 0));
    }

    #[Test]
    public function anyReturnsTrueWhenAnyElementMatches(): void
    {
        static::assertTrue(Seq::any([1, 2, 3], static fn (int $v): bool => $v % 2 === 0));
    }

    #[Test]
    public function anyReturnsFalseForEmptySource(): void
    {
        static::assertFalse(Seq::any([], static fn (int $v): bool => true));
    }

    #[Test]
    public function atReturnsTheElementAtTheGivenIndex(): void
    {
        static::assertSame('b', Seq::at(['a', 'b', 'c'], 1));
    }

    #[Test]
    public function atCountsANegativeIndexFromTheEnd(): void
    {
        static::assertSame('c', Seq::at(['a', 'b', 'c'], -1));
        static::assertSame('a', Seq::at(['a', 'b', 'c'], -3));
    }

    #[Test]
    public function atThrowsWhenTheIndexIsOutOfBounds(): void
    {
        $this->expectException(OutOfBoundsException::class);

        Seq::at(['a', 'b'], 2);
    }

    #[Test]
    public function atThrowsWhenTheNegativeIndexIsOutOfBounds(): void
    {
        $this->expectException(OutOfBoundsException::class);

        Seq::at(['a', 'b'], -3);
    }

    #[Test]
    public function atThrowsForAnEmptySource(): void
    {
        $this->expectException(OutOfBoundsException::class);

        Seq::at([], 0);
    }

    #[Test]
    public function chunkGroupsElementsIntoListsOfGivenSize(): void
    {
        static::assertSame([[1, 2], [3, 4], [5]], Seq::chunk([1, 2, 3, 4, 5], 2));
    }

    #[Test]
    public function chunkThrowsForNonPositiveSize(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Seq::chunk([1, 2], 0);
    }

    #[Test]
    public function concatAppendsSourcesEndToEnd(): void
    {
        static::assertSame([1, 2, 3, 4], Seq::concat([1, 2], ['a' => 3], [4]));
    }

    #[Test]
    public function concatReturnsAnEmptyListWithoutSources(): void
    {
        static::assertSame([], Seq::concat());
    }

    #[Test]
    public function containsFindsAnElementByStrictComparison(): void
    {
        static::assertTrue(Seq::contains([1, 2, 3], 2));
        static::assertFalse(Seq::contains([1, 2, 3], '2'));
        static::assertFalse(Seq::contains([], 1));
    }

    #[Test]
    public function filterKeepsMatchingElementsAsAReindexedList(): void
    {
        $result = Seq::filter(['a' => 1, 'b' => 2, 'c' => 3, 'd' => 4], static fn (int $v): bool => $v % 2 === 0);

        static::assertSame([2, 4], $result);
    }

    #[Test]
    public function filterPassesThePositionalIndexToThePredicate(): void
    {
        $seen = [];
        Seq::filter(['x' => 'a', 'y' => 'b'], static function (string $v, int $i) use (&$seen): bool {
            $seen[] = $i;

            return true;
        });

        static::assertSame([0, 1], $seen);
    }

    #[Test]
    public function firstReturnsTheFirstMatchingElement(): void
    {
        static::assertSame(1, Seq::first([1, 2, 3]));
        static::assertSame(2, Seq::first([1, 2, 3, 4], static fn (int $v): bool => $v % 2 === 0));
    }

    #[Test]
    public function firstThrowsWhenNoElementMatches(): void
    {
        $this->expectException(UnderflowException::class);

        Seq::first([1, 3], static fn (int $v): bool => $v % 2 === 0);
    }

    #[Test]
    public function firstOrNullReturnsNullWhenNoElementMatches(): void
    {
        static::assertNull(Seq::firstOrNull([]));
        static::assertNull(Seq::firstOrNull([1, 3], static fn (int $v): bool => $v % 2 === 0));
    }

    #[Test]
    public function firstOrNullReturnsTheFirstMatchingElement(): void
    {
        static::assertSame(1, Seq::firstOrNull([1, 2, 3]));
        static::assertSame(2, Seq::firstOrNull([1, 2, 3, 4], static fn (int $v): bool => $v % 2 === 0));
    }

    #[Test]
    public function flatMapConcatenatesTheMappedIterables(): void
    {
        $result = Seq::flatMap([1, 2], static fn (int $v): array => [$v, $v * 10]);

        static::assertSame([1, 10, 2, 20], $result);
    }

    #[Test]
    public function flatMapPassesThePositionalIndexToTheMapper(): void
    {
        $result = Seq::flatMap(['a' => 'x', 'b' => 'y'], static fn (string $v, int $i): array => [$i]);

        static::assertSame([0, 1], $result);
    }

    #[Test]
    public function flattenConcatenatesOneLevel(): void
    {
        static::assertSame([1, 2, 3, 4], Seq::flatten([[1, 2], ['a' => 3], [4]]));
    }

    #[Test]
    public function indexOfReturnsThePositionOfTheFirstStrictMatch(): void
    {
        static::assertSame(1, Seq::indexOf(['a', 'b', 'c', 'b'], 'b'));
    }

    #[Test]
    public function indexOfReturnsNullWhenTheValueIsAbsent(): void
    {
        static::assertNull(Seq::indexOf([1, 2, 3], 4));
        static::assertNull(Seq::indexOf([1, 2, 3], '1'));
    }

    #[Test]
    public function insertAtInsertsValuesBeforeTheGivenIndex(): void
    {
        static::assertSame(['a', 'x', 'y', 'b'], Seq::insertAt(['a', 'b'], 1, 'x', 'y'));
    }

    #[Test]
    public function insertAtAppendsWhenIndexEqualsTheCount(): void
    {
        static::assertSame(['a', 'b', 'c'], Seq::insertAt(['a', 'b'], 2, 'c'));
    }

    #[Test]
    public function insertAtWithoutValuesReturnsTheListUnchanged(): void
    {
        static::assertSame(['a', 'b'], Seq::insertAt(['a', 'b'], 1));
    }

    #[Test]
    public function insertAtThrowsForAnIndexOutsideTheRange(): void
    {
        $this->expectException(OutOfBoundsException::class);

        Seq::insertAt(['a', 'b'], 3, 'x');
    }

    #[Test]
    public function insertAtThrowsForANegativeIndex(): void
    {
        $this->expectException(OutOfBoundsException::class);

        Seq::insertAt(['a', 'b'], -1, 'x');
    }

    #[Test]
    public function lastReturnsTheLastMatchingElement(): void
    {
        static::assertSame(3, Seq::last([1, 2, 3]));
        static::assertSame(4, Seq::last([1, 2, 3, 4, 5], static fn (int $v): bool => $v % 2 === 0));
    }

    #[Test]
    public function lastThrowsWhenNoElementMatches(): void
    {
        $this->expectException(UnderflowException::class);

        Seq::last([]);
    }

    #[Test]
    public function lastIndexOfReturnsThePositionOfTheLastStrictMatch(): void
    {
        static::assertSame(3, Seq::lastIndexOf(['a', 'b', 'c', 'b'], 'b'));
    }

    #[Test]
    public function lastIndexOfReturnsNullWhenTheValueIsAbsent(): void
    {
        static::assertNull(Seq::lastIndexOf([1, 2], 3));
    }

    #[Test]
    public function lastOrNullReturnsNullWhenNoElementMatches(): void
    {
        static::assertNull(Seq::lastOrNull([]));
        static::assertNull(Seq::lastOrNull([1, 3], static fn (int $v): bool => $v % 2 === 0));
    }

    #[Test]
    public function lastOrNullReturnsTheLastMatchingElement(): void
    {
        static::assertSame(3, Seq::lastOrNull([1, 2, 3]));
        static::assertSame(4, Seq::lastOrNull([1, 2, 3, 4, 5], static fn (int $v): bool => $v % 2 === 0));
    }

    #[Test]
    public function mapTransformsEachElementIntoAList(): void
    {
        static::assertSame([10, 20, 30], Seq::map([1, 2, 3], static fn (int $v): int => $v * 10));
    }

    #[Test]
    public function mapPassesThePositionalIndexEvenForAMapKeyedSource(): void
    {
        $result = Seq::map(['a' => 'x', 'b' => 'y'], static fn (string $v, int $i): string => $v . $i);

        static::assertSame(['x0', 'y1'], $result);
    }

    #[Test]
    public function orderBySortsAscendingBySpaceshipByDefault(): void
    {
        static::assertSame([1, 2, 3], Seq::orderBy([3, 1, 2]));
        static::assertSame(['a', 'b'], Seq::orderBy(['b', 'a']));
    }

    #[Test]
    public function orderByAcceptsAComparator(): void
    {
        $result = Seq::orderBy([3, 1, 2], static fn (int $a, int $b): int => $b <=> $a);

        static::assertSame([3, 2, 1], $result);
    }

    #[Test]
    public function orderByIsStable(): void
    {
        $source = [['b', 1], ['a', 1], ['c', 0]];
        $result = Seq::orderBy($source, static fn (array $x, array $y): int => $x[1] <=> $y[1]);

        static::assertSame([['c', 0], ['b', 1], ['a', 1]], $result);
    }

    #[Test]
    public function orderByDoesNotMutateTheSourceArray(): void
    {
        $source = [3, 1, 2];
        Seq::orderBy($source);

        static::assertSame([3, 1, 2], $source);
    }

    #[Test]
    public function reduceAccumulatesAcrossTheElements(): void
    {
        $result = Seq::reduce([1, 2, 3], static fn (int $carry, int $v): int => $carry + $v, 0);

        static::assertSame(6, $result);
    }

    #[Test]
    public function reducePassesThePositionalIndexToTheReducer(): void
    {
        $result = Seq::reduce(
            ['a' => 'x', 'b' => 'y'],
            static fn (string $carry, string $v, int $i): string => $carry . $i,
            '',
        );

        static::assertSame('01', $result);
    }

    #[Test]
    public function removeAtRemovesTheElementAtTheGivenIndex(): void
    {
        static::assertSame(['a', 'c'], Seq::removeAt(['a', 'b', 'c'], 1));
    }

    #[Test]
    public function removeAtThrowsForAnIndexOutsideTheRange(): void
    {
        $this->expectException(OutOfBoundsException::class);

        Seq::removeAt(['a', 'b'], 2);
    }

    #[Test]
    public function reverseReturnsTheElementsInReverseOrder(): void
    {
        static::assertSame([3, 2, 1], Seq::reverse([1, 2, 3]));
        static::assertSame([], Seq::reverse([]));
    }

    #[Test]
    public function skipDropsTheFirstElements(): void
    {
        static::assertSame([3, 4], Seq::skip([1, 2, 3, 4], 2));
        static::assertSame([], Seq::skip([1, 2], 5));
    }

    #[Test]
    public function skipThrowsForNegativeCount(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Seq::skip([1, 2], -1);
    }

    #[Test]
    public function skipWhileDropsLeadingMatchesThenKeepsTheRemainder(): void
    {
        $result = Seq::skipWhile([1, 2, 5, 1], static fn (int $v): bool => $v < 3);

        static::assertSame([5, 1], $result);
    }

    #[Test]
    public function sliceReturnsTheRequestedSubList(): void
    {
        static::assertSame([2, 3], Seq::slice([1, 2, 3, 4], 1, 2));
        static::assertSame([2, 3, 4], Seq::slice([1, 2, 3, 4], 1));
    }

    #[Test]
    public function sliceAcceptsNegativeOffsetAndLength(): void
    {
        static::assertSame([3, 4], Seq::slice([1, 2, 3, 4], -2));
        static::assertSame([2, 3], Seq::slice([1, 2, 3, 4], 1, -1));
    }

    #[Test]
    public function sliceReturnsAnEmptyListForAnOutOfRangeOffset(): void
    {
        static::assertSame([], Seq::slice([1, 2], 5));
    }

    #[Test]
    public function takeKeepsAtMostTheFirstElements(): void
    {
        static::assertSame([1, 2], Seq::take([1, 2, 3], 2));
        static::assertSame([], Seq::take([1, 2], 0));
        static::assertSame([1, 2], Seq::take([1, 2], 5));
    }

    #[Test]
    public function takeThrowsForNegativeCount(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Seq::take([1, 2], -1);
    }

    #[Test]
    public function takeWhileStopsAtTheFirstNonMatchingElement(): void
    {
        $result = Seq::takeWhile([1, 2, 5, 1], static fn (int $v): bool => $v < 3);

        static::assertSame([1, 2], $result);
    }

    #[Test]
    public function uniqueKeepsTheFirstOccurrenceOfEachElement(): void
    {
        static::assertSame([1, 2, 3], Seq::unique(['a' => 1, 'b' => 2, 'c' => 1, 'd' => 3]));
    }

    #[Test]
    public function uniqueAcceptsAKeySelector(): void
    {
        $source = [['id' => 1], ['id' => 2], ['id' => 1]];
        $result = Seq::unique($source, static fn (array $row): int => $row['id']);

        static::assertSame([['id' => 1], ['id' => 2]], $result);
    }

    #[Test]
    public function methodsNormalizeAGeneratorSource(): void
    {
        $source = static function (): Generator {
            yield 'k' => 1;

            yield 'k' => 2;
        };

        static::assertSame([2, 1], Seq::reverse($source()));
        static::assertSame(1, Seq::at($source(), 0));
        static::assertSame([1, 2], Seq::orderBy($source()));
    }
}
