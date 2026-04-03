<?php

declare(strict_types=1);

namespace Manychois\PhpStrongTests\Collections;

use BadMethodCallException;
use Generator;
use Manychois\PhpStrong\Collections\ComparerInterface as IComparer;
use Manychois\PhpStrong\Collections\ReadonlyList;
use Manychois\PhpStrong\Collections\SequenceInterface as ISequence;
use OutOfBoundsException;
use Override;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use UnderflowException;

/**
 * Unit tests for ReadonlyList.
 */
final class ReadonlyListTest extends TestCase
{
    /**
     * @return Generator<int, string>
     */
    private static function lettersGenerator(): Generator
    {
        yield 'a';
        yield 'b';
        yield 'c';
    }

    /**
     * @return IComparer<string>
     */
    private static function comparerLastChar(): IComparer
    {
        return new class () implements IComparer {
            #[Override]
            public function compare(mixed $x, mixed $y): int
            {
                $xc = is_string($x) && $x !== '' ? ord($x[strlen($x) - 1]) : 0;
                $yc = is_string($y) && $y !== '' ? ord($y[strlen($y) - 1]) : 0;

                return $xc <=> $yc;
            }
        };
    }

    #[Test]
    public function all_delegatesToInnerList(): void
    {
        $list = new ReadonlyList([1, 2, 3]);
        self::assertTrue($list->all(static fn(int $n): bool => $n > 0));
        self::assertFalse($list->all(static fn(int $n): bool => $n < 3));
    }

    #[Test]
    public function any_delegatesToInnerList(): void
    {
        $list = new ReadonlyList([1, 2, 3]);
        self::assertTrue($list->any(static fn(int $n): bool => $n === 2));
        self::assertFalse($list->any(static fn(int $n): bool => $n > 10));
    }

    #[Test]
    public function arrayAccess_offsetExists_reflectsIndices(): void
    {
        $list = new ReadonlyList(['x', 'y']);
        self::assertTrue($list->offsetExists(0));
        self::assertTrue($list->offsetExists(1));
        self::assertTrue($list->offsetExists(-1));
        self::assertFalse($list->offsetExists(2));
    }

    #[Test]
    public function arrayAccess_offsetGet_returnsElement(): void
    {
        $list = new ReadonlyList([10, 20, 30]);
        self::assertSame(10, $list->offsetGet(0));
        self::assertSame(30, $list->offsetGet(-1));
    }

    #[Test]
    public function arrayAccess_offsetSet_throwsBadMethodCall_forIndexedOffset(): void
    {
        $list = new ReadonlyList([1]);
        $this->expectException(BadMethodCallException::class);
        $this->expectExceptionMessage('Cannot modify a readonly list.');
        $list->offsetSet(0, 99);
    }

    #[Test]
    public function arrayAccess_offsetSet_throwsBadMethodCall_forNullOffset(): void
    {
        $list = new ReadonlyList([1]);
        $this->expectException(BadMethodCallException::class);
        $list->offsetSet(null, 2);
    }

    #[Test]
    public function arrayAccess_offsetUnset_throwsBadMethodCall(): void
    {
        $list = new ReadonlyList([1, 2]);
        $this->expectException(BadMethodCallException::class);
        $list->offsetUnset(0);
    }

    #[Test]
    public function asArray_reindexesAssociativeSource(): void
    {
        $list = new ReadonlyList(['k' => 'alpha', 'j' => 'beta']);
        self::assertSame(['alpha', 'beta'], $list->asArray());
    }

    #[Test]
    public function asList_returnsIndependentMutableList(): void
    {
        $list = new ReadonlyList([1, 2]);
        $mutable = $list->asList();
        $mutable->offsetSet(0, 10);
        self::assertSame([1, 2], $list->asArray());
        self::assertSame([10, 2], $mutable->asArray());
    }

    #[Test]
    public function at_returnsItemForValidIndex(): void
    {
        $list = new ReadonlyList(['a', 'b', 'c']);
        self::assertSame('a', $list->at(0));
        self::assertSame('c', $list->at(-1));
    }

    #[Test]
    public function at_throwsOutOfBoundsForInvalidIndex(): void
    {
        $list = new ReadonlyList([1]);
        $this->expectException(OutOfBoundsException::class);
        $list->at(5);
    }

    #[Test]
    public function constructor_copiesGeneratorContentEagerly(): void
    {
        $list = new ReadonlyList(self::lettersGenerator());
        self::assertSame(['a', 'b', 'c'], $list->asArray());
        self::assertCount(3, $list);
    }

    #[Test]
    public function constructor_emptyIterable_yieldsEmptyList(): void
    {
        $list = new ReadonlyList([]);
        self::assertTrue($list->isEmpty());
        self::assertCount(0, $list);
        self::assertSame([], $list->asArray());
    }

    #[Test]
    public function contains_delegatesToInnerList(): void
    {
        $list = new ReadonlyList([1, 2, 3]);
        self::assertTrue($list->contains(2));
        self::assertFalse($list->contains(9));
    }

    #[Test]
    public function count_matchesSourceLength(): void
    {
        $list = new ReadonlyList([1, 2, 3, 4]);
        self::assertCount(4, $list);
    }

    #[Test]
    public function filter_returnsLazySequence(): void
    {
        $list = new ReadonlyList([1, 2, 3, 4]);
        $filtered = $list->filter(static fn(int $n): bool => $n % 2 === 0);
        self::assertSame([2, 4], $filtered->asArray());
        self::assertSame([1, 2, 3, 4], $list->asArray());
    }

    #[Test]
    public function first_returnsFirstElement(): void
    {
        $list = new ReadonlyList([7, 8, 9]);
        self::assertSame(7, $list->first());
    }

    #[Test]
    public function getIterator_yieldsAllElementsInOrder(): void
    {
        $list = new ReadonlyList([1, 2, 3]);
        self::assertSame([1, 2, 3], iterator_to_array($list->getIterator(), false));
    }

    #[Test]
    public function indexOf_findsFirstOccurrence(): void
    {
        $list = new ReadonlyList([10, 20, 10]);
        self::assertSame(0, $list->indexOf(10));
        self::assertSame(-1, $list->indexOf(99));
    }

    #[Test]
    public function map_delegatesAndDoesNotMutateReadonlyList(): void
    {
        $list = new ReadonlyList([1, 2]);
        $mapped = $list->map(static fn(int $n): int => $n * 10);
        self::assertSame([10, 20], $mapped->asArray());
        self::assertSame([1, 2], $list->asArray());
    }

    #[Test]
    public function squareBracketSyntax_readsElements(): void
    {
        $list = new ReadonlyList(['p', 'q']);
        self::assertSame('p', $list[0]);
        self::assertSame('q', $list[-1]);
    }

    #[Test]
    public function squareBracketSyntax_assignmentThrowsBadMethodCall(): void
    {
        $list = new ReadonlyList([1]);
        $this->expectException(BadMethodCallException::class);
        $this->expectExceptionMessage('Cannot modify a readonly list.');
        $list[0] = 9;
    }

    #[Test]
    public function unsetArrayOffset_throwsBadMethodCall(): void
    {
        $list = new ReadonlyList([1, 2]);
        $this->expectException(BadMethodCallException::class);
        unset($list[0]);
    }

    #[Test]
    public function arrayAccess_isset_reflectsOffsetExists(): void
    {
        $list = new ReadonlyList(['a']);
        self::assertTrue(isset($list[0]));
        self::assertFalse(isset($list[1]));
    }

    #[Test]
    public function asArray_returnValueIsSafeToMutateWithoutAffectingList(): void
    {
        $list = new ReadonlyList([1, 2]);
        $buf = $list->asArray();
        $buf[] = 3;
        self::assertSame([1, 2], $list->asArray());
        self::assertSame([1, 2, 3], $buf);
    }

    #[Test]
    public function chunk_splitsIntoFixedSizeGroups(): void
    {
        $list = new ReadonlyList([1, 2, 3, 4, 5]);
        $chunks = $list->chunk(2);
        self::assertSame(
            [[1, 2], [3, 4], [5]],
            $chunks->map(static fn(ISequence $c): array => $c->asArray())->asArray()
        );
    }

    #[Test]
    public function distinct_preservesFirstOccurrenceOrder(): void
    {
        $list = new ReadonlyList([3, 1, 3, 2, 1]);
        self::assertSame([3, 1, 2], $list->distinct()->asArray());
    }

    #[Test]
    public function except_excludesItemsPresentInOtherSequence(): void
    {
        $list = new ReadonlyList([1, 2, 3, 4]);
        self::assertSame([1, 4], $list->except([2, 3])->asArray());
    }

    #[Test]
    public function findIndex_returnsFirstMatchIndex(): void
    {
        $list = new ReadonlyList([2, 4, 6]);
        self::assertSame(1, $list->findIndex(static fn(int $n): bool => $n > 3));
    }

    #[Test]
    public function findLastIndex_returnsLastMatchIndex(): void
    {
        $list = new ReadonlyList([2, 4, 6, 4]);
        self::assertSame(3, $list->findLastIndex(static fn(int $n): bool => $n === 4));
    }

    #[Test]
    public function first_throwsUnderflowWhenEmpty(): void
    {
        $list = new ReadonlyList([]);
        $this->expectException(UnderflowException::class);
        $list->first();
    }

    #[Test]
    public function firstOrNull_returnsNullWhenEmpty(): void
    {
        $list = new ReadonlyList([]);
        self::assertNull($list->firstOrNull());
    }

    #[Test]
    public function intersect_keepsOrderOfFirstSequence(): void
    {
        $list = new ReadonlyList([3, 1, 2]);
        self::assertSame([3, 1, 2], $list->intersect([1, 3, 2, 0])->asArray());
    }

    #[Test]
    public function isEmpty_isFalseWhenItemsPresent(): void
    {
        self::assertFalse((new ReadonlyList([0]))->isEmpty());
    }

    #[Test]
    public function last_returnsFinalElement(): void
    {
        $list = new ReadonlyList([1, 2, 9]);
        self::assertSame(9, $list->last());
    }

    #[Test]
    public function lastOrNull_returnsNullWhenEmpty(): void
    {
        self::assertNull((new ReadonlyList([]))->lastOrNull());
    }

    #[Test]
    public function lastIndexOf_findsLastOccurrence(): void
    {
        $list = new ReadonlyList([1, 2, 1, 3]);
        self::assertSame(2, $list->lastIndexOf(1));
    }

    #[Test]
    public function orderBy_sortsAscendingUsingComparer(): void
    {
        $list = new ReadonlyList(['bat', 'mud', 'car']);
        self::assertSame(['mud', 'car', 'bat'], $list->orderBy(self::comparerLastChar())->asArray());
    }

    #[Test]
    public function orderDescBy_sortsDescendingUsingComparer(): void
    {
        $list = new ReadonlyList(['bat', 'mud', 'car']);
        self::assertSame(['bat', 'car', 'mud'], $list->orderDescBy(self::comparerLastChar())->asArray());
    }

    #[Test]
    public function precededBy_prependsOtherSequences(): void
    {
        $list = new ReadonlyList([3]);
        self::assertSame([1, 2, 3], $list->precededBy([1], [2])->asArray());
    }

    #[Test]
    public function reduce_accumulatesValues(): void
    {
        $list = new ReadonlyList([1, 2, 3]);
        self::assertSame(6, $list->reduce(static fn(int $a, int $b): int => $a + $b, 0));
    }

    #[Test]
    public function reverse_returnsElementsInOppositeOrder(): void
    {
        $list = new ReadonlyList([1, 2, 3]);
        self::assertSame([3, 2, 1], $list->reverse()->asArray());
    }

    #[Test]
    public function shuffle_preservesMultiset(): void
    {
        $list = new ReadonlyList([1, 2, 2, 3]);
        $shuffled = $list->shuffle()->asArray();
        sort($shuffled);
        self::assertSame([1, 2, 2, 3], $shuffled);
        self::assertSame([1, 2, 2, 3], $list->asArray());
    }

    #[Test]
    public function skip_and_take_sliceWindow(): void
    {
        $list = new ReadonlyList([10, 20, 30, 40, 50]);
        self::assertSame([30, 40], $list->skip(2)->take(2)->asArray());
    }

    #[Test]
    public function take_delegates_to_inner_list(): void
    {
        $list = new ReadonlyList([1, 2, 3, 4]);
        self::assertSame([1, 2], $list->take(2)->asArray());
    }

    #[Test]
    public function takeLast_delegates_to_inner_list(): void
    {
        $list = new ReadonlyList([1, 2, 3, 4, 5]);
        self::assertSame([4, 5], $list->takeLast(2)->asArray());
    }

    #[Test]
    public function skipLast_and_takeLast_selectEndWindow(): void
    {
        $list = new ReadonlyList([1, 2, 3, 4, 5]);
        self::assertSame([3, 4], $list->skipLast(1)->takeLast(2)->asArray());
    }

    #[Test]
    public function slice_returnsSubrangeByIndex(): void
    {
        $list = new ReadonlyList(['a', 'b', 'c', 'd']);
        self::assertSame(['b', 'c'], $list->slice(1, 2)->asArray());
    }

    #[Test]
    public function then_appendsOtherSequences(): void
    {
        $list = new ReadonlyList([1]);
        self::assertSame([1, 2, 3], $list->then([2], [3])->asArray());
    }

    #[Test]
    public function union_keepsDistinctInEncounterOrder(): void
    {
        $list = new ReadonlyList([1, 2]);
        self::assertSame([1, 2, 3], $list->union([2, 3])->asArray());
    }
}
