<?php

declare(strict_types=1);

namespace Manychois\PhpStrongTests\Collections;

use Generator;
use InvalidArgumentException;
use Manychois\PhpStrong\Collections\DuplicationPolicy;
use Manychois\PhpStrong\Collections\Entry;
use Manychois\PhpStrong\Collections\ReadonlyMap;
use Manychois\PhpStrong\Collections\StringMap;
use OutOfBoundsException;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for StringMap.
 */
final class StringMapTest extends TestCase
{
    /**
     * Empty map with string values (seeds TValue for static analysis).
     *
     * @return StringMap<string>
     */
    private static function empty_string_map_string(DuplicationPolicy $policy = DuplicationPolicy::Overwrite): StringMap
    {
        $map = new StringMap(['@' => ''], $policy);
        $map->remove('@');

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
     * @return Generator<string, string>
     */
    private static function generator_with_duplicate_key(): Generator
    {
        yield 'a' => 'first';
        yield 'b' => 'B';
        yield 'a' => 'second';
    }

    #[Test]
    public function add_addsNewEntry(): void
    {
        $map = self::empty_string_map_string();
        $map->add('k', 'x');
        self::assertSame('x', $map->get('k'));
        self::assertCount(1, $map);
    }

    #[Test]
    public function add_duplicate_with_ignore_leavesFirstValue(): void
    {
        $map = self::empty_string_map_string(DuplicationPolicy::Ignore);
        $map->add('x', 'a');
        $map->add('x', 'b');
        self::assertSame('a', $map->get('x'));
    }

    #[Test]
    public function add_duplicate_with_overwrite_replacesValue(): void
    {
        $map = self::empty_string_map_string(DuplicationPolicy::Overwrite);
        $map->add('x', 'a');
        $map->add('x', 'b');
        self::assertSame('b', $map->get('x'));
    }

    #[Test]
    public function add_duplicate_with_throwError_throws(): void
    {
        $map = self::empty_string_map_string(DuplicationPolicy::ThrowError);
        $map->add('x', 'a');
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Key x already exists');
        $map->add('x', 'b');
    }

    #[Test]
    public function add_invalidKey_throws(): void
    {
        $map = self::empty_string_map_string();
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Key must be a string');
        self::invoke_untyped($map, 'add', [1, 'x']);
    }

    #[Test]
    public function addRange_mergesAllRanges(): void
    {
        $map = new StringMap(['a' => '1']);
        $map->addRange(['b' => '2'], new StringMap(['c' => '3']));
        self::assertSame(['1', '2', '3'], $map->values()->asArray());
        self::assertCount(3, $map);
    }

    #[Test]
    public function arrayConstructor_assignsSource(): void
    {
        $map = new StringMap(['a' => 'A', 'z' => 'Z']);
        self::assertSame('A', $map->get('a'));
        self::assertSame('Z', $map->get('z'));
        self::assertCount(2, $map);
    }

    #[Test]
    public function asArray_returnsBackingArray(): void
    {
        $data = ['p' => 'q', 'r' => 's'];
        $map = new StringMap($data);
        self::assertSame($data, $map->asArray());
    }

    #[Test]
    public function asReadonly_wrapsMapWithSameData(): void
    {
        $map = new StringMap(['k' => 'v']);
        $ro = $map->asReadonly();
        self::assertInstanceOf(ReadonlyMap::class, $ro);
        self::assertSame($map->asArray(), $ro->asArray());
        self::assertSame(DuplicationPolicy::Overwrite, $ro->duplicationPolicy);
    }

    #[Test]
    public function clear_removesAllEntries(): void
    {
        $map = new StringMap(['a' => '1']);
        $map->clear();
        self::assertCount(0, $map);
        self::assertFalse($map->has('a'));
    }

    #[Test]
    public function constructor_defaultDuplicationPolicy_isOverwrite(): void
    {
        $map = new StringMap(['@' => '']);
        self::assertSame(DuplicationPolicy::Overwrite, $map->duplicationPolicy);
    }

    #[Test]
    public function constructor_fromIterable_appliesThrowError_onDuplicate(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Key a already exists');
        new StringMap(self::generator_with_duplicate_key(), DuplicationPolicy::ThrowError);
    }

    #[Test]
    public function constructor_fromIterable_withIgnore_keepsFirstDuplicate(): void
    {
        $map = new StringMap(self::generator_with_duplicate_key(), DuplicationPolicy::Ignore);
        self::assertSame('first', $map->get('a'));
        self::assertSame('B', $map->get('b'));
    }

    #[Test]
    public function constructor_fromIterable_withOverwrite_keepsLastDuplicate(): void
    {
        $map = new StringMap(self::generator_with_duplicate_key(), DuplicationPolicy::Overwrite);
        self::assertSame('second', $map->get('a'));
    }

    #[Test]
    public function emptyMap_hasZeroCount(): void
    {
        $map = new StringMap(['@' => '']);
        $map->clear();
        self::assertCount(0, $map);
    }

    #[Test]
    public function entries_yieldsEntryObjectsWithStringKeys(): void
    {
        $map = new StringMap(['a' => 'A', 'b' => 'B']);
        $list = $map->entries()->asArray();
        self::assertCount(2, $list);
        self::assertContainsOnlyInstancesOf(Entry::class, $list);
        $keys = [];
        foreach ($list as $entry) {
            self::assertIsString($entry->key);
            $keys[] = $entry->key;
        }
        sort($keys);
        self::assertSame(['a', 'b'], $keys);
    }

    #[Test]
    public function flip_swapsKeysAndValues(): void
    {
        $map = new StringMap(['a' => '1', 'b' => '2']);
        $flipped = iterator_to_array($map->flip());
        self::assertSame(['1' => 'a', '2' => 'b'], $flipped);
    }

    #[Test]
    public function foreach_yieldsStringKeys(): void
    {
        $map = new StringMap(['k10' => 'ten']);
        $out = [];
        foreach ($map as $k => $v) {
            self::assertIsString($k);
            $out[$k] = $v;
        }
        self::assertSame(['k10' => 'ten'], $out);
    }

    #[Test]
    public function get_missingKey_throwsOutOfBounds(): void
    {
        $map = self::empty_string_map_string();
        $this->expectException(OutOfBoundsException::class);
        $this->expectExceptionMessage('Key z not found');
        $map->get('z');
    }

    #[Test]
    public function get_returnsValue_whenPresent(): void
    {
        self::assertSame('ok', (new StringMap(['' => 'ok']))->get(''));
    }

    #[Test]
    public function has_invalidKey_throws(): void
    {
        $map = self::empty_string_map_string();
        $this->expectException(InvalidArgumentException::class);
        self::invoke_untyped($map, 'has', [null]);
    }

    #[Test]
    public function has_returnsFalseForMissingKey(): void
    {
        self::assertFalse(self::empty_string_map_string()->has('nope'));
    }

    #[Test]
    public function has_returnsTrueWhenKeyExists(): void
    {
        self::assertTrue((new StringMap(['x' => null]))->has('x'));
    }

    #[Test]
    public function keys_returnsSequenceOfStringKeys(): void
    {
        $map = new StringMap(['b' => 2, 'a' => 1]);
        self::assertSame(['b', 'a'], $map->keys()->asArray());
    }

    #[Test]
    public function nullGet_missingKey_returnsNull(): void
    {
        self::assertNull(self::empty_string_map_string()->nullGet('m'));
    }

    #[Test]
    public function nullGet_returnsValueIncludingNullStored(): void
    {
        $map = new StringMap(['n' => null]);
        self::assertNull($map->nullGet('n'));
        self::assertTrue($map->has('n'));
    }

    #[Test]
    public function offsetExists_invalidOffset_throws(): void
    {
        $map = self::empty_string_map_string();
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Offset must be a string');
        self::invoke_untyped($map, 'offsetExists', [0]);
    }

    #[Test]
    public function offsetGet_missing_throwsOutOfBounds(): void
    {
        $map = self::empty_string_map_string();
        $this->expectException(OutOfBoundsException::class);
        $map->offsetGet('n');
    }

    #[Test]
    public function offsetSet_nullOffset_throws(): void
    {
        $map = self::empty_string_map_string();
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Offset must be a string');
        $map->offsetSet(null, 'x');
    }

    #[Test]
    public function offsetUnset_removesEntry(): void
    {
        $map = new StringMap(['p' => 'P']);
        unset($map['p']);
        self::assertFalse($map->has('p'));
    }

    #[Test]
    public function remove_invalidKey_throws(): void
    {
        $map = self::empty_string_map_string();
        $this->expectException(InvalidArgumentException::class);
        self::invoke_untyped($map, 'remove', [1]);
    }

    #[Test]
    public function remove_returnsFalseWhenKeyMissing(): void
    {
        self::assertFalse(self::empty_string_map_string()->remove('x'));
    }

    #[Test]
    public function remove_returnsTrueAndRemovesWhenPresent(): void
    {
        $map = new StringMap(['a' => '1']);
        self::assertTrue($map->remove('a'));
        self::assertCount(0, $map);
    }

    #[Test]
    public function squareBracketAccess_getAndSet(): void
    {
        $map = self::empty_string_map_string();
        $map['key'] = 'val';
        self::assertTrue(isset($map['key']));
        self::assertSame('val', $map['key']);
    }

    #[Test]
    public function values_returnsSequenceInInsertionOrder(): void
    {
        $map = new StringMap(['b' => 2, 'a' => 1]);
        self::assertSame([2, 1], $map->values()->asArray());
    }
}
