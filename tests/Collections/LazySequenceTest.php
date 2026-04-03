<?php

declare(strict_types=1);

namespace Manychois\PhpStrongTests\Collections;

use DateTimeImmutable;
use DateTimeZone;
use Generator;
use InvalidArgumentException;
use Manychois\PhpStrong\Collections\ComparerInterface as IComparer;
use Manychois\PhpStrong\Collections\LazySequence;
use Manychois\PhpStrong\Collections\SequenceInterface as ISequence;
use Override;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use UnderflowException;

/**
 * Unit tests for LazySequence.
 */
final class LazySequenceTest extends TestCase
{
    /**
     * @var list<DateTimeImmutable>
     */
    private static array $datetime_objects = [];

    /**
     * @return IComparer<string>
     */
    private static function comparer_last_char(): IComparer
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

    /**
     * @return list<int>
     */
    private static function source_some_integers(): array
    {
        return [-1, -5, 3, 0, 6, -8, 4, 2, 7, -10, 9];
    }

    /**
     * @return array<string,string>
     */
    private static function source_some_words(): array
    {
        return [
            'c' => 'cherry',
            'a' => 'apple',
            'b' => 'banana',
            'd' => 'date',
        ];
    }

    /**
     * @return Generator<string, DateTimeImmutable>
     */
    private static function source_some_datetime(): Generator
    {
        if (count(self::$datetime_objects) === 0) {
            $date = new DateTimeImmutable('2026-01-01', new DateTimeZone('UTC'));
            self::$datetime_objects[] = $date;
            for ($i = 0; $i < 4; $i++) {
                $date = $date->modify('+1 day');
                self::$datetime_objects[] = $date;
            }
        }

        foreach (self::$datetime_objects as $date) {
            yield $date->format('Y-m-d') => $date;
        }
    }

    #[Test]
    public function all_returnsTrue_ifAllItemsSatisfyPredicate(): void
    {
        $seq = new LazySequence(self::source_some_datetime());
        self::assertTrue($seq->all(
            static fn(DateTimeImmutable $item, int $index): bool => intval($item->format('d')) === $index + 1
        ));
    }

    #[Test]
    public function all_returnsFalse_ifNotAllItemsSatisfyPredicate(): void
    {
        $seq = new LazySequence(self::source_some_words());
        self::assertFalse($seq->all(static fn(string $item): bool => strlen($item) > 10));
    }

    #[Test]
    public function any_returnsTrue_ifSomeItemSatisfiesPredicate(): void
    {
        $seq = new LazySequence(self::source_some_datetime());
        self::assertTrue($seq->any(
            static fn(DateTimeImmutable $item, int $index): bool => $index === 2 && $item->format('Y-m-d') === '2026-01-03'
        ));
    }

    #[Test]
    public function any_returnsFalse_ifNoItemSatisfiesPredicate(): void
    {
        $seq = new LazySequence(self::source_some_words());
        self::assertFalse($seq->any(static fn(string $item): bool => $item === 'fig'));
    }

    #[Test]
    public function asArray_returnsEmptyList_whenSourceIsEmpty(): void
    {
        $seq = new LazySequence([]);
        self::assertSame([], $seq->asArray());
    }

    #[Test]
    public function asArray_returnsSameList_whenSourceIsListArray(): void
    {
        $seq = new LazySequence(self::source_some_integers());
        $result = $seq->asArray();
        self::assertIsList($result);
        self::assertSame(self::source_some_integers(), $result);
    }

    #[Test]
    public function asArray_reindexesValues_whenSourceIsAssociativeArray(): void
    {
        $seq = new LazySequence(self::source_some_words());
        $result = $seq->asArray();
        self::assertIsList($result);
        self::assertSame(['cherry', 'apple', 'banana', 'date'], $result);
    }

    #[Test]
    public function asArray_listsValuesInYieldOrder_whenSourceIsGenerator(): void
    {
        $seq = new LazySequence(self::source_some_datetime());
        $result = $seq->asArray();
        self::assertIsList($result);
        self::assertCount(5, $result);
        self::assertSame('2026-01-01', $result[0]->format('Y-m-d'));
        self::assertSame('2026-01-05', $result[4]->format('Y-m-d'));
    }

    #[Test]
    public function asList_materializesSameOrderAsAsArray(): void
    {
        $seq = new LazySequence(self::source_some_integers());
        self::assertSame($seq->asArray(), $seq->asList()->asArray());
    }

    #[Test]
    public function chunk_groupsItemsBySize(): void
    {
        $seq = new LazySequence([1, 2, 3, 4, 5]);
        $chunks = $seq->chunk(2);
        self::assertSame(
            [[1, 2], [3, 4], [5]],
            $chunks->map(static fn(ISequence $c): array => $c->asArray())->asArray()
        );
    }

    #[Test]
    public function chunk_throwsInvalidArgumentException_ifSizeNotPositive(): void
    {
        self::expectException(InvalidArgumentException::class);
        $seq = new LazySequence([1]);
        $seq->chunk(0);
    }

    #[Test]
    public function contains_returnsFalse_ifValueAbsent(): void
    {
        $seq = new LazySequence(self::source_some_integers());
        self::assertFalse($seq->contains(99));

        $seq = new LazySequence(self::source_some_datetime());
        self::assertFalse($seq->contains(new DateTimeImmutable('2026-01-01')));
    }

    #[Test]
    public function contains_returnsTrue_ifValuePresent(): void
    {
        $seq = new LazySequence(self::source_some_words());
        self::assertTrue($seq->contains('banana'));

        $seq = new LazySequence(self::source_some_datetime());
        self::assertTrue($seq->contains(self::$datetime_objects[2]));
    }

    #[Test]
    public function count_returnsNumberOfItems(): void
    {
        $seq = new LazySequence(self::source_some_integers());
        self::assertCount(11, $seq);

        $seq = new LazySequence(self::source_some_datetime());
        self::assertCount(5, $seq);
    }

    #[Test]
    public function distinct_yieldsFirstOccurrenceOrder(): void
    {
        $seq = new LazySequence([1, 2, 1, 3, 2, 4]);
        self::assertSame([1, 2, 3, 4], $seq->distinct()->asArray());
    }

    #[Test]
    public function except_skipsItemsPresentInOtherSequence_whenOtherIsArray(): void
    {
        $seq = new LazySequence([1, 2, 3, 2, 4]);
        self::assertSame([1, 4], $seq->except([2, 3])->asArray());
    }

    #[Test]
    public function except_skipsItemsPresentInOtherSequence_whenOtherIsIterable(): void
    {
        $seq = new LazySequence([1, 2, 3]);
        $other = (static function (): Generator {
            yield 2;
            yield 99;
        })();
        self::assertSame([1, 3], $seq->except($other)->asArray());
    }

    #[Test]
    public function filter_keepsItemsMatchingPredicate(): void
    {
        $seq = new LazySequence(self::source_some_integers());
        $positive = $seq->filter(static fn(int $n, int $i): bool => $n > 0 && $i > 0);
        self::assertSame([3, 6, 4, 2, 7, 9], $positive->asArray());
    }

    #[Test]
    public function first_returnsFirstItem_whenNoPredicateAndSequenceNotEmpty(): void
    {
        $seq = new LazySequence(self::source_some_words());
        self::assertSame('cherry', $seq->first());
    }

    #[Test]
    public function first_returnsFirstMatchingItem_whenPredicateProvided(): void
    {
        $seq = new LazySequence(self::source_some_integers());
        self::assertSame(3, $seq->first(static fn(int $n): bool => $n > 0));
    }

    #[Test]
    public function first_throwsRuntimeException_whenNoItemMatchesPredicate(): void
    {
        self::expectException(RuntimeException::class);
        $seq = new LazySequence(self::source_some_integers());
        $seq->first(static fn(int $n): bool => $n > 1000);
    }

    #[Test]
    public function first_throwsUnderflowException_whenSequenceIsEmpty(): void
    {
        self::expectException(UnderflowException::class);
        self::expectExceptionMessage('The sequence is empty');
        (new LazySequence([]))->first();
    }

    #[Test]
    public function first_throwsUnderflowException_whenSourceIsEmptyGenerator(): void
    {
        self::expectException(UnderflowException::class);
        self::expectExceptionMessage('The sequence is empty');
        $empty = (static function (): Generator {
            yield from [];
        })();
        (new LazySequence($empty))->first();
    }

    #[Test]
    public function first_throwsUnderflowException_whenEmptySequenceAndPredicateProvided(): void
    {
        self::expectException(UnderflowException::class);
        self::expectExceptionMessage('The sequence is empty');
        (new LazySequence([]))->first(static fn(mixed $_item, int $_i): bool => false);
    }

    #[Test]
    public function firstOrNull_returnsFirstMatchingItem_whenPredicateProvided(): void
    {
        $seq = new LazySequence(self::source_some_integers());
        self::assertSame(3, $seq->firstOrNull(static fn(int $n): bool => $n > 0));
    }

    #[Test]
    public function firstOrNull_returnsNull_whenNoItemMatchesPredicate(): void
    {
        $seq = new LazySequence(self::source_some_integers());
        self::assertNull($seq->firstOrNull(static fn(int $n): bool => $n > 1000));
    }

    #[Test]
    public function firstOrNull_returnsNull_whenSequenceIsEmpty(): void
    {
        self::assertNull((new LazySequence([]))->firstOrNull());
    }

    #[Test]
    public function firstOrNull_returnsFirstItem_whenPredicateIsNullAndSourceIsNonEmptyArray(): void
    {
        $seq = new LazySequence(self::source_some_words());
        self::assertSame('cherry', $seq->firstOrNull());
    }

    #[Test]
    public function getIterator_yieldsZeroBasedIndices(): void
    {
        $seq = new LazySequence(['a', 'b']);
        $keys = [];
        $values = [];
        foreach ($seq->getIterator() as $k => $v) {
            $keys[] = $k;
            $values[] = $v;
        }
        self::assertSame([0, 1], $keys);
        self::assertSame(['a', 'b'], $values);
    }

    #[Test]
    public function intersect_keepsItemsAlsoInOtherSequence(): void
    {
        $seq = new LazySequence([1, 2, 3, 2]);
        self::assertSame([2, 2], $seq->intersect([2, 4])->asArray());
    }

    #[Test]
    public function intersect_keepsItemsAlsoInOtherSequence_whenOtherIsIterableNotArray(): void
    {
        $seq = new LazySequence([1, 2, 3, 2]);
        $other = (static function (): Generator {
            yield 2;
            yield 4;
        })();
        self::assertSame([2, 2], $seq->intersect($other)->asArray());
    }

    #[Test]
    public function isEmpty_returnsFalse_whenSequenceHasItems(): void
    {
        self::assertFalse((new LazySequence(self::source_some_integers()))->isEmpty());
    }

    #[Test]
    public function isEmpty_returnsTrue_whenSequenceHasNoItems(): void
    {
        self::assertTrue((new LazySequence([]))->isEmpty());
    }

    #[Test]
    public function last_returnsLastItem_whenNoPredicateAndSequenceNotEmpty(): void
    {
        $seq = new LazySequence(self::source_some_words());
        self::assertSame('date', $seq->last());
    }

    #[Test]
    public function last_returnsLastMatchingItem_whenPredicateProvided(): void
    {
        $seq = new LazySequence([1, 2, 3, 4, 5]);
        self::assertSame(4, $seq->last(static fn(int $n): bool => $n % 2 === 0));
    }

    #[Test]
    public function last_throwsRuntimeException_whenNoItemMatchesPredicate(): void
    {
        self::expectException(RuntimeException::class);
        $seq = new LazySequence([1, 3, 5]);
        $seq->last(static fn(int $n): bool => $n % 2 === 0);
    }

    #[Test]
    public function last_throwsUnderflowException_whenSequenceIsEmpty(): void
    {
        self::expectException(UnderflowException::class);
        (new LazySequence([]))->last();
    }

    #[Test]
    public function last_throwsUnderflowException_whenSourceIsEmptyIterable(): void
    {
        self::expectException(UnderflowException::class);
        $empty = (static function (): Generator {
            yield from [];
        })();
        (new LazySequence($empty))->last();
    }

    #[Test]
    public function lastOrNull_returnsLastMatchingItem_whenPredicateProvided(): void
    {
        $seq = new LazySequence([1, 2, 3, 4, 5]);
        self::assertSame(4, $seq->lastOrNull(static fn(int $n): bool => $n % 2 === 0));
    }

    #[Test]
    public function lastOrNull_returnsNull_whenNoItemMatchesPredicate(): void
    {
        $seq = new LazySequence([1, 3, 5]);
        self::assertNull($seq->lastOrNull(static fn(int $n): bool => $n % 2 === 0));
    }

    #[Test]
    public function lastOrNull_returnsNull_whenSequenceIsEmpty(): void
    {
        self::assertNull((new LazySequence([]))->lastOrNull());
    }

    #[Test]
    public function lastOrNull_returnsLastItem_whenPredicateIsNullAndSourceIsNonEmptyArray(): void
    {
        $seq = new LazySequence(self::source_some_words());
        self::assertSame('date', $seq->lastOrNull());
    }

    #[Test]
    public function map_appliesCallbackWithIndex(): void
    {
        $seq = new LazySequence([10, 20, 30]);
        self::assertSame([10, 21, 32], $seq->map(static fn(int $n, int $i): int => $n + $i)->asArray());
    }

    #[Test]
    public function orderBy_sortsAscendingUsingComparer(): void
    {
        $seq = new LazySequence(['bat', 'mud', 'car']);
        self::assertSame(['mud', 'car', 'bat'], $seq->orderBy(self::comparer_last_char())->asArray());
    }

    #[Test]
    public function orderDescBy_sortsDescendingUsingComparer(): void
    {
        $seq = new LazySequence(['bat', 'mud', 'car']);
        self::assertSame(['bat', 'car', 'mud'], $seq->orderDescBy(self::comparer_last_char())->asArray());
    }

    #[Test]
    public function precededBy_prependsOtherSequences(): void
    {
        $seq = new LazySequence([3]);
        self::assertSame([1, 2, 3], $seq->precededBy([1], [2])->asArray());
    }

    #[Test]
    public function reduce_accumulatesWithIndex(): void
    {
        $seq = new LazySequence([1, 2, 3]);
        self::assertSame(
            9,
            $seq->reduce(static fn(int $acc, int $n, int $i): int => $acc + $n + $i, 0)
        );
    }

    #[Test]
    public function reverse_reversesGeneratorSource(): void
    {
        $seq = new LazySequence((static function (): Generator {
            yield 'a';
            yield 'b';
            yield 'c';
        })());
        self::assertSame(['c', 'b', 'a'], $seq->reverse()->asArray());
    }

    #[Test]
    public function reverse_reversesListArraySource(): void
    {
        $seq = new LazySequence([1, 2, 3]);
        self::assertSame([3, 2, 1], $seq->reverse()->asArray());
    }

    #[Test]
    public function shuffle_preservesMultisetOfValues(): void
    {
        $seq = new LazySequence([3, 1, 2, 1]);
        $out = $seq->shuffle()->asArray();
        self::assertCount(4, $out);
        sort($out);
        self::assertSame([1, 1, 2, 3], $out);
    }

    #[Test]
    public function skip_skipsFirstNItems(): void
    {
        $seq = new LazySequence([1, 2, 3, 4]);
        self::assertSame([3, 4], $seq->skip(2)->asArray());
    }

    #[Test]
    public function skip_throwsInvalidArgumentException_whenCountNegative(): void
    {
        self::expectException(InvalidArgumentException::class);
        (new LazySequence([1]))->skip(-1);
    }

    #[Test]
    public function skipLast_dropsTrailingItems(): void
    {
        $seq = new LazySequence([1, 2, 3, 4, 5]);
        self::assertSame([1, 2, 3], $seq->skipLast(2)->asArray());
    }

    #[Test]
    public function skipLast_throwsInvalidArgumentException_whenCountNegative(): void
    {
        self::expectException(InvalidArgumentException::class);
        (new LazySequence([1]))->skipLast(-1);
    }

    #[Test]
    public function slice_returnsRangeFromIndex(): void
    {
        $seq = new LazySequence([10, 20, 30, 40]);
        self::assertSame([20, 30], $seq->slice(1, 2)->asArray());
    }

    #[Test]
    public function slice_returnsEmpty_whenLengthZero(): void
    {
        $seq = new LazySequence([1, 2, 3]);
        self::assertSame([], $seq->slice(0, 0)->asArray());
    }

    #[Test]
    public function slice_throwsInvalidArgumentException_whenIndexNegative(): void
    {
        self::expectException(InvalidArgumentException::class);
        (new LazySequence([1]))->slice(-1, 1);
    }

    #[Test]
    public function slice_throwsInvalidArgumentException_whenLengthNegative(): void
    {
        self::expectException(InvalidArgumentException::class);
        (new LazySequence([1]))->slice(0, -1);
    }

    #[Test]
    public function take_keepsFirstNItems(): void
    {
        $seq = new LazySequence([1, 2, 3, 4]);
        self::assertSame([1, 2], $seq->take(2)->asArray());
    }

    #[Test]
    public function take_returnsEmpty_whenCountZero(): void
    {
        $seq = new LazySequence([1, 2, 3]);
        self::assertSame([], $seq->take(0)->asArray());
    }

    #[Test]
    public function take_throwsInvalidArgumentException_whenCountNegative(): void
    {
        self::expectException(InvalidArgumentException::class);
        (new LazySequence([1]))->take(-1);
    }

    #[Test]
    public function takeLast_keepsFinalNItems(): void
    {
        $seq = new LazySequence([1, 2, 3, 4, 5]);
        self::assertSame([4, 5], $seq->takeLast(2)->asArray());
    }

    #[Test]
    public function takeLast_returnsEmpty_whenCountZero(): void
    {
        $seq = new LazySequence([1, 2, 3]);
        self::assertSame([], $seq->takeLast(0)->asArray());
    }

    #[Test]
    public function takeLast_throwsInvalidArgumentException_whenCountNegative(): void
    {
        self::expectException(InvalidArgumentException::class);
        (new LazySequence([1]))->takeLast(-1);
    }

    #[Test]
    public function then_appendsOtherSequences(): void
    {
        $seq = new LazySequence([1]);
        self::assertSame([1, 2, 3], $seq->then([2], [3])->asArray());
    }

    #[Test]
    public function union_appendsUniqueValuesFromSecondSequence(): void
    {
        $seq = new LazySequence([1, 2, 2]);
        self::assertSame([1, 2, 3], $seq->union([2, 3])->asArray());
    }
}
