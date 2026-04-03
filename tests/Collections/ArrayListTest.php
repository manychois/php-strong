<?php

declare(strict_types=1);

namespace Manychois\PhpStrongTests\Collections;

use BadMethodCallException;
use Generator;
use InvalidArgumentException;
use Manychois\PhpStrong\Collections\ArrayList;
use OutOfBoundsException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Unit tests for {@see ArrayList}.
 */
final class ArrayListTest extends TestCase
{
    /**
     * @return Generator<int, int>
     */
    private static function generator_of_integers(): Generator
    {
        yield 10;
        yield 20;
        yield 30;
    }

    #[Test]
    public function add_appends_items_in_order(): void
    {
        $list = new ArrayList([1]);
        $list->add(2, 3);
        self::assertSame([1, 2, 3], $list->asArray());
    }

    #[Test]
    public function addRange_appends_multiple_ranges(): void
    {
        $list = new ArrayList([]);
        $list->addRange([1, 2], [3], []);
        self::assertSame([1, 2, 3], $list->asArray());
    }

    #[Test]
    public function asArray_return_value_is_safe_to_mutate_without_affecting_list(): void
    {
        $list = new ArrayList([1, 2]);
        $buf = $list->asArray();
        $buf[] = 3;
        self::assertSame([1, 2], $list->asArray());
        self::assertSame([1, 2, 3], $buf);
    }

    #[Test]
    public function asList_initially_matches_source_until_either_list_mutates(): void
    {
        $list = new ArrayList([1, 2]);
        $alias = $list->asList();
        self::assertSame([1, 2], $alias->asArray());
        $list->add(3);
        self::assertSame([1, 2, 3], $list->asArray());
        self::assertSame([1, 2], $alias->asArray());
    }

    #[Test]
    public function asReadonly_returns_snapshot_not_updated_by_later_mutations(): void
    {
        $list = new ArrayList(['a', 'b']);
        $ro = $list->asReadonly();
        $list->add('c');
        self::assertSame(['a', 'b'], $ro->asArray());
        self::assertSame(['a', 'b', 'c'], $list->asArray());
    }

    #[Test]
    public function at_supports_negative_indices(): void
    {
        $list = new ArrayList(['x', 'y', 'z']);
        self::assertSame('z', $list->at(-1));
        self::assertSame('y', $list->at(-2));
    }

    #[Test]
    public function at_throws_when_index_out_of_bounds(): void
    {
        $list = new ArrayList([1, 2]);
        $this->expectException(OutOfBoundsException::class);
        $list->at(2);
    }

    #[Test]
    public function clear_removes_all_elements(): void
    {
        $list = new ArrayList([1]);
        $list->clear();
        self::assertTrue($list->isEmpty());
        self::assertCount(0, $list);
    }

    #[Test]
    public function constructor_accepts_associative_array_using_values_in_order(): void
    {
        $list = new ArrayList(['a' => 1, 'b' => 2]);
        self::assertSame([1, 2], $list->asArray());
    }

    #[Test]
    public function constructor_accepts_empty_iterable(): void
    {
        $list = new ArrayList([]);
        self::assertTrue($list->isEmpty());
    }

    #[Test]
    public function constructor_materializes_generator(): void
    {
        $list = new ArrayList(self::generator_of_integers());
        self::assertSame([10, 20, 30], $list->asArray());
    }

    #[Test]
    public function constructor_preserves_list_array_order(): void
    {
        $list = new ArrayList([3, 1, 4]);
        self::assertSame([3, 1, 4], $list->asArray());
    }

    #[Test]
    public function contains_uses_strict_comparison(): void
    {
        $list = new ArrayList([1, 2, true, 'extra']);
        self::assertTrue($list->contains(1));
        self::assertFalse($list->contains('1'));
        self::assertTrue($list->contains(true));
    }

    #[Test]
    public function findIndex_and_findLastIndex_respect_start_normalisation(): void
    {
        $list = new ArrayList([2, 4, 4, 6]);
        self::assertSame(1, $list->findIndex(static fn(int $n): bool => $n % 4 === 0, 1));
        self::assertSame(2, $list->findLastIndex(static fn(int $n): bool => $n === 4, -1));
        self::assertSame(-1, $list->findIndex(static fn(int $n): bool => $n === 99));
    }

    #[Test]
    public function findLastIndex_returns_negative_one_when_no_match(): void
    {
        $list = new ArrayList([1, 2, 3]);
        self::assertSame(-1, $list->findLastIndex(static fn(int $n): bool => $n > 10));
    }

    #[Test]
    public function getIterator_yields_sequential_integer_keys(): void
    {
        $list = new ArrayList(['p', 'q']);
        $pairs = iterator_to_array($list->getIterator());
        self::assertSame([0 => 'p', 1 => 'q'], $pairs);
    }

    #[Test]
    public function indexOf_and_lastIndexOf_use_strict_equality_with_start(): void
    {
        $list = new ArrayList([1, 2, 1, 2, 1]);
        self::assertSame(2, $list->indexOf(1, 1));
        self::assertSame(2, $list->lastIndexOf(1, 3));
        self::assertSame(-1, $list->indexOf('1'));
    }

    #[Test]
    public function lastIndexOf_returns_negative_one_when_item_absent_from_start_window(): void
    {
        $list = new ArrayList([1, 2, 3]);
        self::assertSame(-1, $list->lastIndexOf(9, -1));
    }

    #[Test]
    public function insert_on_empty_list_at_zero_prepends_or_inserts(): void
    {
        $list = new ArrayList([]);
        $list->insert(0, 'first');
        self::assertSame(['first'], $list->asArray());
    }

    #[Test]
    public function insert_at_end_appends(): void
    {
        $list = new ArrayList([1, 2]);
        $list->insert(2, 3);
        self::assertSame([1, 2, 3], $list->asArray());
    }

    #[Test]
    public function insert_supports_negative_insert_position_as_append_relative_to_end(): void
    {
        $list = new ArrayList([1, 2, 3]);
        $list->insert(-1, 9);
        self::assertSame([1, 2, 3, 9], $list->asArray());
    }

    #[Test]
    public function insertRange_merges_ranges_at_index(): void
    {
        $list = new ArrayList(['a', 'd']);
        $list->insertRange(1, ['b', 'c']);
        self::assertSame(['a', 'b', 'c', 'd'], $list->asArray());
    }

    #[Test]
    public function insert_throws_when_index_past_end(): void
    {
        $list = new ArrayList([1, 2]);
        $this->expectException(OutOfBoundsException::class);
        $list->insert(3, 9);
    }

    #[Test]
    public function map_returns_lazy_sequence(): void
    {
        $list = new ArrayList([1, 2]);
        $mapped = $list->map(static fn(int $n): int => $n * 2);
        self::assertSame([2, 4], $mapped->asArray());
    }

    #[Test]
    public function offsetExists_accepts_negative_indices_within_range(): void
    {
        $list = new ArrayList([10, 20]);
        self::assertTrue(isset($list[-1]));
        self::assertFalse(isset($list[-3]));
    }

    #[Test]
    public function offsetExists_throws_for_non_integer_offset(): void
    {
        $list = new ArrayList([1]);
        $this->expectException(InvalidArgumentException::class);
        $method = new ReflectionMethod(ArrayList::class, 'offsetExists');
        $method->invoke($list, '0');
    }

    #[Test]
    public function offsetGet_normalises_negative_index(): void
    {
        $list = new ArrayList([7, 8, 9]);
        self::assertSame(9, $list[-1]);
    }

    #[Test]
    public function offsetGet_throws_for_non_integer_offset(): void
    {
        $list = new ArrayList([1]);
        $this->expectException(InvalidArgumentException::class);
        $method = new ReflectionMethod(ArrayList::class, 'offsetGet');
        $method->invoke($list, '0');
    }

    #[Test]
    public function offsetSet_with_null_appends(): void
    {
        $list = new ArrayList([1]);
        $list[] = 2;
        self::assertSame([1, 2], $list->asArray());
    }

    #[Test]
    public function offsetSet_at_count_appends(): void
    {
        $list = new ArrayList([4, 5]);
        $list[2] = 6;
        self::assertSame([4, 5, 6], $list->asArray());
    }

    #[Test]
    public function offsetSet_replaces_when_negative_offset_normalises_to_valid_index(): void
    {
        $list = new ArrayList([10, 20, 30]);
        $list[-2] = 99;
        self::assertSame([10, 99, 30], $list->asArray());
    }

    #[Test]
    public function offsetSet_throws_for_non_integer_offset(): void
    {
        $list = new ArrayList([1]);
        $this->expectException(InvalidArgumentException::class);
        $method = new ReflectionMethod(ArrayList::class, 'offsetSet');
        $method->invoke($list, '0', 2);
    }

    #[Test]
    public function offsetSet_throws_when_normalized_negative_index_too_low(): void
    {
        $list = new ArrayList([1, 2]);
        $this->expectException(OutOfBoundsException::class);
        $list[-4] = 0;
    }

    #[Test]
    public function offsetSet_throws_when_index_strictly_greater_than_length(): void
    {
        $list = new ArrayList([7, 8]);
        $this->expectException(OutOfBoundsException::class);
        $list[3] = 9;
    }

    #[Test]
    public function offsetSet_negative_one_past_start_unshifts(): void
    {
        $list = new ArrayList([1, 2]);
        $list[-3] = 0;
        self::assertSame([0, 1, 2], $list->asArray());
    }

    #[Test]
    public function offsetUnset_noops_when_out_of_bounds(): void
    {
        $list = new ArrayList([1]);
        unset($list[99]);
        self::assertSame([1], $list->asArray());
    }

    #[Test]
    public function offsetUnset_removes_at_index(): void
    {
        $list = new ArrayList([1, 2, 3]);
        unset($list[1]);
        self::assertSame([1, 3], $list->asArray());
    }

    #[Test]
    public function offsetUnset_supports_negative_index(): void
    {
        $list = new ArrayList([1, 2, 3]);
        unset($list[-1]);
        self::assertSame([1, 2], $list->asArray());
    }

    #[Test]
    public function offsetUnset_throws_for_non_integer_offset(): void
    {
        $list = new ArrayList([1]);
        $this->expectException(InvalidArgumentException::class);
        $method = new ReflectionMethod(ArrayList::class, 'offsetUnset');
        $method->invoke($list, 1.5);
    }

    #[Test]
    public function remove_removes_first_match_only(): void
    {
        $list = new ArrayList([1, 2, 1]);
        self::assertTrue($list->remove(1));
        self::assertSame([2, 1], $list->asArray());
        self::assertFalse($list->remove(9));
    }

    #[Test]
    public function removeAll_collects_removed_in_order_and_mutates_list(): void
    {
        $list = new ArrayList([1, 2, 3, 4]);
        $removed = $list->removeAll(static fn(int $n): bool => $n % 2 === 0);
        self::assertSame([1, 3], $list->asArray());
        self::assertSame([2, 4], $removed->asArray());
    }

    #[Test]
    public function removeAt_deduplicates_indices_and_removes_from_end_first(): void
    {
        $list = new ArrayList(['a', 'b', 'c', 'd']);
        $count = $list->removeAt(-1, 0, 0, 1);
        self::assertSame(3, $count);
        self::assertSame(['c'], $list->asArray());
    }

    #[Test]
    public function reverse_returns_new_list_with_reversed_order(): void
    {
        $list = new ArrayList([1, 2, 3]);
        $rev = $list->reverse();
        self::assertSame([1, 2, 3], $list->asArray());
        self::assertSame([3, 2, 1], $rev->asArray());
    }

    #[Test]
    public function set_replaces_at_negative_index(): void
    {
        $list = new ArrayList(['a', 'b']);
        $list->set(-1, 'z');
        self::assertSame(['a', 'z'], $list->asArray());
    }

    #[Test]
    public function set_throws_when_index_equals_length(): void
    {
        $list = new ArrayList([1, 2]);
        $this->expectException(OutOfBoundsException::class);
        $list->set(2, 3);
    }

    #[Test]
    public function shuffle_returns_permutation_of_same_elements(): void
    {
        $list = new ArrayList([1, 2, 3, 4, 5]);
        $shuffled = $list->shuffle();
        self::assertSame([1, 2, 3, 4, 5], $list->asArray());
        self::assertCount(5, $shuffled);
        self::assertEqualsCanonicalizing([1, 2, 3, 4, 5], $shuffled->asArray());
    }

    #[Test]
    public function slice_delegates_to_lazy_sequence(): void
    {
        $list = new ArrayList([10, 20, 30, 40]);
        self::assertSame([20, 30], $list->slice(1, 2)->asArray());
    }

    #[Test]
    public function readonly_from_asReadonly_throws_on_array_assignment(): void
    {
        $list = new ArrayList([1]);
        $ro = $list->asReadonly();
        $this->expectException(BadMethodCallException::class);
        $ro[] = 2;
    }
}
