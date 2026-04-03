<?php

declare(strict_types=1);

namespace Manychois\PhpStrongTests\Collections;

use Generator;
use InvalidArgumentException;
use Manychois\PhpStrong\Collections\DuplicationPolicy;
use Manychois\PhpStrong\Collections\Entry;
use Manychois\PhpStrong\Collections\ObjectMap;
use Manychois\PhpStrong\Collections\ReadonlyMap;
use OutOfBoundsException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use RuntimeException;
use stdClass;

/**
 * Unit tests for ObjectMap.
 */
final class ObjectMapTest extends TestCase
{
    /**
     * @return ObjectMap<object, string>
     */
    private static function empty_object_map_string(DuplicationPolicy $policy = DuplicationPolicy::Overwrite): ObjectMap
    {
        $key = new stdClass();
        $map = new ObjectMap((static function () use ($key) {
            yield $key => '';
        })(), $policy);
        $map->remove($key);

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
     * @return Generator<object, string>
     */
    private static function generator_with_duplicate_key(): Generator
    {
        $key = new stdClass();
        yield $key => 'first';
        yield new stdClass() => 'B';
        yield $key => 'second';
    }

    #[Test]
    public function add_addsNewEntry(): void
    {
        $map = self::empty_object_map_string();
        $key = new stdClass();
        $map->add($key, 'x');
        self::assertSame('x', $map->get($key));
        self::assertCount(1, $map);
    }

    #[Test]
    public function add_duplicate_with_ignore_leavesFirstValue(): void
    {
        $map = self::empty_object_map_string(DuplicationPolicy::Ignore);
        $key = new stdClass();
        $map->add($key, 'a');
        $map->add($key, 'b');
        self::assertSame('a', $map->get($key));
    }

    #[Test]
    public function add_duplicate_with_overwrite_replacesValue(): void
    {
        $map = self::empty_object_map_string(DuplicationPolicy::Overwrite);
        $key = new stdClass();
        $map->add($key, 'a');
        $map->add($key, 'b');
        self::assertSame('b', $map->get($key));
    }

    #[Test]
    public function add_duplicate_with_throwError_throws(): void
    {
        $map = self::empty_object_map_string(DuplicationPolicy::ThrowError);
        $key = new stdClass();
        $map->add($key, 'a');
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Key already exists');
        $map->add($key, 'b');
    }

    #[Test]
    public function add_invalidKey_throws(): void
    {
        $map = self::empty_object_map_string();
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Key must be an object, type string given');
        self::invoke_untyped($map, 'add', ['k', 'x']);
    }

    #[Test]
    public function addRange_mergesAllRanges(): void
    {
        $k1 = new stdClass();
        $k2 = new stdClass();
        $k3 = new stdClass();
        $map = new ObjectMap((static function () use ($k1) {
            yield $k1 => 'a';
        })());
        $map->addRange(
            (static function () use ($k2) {
                yield $k2 => 'b';
            })(),
            new ObjectMap((static function () use ($k3) {
                yield $k3 => 'c';
            })())
        );
        self::assertSame(['a', 'b', 'c'], $map->values()->asArray());
        self::assertCount(3, $map);
    }

    #[Test]
    public function asArray_throws(): void
    {
        $map = self::empty_object_map_string();
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('ObjectMap cannot be converted to an array.');
        $map->asArray();
    }

    #[Test]
    public function asReadonly_wrapsMapAndDelegatesReads(): void
    {
        $key = new stdClass();
        $map = new ObjectMap((static function () use ($key) {
            yield $key => 'v';
        })());
        $ro = $map->asReadonly();
        self::assertInstanceOf(ReadonlyMap::class, $ro);
        self::assertSame(DuplicationPolicy::Overwrite, $ro->duplicationPolicy);
        self::assertSame('v', $ro->get($key));
        $this->expectException(RuntimeException::class);
        $ro->asArray();
    }

    #[Test]
    public function clear_removesAllEntries(): void
    {
        $key = new stdClass();
        $map = new ObjectMap((static function () use ($key) {
            yield $key => '1';
        })());
        $map->clear();
        self::assertCount(0, $map);
        self::assertFalse($map->has($key));
    }

    #[Test]
    public function constructor_clonesObjectMapStorage(): void
    {
        $k1 = new stdClass();
        $k2 = new stdClass();
        $orig = new ObjectMap((static function () use ($k1, $k2) {
            yield $k1 => 'a';
            yield $k2 => 'b';
        })());
        $copy = new ObjectMap($orig);
        $orig->clear();
        self::assertSame('a', $copy->get($k1));
        self::assertSame('b', $copy->get($k2));
        self::assertCount(2, $copy);
    }

    #[Test]
    public function constructor_defaultDuplicationPolicy_isOverwrite(): void
    {
        $key = new stdClass();
        $map = new ObjectMap((static function () use ($key) {
            yield $key => '';
        })());
        self::assertSame(DuplicationPolicy::Overwrite, $map->duplicationPolicy);
    }

    #[Test]
    public function constructor_defaultWeakKeysAndValues_areFalse(): void
    {
        $key = new stdClass();
        $map = new ObjectMap((static function () use ($key) {
            yield $key => 'x';
        })());
        self::assertFalse($map->isWeakKey);
        self::assertFalse($map->isWeakValue);
    }

    #[Test]
    public function constructor_fromIterable_appliesThrowError_onDuplicate(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Key already exists');
        new ObjectMap(self::generator_with_duplicate_key(), DuplicationPolicy::ThrowError);
    }

    #[Test]
    public function constructor_fromIterable_withIgnore_keepsFirstDuplicate(): void
    {
        $map = new ObjectMap(self::generator_with_duplicate_key(), DuplicationPolicy::Ignore);
        self::assertCount(2, $map);
        self::assertSame(['first', 'B'], $map->values()->asArray());
    }

    #[Test]
    public function constructor_fromIterable_withOverwrite_keepsLastDuplicate(): void
    {
        $map = new ObjectMap(self::generator_with_duplicate_key(), DuplicationPolicy::Overwrite);
        self::assertCount(2, $map);
        self::assertSame(['second', 'B'], $map->values()->asArray());
    }

    #[Test]
    public function emptyMap_hasZeroCount(): void
    {
        $keyDummy = new stdClass();
        $map = new ObjectMap((static function () use ($keyDummy) {
            yield $keyDummy => '';
        })());
        $map->clear();
        self::assertCount(0, $map);
    }

    #[Test]
    public function entries_yieldsEntryObjectsWithObjectKeys(): void
    {
        $k1 = new stdClass();
        $k2 = new stdClass();
        $map = new ObjectMap((static function () use ($k1, $k2) {
            yield $k1 => 'A';
            yield $k2 => 'B';
        })());
        $list = $map->entries()->asArray();
        self::assertCount(2, $list);
        self::assertContainsOnlyInstancesOf(Entry::class, $list);
        $keys = [];
        foreach ($list as $entry) {
            self::assertIsObject($entry->key);
            $keys[] = $entry->key;
        }
        self::assertTrue(in_array($k1, $keys, true));
        self::assertTrue(in_array($k2, $keys, true));
    }

    #[Test]
    public function flip_swapsKeysAndValues_whenValuesAreScalar(): void
    {
        $k1 = new stdClass();
        $k2 = new stdClass();
        $map = new ObjectMap((static function () use ($k1, $k2) {
            yield $k1 => '1';
            yield $k2 => '2';
        })());
        $expected = ['1' => $k1, '2' => $k2];
        self::assertEquals($expected, iterator_to_array($map->flip()));
    }

    #[Test]
    public function foreach_iteratesObjectKeyedPairs(): void
    {
        $key = new stdClass();
        $map = new ObjectMap((static function () use ($key) {
            yield $key => 'ten';
        })());
        $out = [];
        foreach ($map as $k => $v) {
            self::assertIsObject($k);
            $out[spl_object_id($k)] = $v;
        }
        self::assertSame(['ten'], array_values($out));
    }

    #[Test]
    public function get_missingKey_throwsOutOfBounds(): void
    {
        $map = self::empty_object_map_string();
        $missing = new stdClass();
        $this->expectException(OutOfBoundsException::class);
        $this->expectExceptionMessage('Key not found');
        $map->get($missing);
    }

    #[Test]
    public function get_returnsValue_whenPresent(): void
    {
        $key = new stdClass();
        self::assertSame('ok', (new ObjectMap((static function () use ($key) {
            yield $key => 'ok';
        })()))->get($key));
    }

    #[Test]
    public function has_distinguishesIdentity_notEquality(): void
    {
        $a = new stdClass();
        $b = new stdClass();
        $map = new ObjectMap((static function () use ($a) {
            yield $a => 'only-a';
        })());
        self::assertTrue($map->has($a));
        self::assertFalse($map->has($b));
    }

    #[Test]
    public function has_invalidKey_throws(): void
    {
        $map = self::empty_object_map_string();
        $this->expectException(InvalidArgumentException::class);
        self::invoke_untyped($map, 'has', [null]);
    }

    #[Test]
    public function has_returnsFalseForMissingKey(): void
    {
        self::assertFalse(self::empty_object_map_string()->has(new stdClass()));
    }

    #[Test]
    public function has_returnsTrueWhenKeyExists(): void
    {
        $key = new stdClass();
        self::assertTrue((new ObjectMap((static function () use ($key) {
            yield $key => null;
        })()))->has($key));
    }

    #[Test]
    public function keys_returnsSequenceInInsertionOrder(): void
    {
        $k2 = new stdClass();
        $k1 = new stdClass();
        $map = new ObjectMap((static function () use ($k2, $k1) {
            yield $k2 => 'b';
            yield $k1 => 'a';
        })());
        self::assertSame([$k2, $k1], $map->keys()->asArray());
    }

    #[Test]
    public function nullGet_missingKey_returnsNull(): void
    {
        self::assertNull(self::empty_object_map_string()->nullGet(new stdClass()));
    }

    #[Test]
    public function nullGet_returnsNullForStoredNull(): void
    {
        $key = new stdClass();
        $map = new ObjectMap((static function () use ($key) {
            yield $key => null;
        })());
        self::assertNull($map->nullGet($key));
        self::assertTrue($map->has($key));
    }

    #[Test]
    public function offsetExists_invalidOffset_throws(): void
    {
        $map = self::empty_object_map_string();
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Offset must be an object, type int given');
        self::invoke_untyped($map, 'offsetExists', [0]);
    }

    #[Test]
    public function offsetGet_missing_throwsOutOfBounds(): void
    {
        $map = self::empty_object_map_string();
        $this->expectException(OutOfBoundsException::class);
        $map->offsetGet(new stdClass());
    }

    #[Test]
    public function offsetSet_invalidOffset_throws(): void
    {
        $map = self::empty_object_map_string();
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Offset must be an object, type null given');
        $map->offsetSet(null, 'x');
    }

    #[Test]
    public function offsetUnset_removesEntry(): void
    {
        $key = new stdClass();
        $map = new ObjectMap((static function () use ($key) {
            yield $key => 'P';
        })());
        unset($map[$key]);
        self::assertFalse($map->has($key));
    }

    #[Test]
    public function remove_invalidKey_throws(): void
    {
        $map = self::empty_object_map_string();
        $this->expectException(InvalidArgumentException::class);
        self::invoke_untyped($map, 'remove', [1]);
    }

    #[Test]
    public function remove_returnsFalseWhenKeyMissing(): void
    {
        self::assertFalse(self::empty_object_map_string()->remove(new stdClass()));
    }

    #[Test]
    public function remove_returnsTrueAndRemovesWhenPresent(): void
    {
        $key = new stdClass();
        $map = new ObjectMap((static function () use ($key) {
            yield $key => '1';
        })());
        self::assertTrue($map->remove($key));
        self::assertCount(0, $map);
    }

    #[Test]
    public function squareBracketAccess_getAndSet(): void
    {
        $map = self::empty_object_map_string();
        $key = new stdClass();
        $map[$key] = 'val';
        self::assertTrue(isset($map[$key]));
        self::assertSame('val', $map[$key]);
    }

    #[Test]
    public function values_returnsSequenceInInsertionOrder(): void
    {
        $k2 = new stdClass();
        $k1 = new stdClass();
        $map = new ObjectMap((static function () use ($k2, $k1) {
            yield $k2 => 'b';
            yield $k1 => 'a';
        })());
        self::assertSame(['b', 'a'], $map->values()->asArray());
    }

    #[Test]
    public function weakKey_afterGcCountDrops(): void
    {
        $map = new ObjectMap([], DuplicationPolicy::Overwrite, true, false);
        $key = new stdClass();
        $map->add($key, 'x');
        self::assertCount(1, $map);
        unset($key);
        gc_collect_cycles();
        self::assertCount(0, $map);
    }

    #[Test]
    public function weakValue_afterGc_getThrows(): void
    {
        $key = new stdClass();
        $map = new ObjectMap([], DuplicationPolicy::Overwrite, false, true);
        $value = new stdClass();
        $map->add($key, $value);
        unset($value);
        gc_collect_cycles();
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Value has been garbage collected');
        $map->get($key);
    }

    #[Test]
    public function weakValue_afterGc_hasFalseAndNullGet(): void
    {
        $key = new stdClass();
        $map = new ObjectMap([], DuplicationPolicy::Overwrite, false, true);
        $value = new stdClass();
        $map->add($key, $value);
        unset($value);
        gc_collect_cycles();
        self::assertFalse($map->has($key));
        self::assertNull($map->nullGet($key));
    }

    #[Test]
    public function weakValue_defaultsPreserveStrongValues(): void
    {
        $key = new stdClass();
        $map = new ObjectMap([], DuplicationPolicy::Overwrite, false, false);
        $value = new stdClass();
        $map->add($key, $value);
        unset($value);
        gc_collect_cycles();
        self::assertTrue($map->has($key));
        self::assertIsObject($map->get($key));
    }

    #[Test]
    public function weakKey_add_duplicate_with_ignore_keepsFirst(): void
    {
        $map = new ObjectMap([], DuplicationPolicy::Ignore, true, false);
        $key = new stdClass();
        $map->add($key, 'a');
        $map->add($key, 'b');
        self::assertSame('a', $map->get($key));
    }

    #[Test]
    public function weakKey_add_duplicate_with_overwrite_replaces(): void
    {
        $map = new ObjectMap([], DuplicationPolicy::Overwrite, true, false);
        $key = new stdClass();
        $map->add($key, 'a');
        $map->add($key, 'b');
        self::assertSame('b', $map->get($key));
    }

    #[Test]
    public function weakKey_add_duplicate_with_throwError_throws(): void
    {
        $map = new ObjectMap([], DuplicationPolicy::ThrowError, true, false);
        $key = new stdClass();
        $map->add($key, 'a');
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Key already exists');
        $map->add($key, 'b');
    }

    #[Test]
    public function weakKey_foreach_iteratesEntries(): void
    {
        $map = new ObjectMap([], DuplicationPolicy::Overwrite, true, false);
        $k = new stdClass();
        $map->add($k, 'v');
        $seen = [];
        foreach ($map as $key => $value) {
            $seen[spl_object_id($key)] = $value;
        }
        self::assertSame(['v'], array_values($seen));
    }

    #[Test]
    public function weakKey_remove_returnsTrueWhenPresent(): void
    {
        $map = new ObjectMap([], DuplicationPolicy::Overwrite, true, false);
        $key = new stdClass();
        $map->add($key, 'x');
        self::assertTrue($map->remove($key));
        self::assertCount(0, $map);
    }

    #[Test]
    public function weakKey_remove_returnsFalseWhenKeyMissing(): void
    {
        $map = new ObjectMap([], DuplicationPolicy::Overwrite, true, false);
        self::assertFalse($map->remove(new stdClass()));
    }

    #[Test]
    public function weakKey_weakValue_afterGc_get_unsets_key_and_throws(): void
    {
        $key = new stdClass();
        $map = new ObjectMap([], DuplicationPolicy::Overwrite, true, true);
        $value = new stdClass();
        $map->add($key, $value);
        unset($value);
        gc_collect_cycles();
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Value has been garbage collected');
        $map->get($key);
    }

    #[Test]
    public function weakKey_values_and_keys_match_strong_key_semantics(): void
    {
        $k2 = new stdClass();
        $k1 = new stdClass();
        $map = new ObjectMap([], DuplicationPolicy::Overwrite, true, false);
        $map->add($k2, 'b');
        $map->add($k1, 'a');
        self::assertSame([$k2, $k1], $map->keys()->asArray());
        self::assertSame(['b', 'a'], $map->values()->asArray());
    }

    #[Test]
    public function nullGet_returns_null_when_weak_value_collected_without_prior_has(): void
    {
        $key = new stdClass();
        $map = new ObjectMap([], DuplicationPolicy::Overwrite, false, true);
        $value = new stdClass();
        $map->add($key, $value);
        unset($value);
        gc_collect_cycles();
        self::assertNull($map->nullGet($key));
    }

    #[Test]
    public function iterator_finally_removes_dead_weak_value_entries_on_strong_key_map(): void
    {
        $keyLive = new stdClass();
        $keyDead = new stdClass();
        $map = new ObjectMap([], DuplicationPolicy::Overwrite, false, true);
        $vDead = new stdClass();
        $vLive = new stdClass();
        $map->add($keyDead, $vDead);
        $map->add($keyLive, $vLive);
        unset($vDead);
        gc_collect_cycles();
        foreach ($map->getIterator() as $k => $v) {
            self::assertSame($keyLive, $k);
            self::assertSame($vLive, $v);
            break;
        }
        self::assertFalse($map->has($keyDead));
    }

    #[Test]
    public function weakKey_iterator_finally_removes_dead_weak_value_entries(): void
    {
        $keyLive = new stdClass();
        $keyDead = new stdClass();
        $map = new ObjectMap([], DuplicationPolicy::Overwrite, true, true);
        $vDead = new stdClass();
        $vLive = new stdClass();
        $map->add($keyDead, $vDead);
        $map->add($keyLive, $vLive);
        unset($vDead);
        gc_collect_cycles();
        $ids = [];
        foreach ($map->getIterator() as $k => $v) {
            $ids[] = spl_object_id($k);
            self::assertIsObject($v);
        }
        self::assertContains(spl_object_id($keyLive), $ids);
        self::assertFalse($map->has($keyDead));
    }
}
