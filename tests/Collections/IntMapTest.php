<?php

declare(strict_types=1);

namespace Manychois\PhpStrongTests\Collections;

use Generator;
use InvalidArgumentException;
use Manychois\PhpStrong\Collections\DuplicationPolicy;
use Manychois\PhpStrong\Collections\Entry;
use Manychois\PhpStrong\Collections\IntMap;
use Manychois\PhpStrong\Collections\ReadonlyMap;
use OutOfBoundsException;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for IntMap.
 */
final class IntMapTest extends TestCase
{
    /**
     * Empty map with string values (seeds TValue for static analysis).
     *
     * @return IntMap<string>
     */
    private static function empty_int_map_string(DuplicationPolicy $policy = DuplicationPolicy::Overwrite): IntMap
    {
        $map = new IntMap([0 => ''], $policy);
        $map->remove(0);

        return $map;
    }

    /**
     * @param array<int, mixed> $args
     */
    private static function invoke_untyped(object $map, string $method, array $args): mixed
    {
        $reflector = new ReflectionMethod($map, $method);

        return $reflector->invoke($map, ...$args);
    }

    /**
     * @return Generator<int, string>
     */
    private static function generator_with_duplicate_key(): Generator
    {
        yield 0 => 'first';
        yield 1 => 'b';
        yield 0 => 'second';
    }

    #[Test]
    public function add_addsNewEntry(): void
    {
        $map = self::empty_int_map_string();
        $map->add(10, 'x');
        self::assertSame('x', $map->get(10));
        self::assertCount(1, $map);
    }

    #[Test]
    public function add_duplicate_with_ignore_leavesFirstValue(): void
    {
        $map = self::empty_int_map_string(DuplicationPolicy::Ignore);
        $map->add(1, 'a');
        $map->add(1, 'b');
        self::assertSame('a', $map->get(1));
    }

    #[Test]
    public function add_duplicate_with_overwrite_replacesValue(): void
    {
        $map = self::empty_int_map_string(DuplicationPolicy::Overwrite);
        $map->add(1, 'a');
        $map->add(1, 'b');
        self::assertSame('b', $map->get(1));
    }

    #[Test]
    public function add_duplicate_with_throwError_throws(): void
    {
        $map = self::empty_int_map_string(DuplicationPolicy::ThrowError);
        $map->add(1, 'a');
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Key 1 already exists');
        $map->add(1, 'b');
    }

    #[Test]
    public function add_invalidKey_throws(): void
    {
        $map = self::empty_int_map_string();
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Key must be an integer');
        self::invoke_untyped($map, 'add', ['1', 'x']);
    }

    #[Test]
    public function addRange_mergesAllRanges(): void
    {
        $map = new IntMap([1 => 'a']);
        $map->addRange([2 => 'b'], new IntMap([3 => 'c']));
        self::assertSame(['a', 'b', 'c'], $map->values()->asArray());
        self::assertCount(3, $map);
    }

    #[Test]
    public function arrayConstructor_assignsSource(): void
    {
        $map = new IntMap([2 => 'two', 5 => 'five']);
        self::assertSame('two', $map->get(2));
        self::assertSame('five', $map->get(5));
        self::assertCount(2, $map);
    }

    #[Test]
    public function asArray_returnsBackingArray(): void
    {
        $data = [0 => 'z', 9 => 'n'];
        $map = new IntMap($data);
        self::assertSame($data, $map->asArray());
    }

    #[Test]
    public function asReadonly_wrapsMapWithSameData(): void
    {
        $map = new IntMap([7 => 's']);
        $ro = $map->asReadonly();
        self::assertInstanceOf(ReadonlyMap::class, $ro);
        self::assertSame($map->asArray(), $ro->asArray());
        self::assertSame(DuplicationPolicy::Overwrite, $ro->duplicationPolicy);
    }

    #[Test]
    public function clear_removesAllEntries(): void
    {
        $map = new IntMap([1 => 'a']);
        $map->clear();
        self::assertCount(0, $map);
        self::assertFalse($map->has(1));
    }

    #[Test]
    public function constructor_defaultDuplicationPolicy_isOverwrite(): void
    {
        $map = new IntMap([0 => '']);
        self::assertSame(DuplicationPolicy::Overwrite, $map->duplicationPolicy);
    }

    #[Test]
    public function constructor_fromIterable_appliesThrowError_onDuplicate(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Key 0 already exists');
        new IntMap(self::generator_with_duplicate_key(), DuplicationPolicy::ThrowError);
    }

    #[Test]
    public function constructor_fromIterable_withIgnore_keepsFirstDuplicate(): void
    {
        $map = new IntMap(self::generator_with_duplicate_key(), DuplicationPolicy::Ignore);
        self::assertSame('first', $map->get(0));
        self::assertSame('b', $map->get(1));
    }

    #[Test]
    public function constructor_fromIterable_withOverwrite_keepsLastDuplicate(): void
    {
        $map = new IntMap(self::generator_with_duplicate_key(), DuplicationPolicy::Overwrite);
        self::assertSame('second', $map->get(0));
    }

    #[Test]
    public function emptyMap_hasZeroCount(): void
    {
        $map = new IntMap([0 => '']);
        $map->clear();
        self::assertCount(0, $map);
    }

    #[Test]
    public function entries_yieldsEntryObjects(): void
    {
        $map = new IntMap([1 => 'one', 2 => 'two']);
        $list = $map->entries()->asArray();
        self::assertCount(2, $list);
        self::assertContainsOnlyInstancesOf(Entry::class, $list);
        $keys = [];
        foreach ($list as $entry) {
            $keys[] = $entry->key;
        }
        sort($keys);
        self::assertSame([1, 2], $keys);
    }

    #[Test]
    public function flip_swapsKeysAndValues(): void
    {
        $map = new IntMap([1 => 'a', 2 => 'b']);
        $flipped = iterator_to_array($map->flip());
        self::assertSame(['a' => 1, 'b' => 2], $flipped);
    }

    #[Test]
    public function foreach_iteratesKeyValuePairs(): void
    {
        $map = new IntMap([10 => 'ten']);
        $out = [];
        foreach ($map as $k => $v) {
            $out[$k] = $v;
        }
        self::assertSame([10 => 'ten'], $out);
    }

    #[Test]
    public function get_missingKey_throwsOutOfBounds(): void
    {
        $map = self::empty_int_map_string();
        $this->expectException(OutOfBoundsException::class);
        $this->expectExceptionMessage('Key 99 not found');
        $map->get(99);
    }

    #[Test]
    public function get_returnsValue_whenPresent(): void
    {
        self::assertSame('ok', (new IntMap([0 => 'ok']))->get(0));
    }

    #[Test]
    public function has_invalidKey_throws(): void
    {
        $map = self::empty_int_map_string();
        $this->expectException(InvalidArgumentException::class);
        self::invoke_untyped($map, 'has', [1.5]);
    }

    #[Test]
    public function has_returnsFalseForMissingKey(): void
    {
        self::assertFalse(self::empty_int_map_string()->has(0));
    }

    #[Test]
    public function has_returnsTrueWhenKeyExists(): void
    {
        self::assertTrue((new IntMap([3 => null]))->has(3));
    }

    #[Test]
    public function keys_returnsSequenceOfIntKeys(): void
    {
        $map = new IntMap([2 => 'b', 1 => 'a']);
        self::assertSame([2, 1], $map->keys()->asArray());
    }

    #[Test]
    public function nullGet_missingKey_returnsNull(): void
    {
        self::assertNull(self::empty_int_map_string()->nullGet(5));
    }

    #[Test]
    public function nullGet_returnsValueIncludingNullStored(): void
    {
        $map = new IntMap([0 => null]);
        self::assertNull($map->nullGet(0));
        self::assertTrue($map->has(0));
    }

    #[Test]
    public function offsetExists_invalidOffset_throws(): void
    {
        $map = self::empty_int_map_string();
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Offset must be an integer');
        self::invoke_untyped($map, 'offsetExists', ['0']);
    }

    #[Test]
    public function offsetGet_missing_throwsOutOfBounds(): void
    {
        $map = self::empty_int_map_string();
        $this->expectException(OutOfBoundsException::class);
        $map->offsetGet(1);
    }

    #[Test]
    public function offsetSet_nullOffset_throws(): void
    {
        $map = self::empty_int_map_string();
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Offset must be an integer');
        $map->offsetSet(null, 'x');
    }

    #[Test]
    public function offsetUnset_removesEntry(): void
    {
        $map = new IntMap([5 => 'p']);
        unset($map[5]);
        self::assertFalse($map->has(5));
    }

    #[Test]
    public function remove_invalidKey_throws(): void
    {
        $map = self::empty_int_map_string();
        $this->expectException(InvalidArgumentException::class);
        self::invoke_untyped($map, 'remove', [true]);
    }

    #[Test]
    public function remove_returnsFalseWhenKeyMissing(): void
    {
        self::assertFalse(self::empty_int_map_string()->remove(0));
    }

    #[Test]
    public function remove_returnsTrueAndRemovesWhenPresent(): void
    {
        $map = new IntMap([1 => 'a']);
        self::assertTrue($map->remove(1));
        self::assertCount(0, $map);
    }

    #[Test]
    public function squareBracketAccess_getAndSet(): void
    {
        $map = self::empty_int_map_string();
        $map[4] = 'four';
        self::assertTrue(isset($map[4]));
        self::assertSame('four', $map[4]);
    }

    #[Test]
    public function values_returnsSequenceInInsertionOrder(): void
    {
        $map = new IntMap([2 => 'b', 1 => 'a']);
        self::assertSame(['b', 'a'], $map->values()->asArray());
    }
}
