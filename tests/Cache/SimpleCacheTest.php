<?php

declare(strict_types=1);

namespace Manychois\PhpStrongTests\Cache;

use ArrayIterator;
use DateInterval;
use Generator;
use Manychois\PhpStrong\Cache\FileCachePool;
use Manychois\PhpStrong\Cache\InvalidArgumentException;
use Manychois\PhpStrong\Cache\MemoryCachePool;
use Manychois\PhpStrong\Cache\SimpleCache;
use Manychois\PhpStrong\Clock\TestClock;
use Override;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use stdClass;

final class SimpleCacheTest extends TestCase
{
    private TestClock $clock;

    /**
     * @return list<array{string}>
     */
    public static function provideInvalidKeys(): array
    {
        return [[''], ['a{b'], ['a}b'], ['a(b'], ['a)b'], ['a/b'], ['a\\b'], ['a@b'], ['a:b']];
    }

    /**
     * @return list<array{mixed}>
     */
    public static function provideRoundTripValues(): array
    {
        return [[5], ['5'], [5.5], [true], [false], [null], [[1, ['a' => 2]]], [new stdClass()]];
    }

    #[Test]
    public function get_returnsTheDefaultForAnUnknownKey(): void
    {
        self::assertSame('fallback', $this->cache()->get('nope', 'fallback'));
        self::assertNull($this->cache()->get('nope'));
    }

    #[Test]
    #[DataProvider('provideRoundTripValues')]
    public function set_thenGet_returnsTheValueWithItsExactType(mixed $value): void
    {
        $cache = $this->cache();

        self::assertTrue($cache->set('k', $value));

        $out = $cache->get('k', 'default-not-used');
        self::assertEquals($value, $out);
        self::assertSame(get_debug_type($value), get_debug_type($out));
    }

    #[Test]
    public function get_returnsAStoredNullRatherThanTheDefault(): void
    {
        $cache = $this->cache();
        $cache->set('k', null);

        self::assertNull($cache->get('k', 'fallback'));
    }

    #[Test]
    public function set_withoutTtlCachesForever(): void
    {
        $cache = $this->cache();
        $cache->set('k', 'v');

        $this->clock->advance('P10Y');

        self::assertSame('v', $cache->get('k'));
    }

    #[Test]
    public function set_withSecondsExpiresAtTheTtl(): void
    {
        $cache = $this->cache();
        $cache->set('k', 'v', 60);

        $this->clock->advance('PT59S');
        self::assertSame('v', $cache->get('k'));

        $this->clock->advance('PT1S');
        self::assertNull($cache->get('k'));
    }

    #[Test]
    public function set_withADateIntervalExpiresAtTheTtl(): void
    {
        $cache = $this->cache();
        $cache->set('k', 'v', new DateInterval('PT2H'));

        $this->clock->advance('PT119M');
        self::assertSame('v', $cache->get('k'));

        $this->clock->advance('PT1M');
        self::assertNull($cache->get('k'));
    }

    #[Test]
    public function set_withAZeroTtlDeletesTheExistingItem(): void
    {
        $cache = $this->cache();
        $cache->set('k', 'old');

        self::assertTrue($cache->set('k', 'new', 0));

        self::assertFalse($cache->has('k'));
    }

    #[Test]
    public function set_withANegativeTtlDeletesTheExistingItem(): void
    {
        $cache = $this->cache();
        $cache->set('k', 'old');

        self::assertTrue($cache->set('k', 'new', -1));

        self::assertFalse($cache->has('k'));
    }

    #[Test]
    public function delete_removesTheKeyAndSucceedsForAnUnknownKey(): void
    {
        $cache = $this->cache();
        $cache->set('k', 'v');

        self::assertTrue($cache->delete('k'));
        self::assertFalse($cache->has('k'));
        self::assertTrue($cache->delete('k'));
    }

    #[Test]
    public function clear_removesEveryKey(): void
    {
        $cache = $this->cache();
        $cache->set('a', 1);
        $cache->set('b', 2);

        self::assertTrue($cache->clear());

        self::assertFalse($cache->has('a'));
        self::assertFalse($cache->has('b'));
    }

    #[Test]
    public function has_reportsWhetherALiveValueExists(): void
    {
        $cache = $this->cache();

        self::assertFalse($cache->has('k'));
        $cache->set('k', 'v', 60);
        self::assertTrue($cache->has('k'));

        $this->clock->advance('PT61S');
        self::assertFalse($cache->has('k'));
    }

    #[Test]
    public function getMultiple_returnsAValueOrTheDefaultPerKey(): void
    {
        $cache = $this->cache();
        $cache->set('a', 1);
        $cache->set('c', 3);

        $out = $cache->getMultiple(['a', 'b', 'c'], 'missing');

        self::assertSame(['a' => 1, 'b' => 'missing', 'c' => 3], $out);
    }

    #[Test]
    public function getMultiple_acceptsATraversable(): void
    {
        $cache = $this->cache();
        $cache->set('a', 1);

        $out = $cache->getMultiple(new ArrayIterator(['a', 'b']));

        self::assertSame(['a' => 1, 'b' => null], $out);
    }

    #[Test]
    public function getMultiple_acceptsAGenerator(): void
    {
        $cache = $this->cache();
        $cache->set('a', 1);

        $out = $cache->getMultiple($this->keys('a', 'b'));

        self::assertSame(['a' => 1, 'b' => null], $out);
    }

    #[Test]
    public function getMultiple_withNoKeysReturnsAnEmptyArray(): void
    {
        self::assertSame([], $this->cache()->getMultiple([]));
    }

    #[Test]
    public function setMultiple_storesEveryPair(): void
    {
        $cache = $this->cache();

        self::assertTrue($cache->setMultiple(['a' => 1, 'b' => 2], 60));

        self::assertSame(['a' => 1, 'b' => 2], $cache->getMultiple(['a', 'b']));

        $this->clock->advance('PT61S');
        self::assertSame(['a' => null, 'b' => null], $cache->getMultiple(['a', 'b']));
    }

    #[Test]
    public function setMultiple_acceptsATraversableAndNumericKeys(): void
    {
        $cache = $this->cache();

        self::assertTrue($cache->setMultiple(new ArrayIterator(['1' => 'one'])));

        self::assertSame('one', $cache->get('1'));
    }

    #[Test]
    public function setMultiple_withAZeroTtlDeletesEveryKey(): void
    {
        $cache = $this->cache();
        $cache->setMultiple(['a' => 1, 'b' => 2]);

        self::assertTrue($cache->setMultiple(['a' => 3, 'b' => 4], 0));

        self::assertFalse($cache->has('a'));
        self::assertFalse($cache->has('b'));
    }

    #[Test]
    public function setMultiple_withNoPairsSucceeds(): void
    {
        self::assertTrue($this->cache()->setMultiple([]));
    }

    #[Test]
    public function deleteMultiple_removesEveryKey(): void
    {
        $cache = $this->cache();
        $cache->setMultiple(['a' => 1, 'b' => 2]);

        self::assertTrue($cache->deleteMultiple(new ArrayIterator(['a', 'b', 'missing'])));

        self::assertFalse($cache->has('a'));
        self::assertFalse($cache->has('b'));
    }

    #[Test]
    public function deleteMultiple_withNoKeysSucceeds(): void
    {
        self::assertTrue($this->cache()->deleteMultiple([]));
    }

    #[Test]
    #[DataProvider('provideInvalidKeys')]
    public function get_rejectsAnInvalidKey(string $key): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->cache()->get($key);
    }

    #[Test]
    #[DataProvider('provideInvalidKeys')]
    public function set_rejectsAnInvalidKey(string $key): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->cache()->set($key, 'v');
    }

    #[Test]
    #[DataProvider('provideInvalidKeys')]
    public function delete_rejectsAnInvalidKey(string $key): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->cache()->delete($key);
    }

    #[Test]
    #[DataProvider('provideInvalidKeys')]
    public function has_rejectsAnInvalidKey(string $key): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->cache()->has($key);
    }

    #[Test]
    public function getMultiple_rejectsTheWholeCallWhenAnyKeyIsInvalid(): void
    {
        $cache = $this->cache();
        $cache->set('a', 1);

        $this->expectException(InvalidArgumentException::class);

        $cache->getMultiple(['a', 'bad:key']);
    }

    #[Test]
    public function getMultiple_rejectsAKeyThatIsNotAString(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->cache()->getMultiple([123]);
    }

    #[Test]
    public function deleteMultiple_rejectsTheWholeCallWhenAnyKeyIsInvalid(): void
    {
        $cache = $this->cache();
        $cache->set('a', 1);

        try {
            $cache->deleteMultiple(['a', 'bad@key']);
            self::fail('Expected an InvalidArgumentException.');
        } catch (InvalidArgumentException) {
            self::assertTrue($cache->has('a'));
        }
    }

    #[Test]
    public function setMultiple_rejectsTheWholeCallWhenAnyKeyIsInvalid(): void
    {
        $cache = $this->cache();

        try {
            $cache->setMultiple(['a' => 1, 'bad/key' => 2]);
            self::fail('Expected an InvalidArgumentException.');
        } catch (InvalidArgumentException) {
            self::assertFalse($cache->has('a'));
        }
    }

    #[Test]
    public function setMultiple_rejectsAKeyThatIsNotAString(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->cache()->setMultiple($this->objectKeyedPairs());
    }

    #[Test]
    public function set_returnsFalseWhenTheUnderlyingPoolCannotStoreTheValue(): void
    {
        $dir = sys_get_temp_dir() . '/php-strong-simple-' . bin2hex(random_bytes(8));
        $cache = new SimpleCache(new FileCachePool($dir, $this->clock));

        try {
            self::assertFalse($cache->set('closure', static fn (): int => 1));
            self::assertFalse($cache->has('closure'));
        } finally {
            self::removeTree($dir);
        }
    }

    #[Test]
    public function adapter_worksOverAFileBackedPool(): void
    {
        $dir = sys_get_temp_dir() . '/php-strong-simple-' . bin2hex(random_bytes(8));

        try {
            $cache = new SimpleCache(new FileCachePool($dir, $this->clock));
            $cache->setMultiple(['a' => 1, 'b' => 2], 60);

            $reread = new SimpleCache(new FileCachePool($dir, $this->clock));
            self::assertSame(['a' => 1, 'b' => 2], $reread->getMultiple(['a', 'b']));

            $this->clock->advance('PT61S');
            $afterExpiry = new SimpleCache(new FileCachePool($dir, $this->clock));
            self::assertSame(['a' => null, 'b' => null], $afterExpiry->getMultiple(['a', 'b']));
        } finally {
            self::removeTree($dir);
        }
    }

    private static function removeTree(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $entries = scandir($dir);
        foreach ($entries === false ? [] : $entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $path = $dir . '/' . $entry;
            if (is_dir($path)) {
                self::removeTree($path);
            } else {
                unlink($path);
            }
        }

        rmdir($dir);
    }

    #[Override]
    protected function setUp(): void
    {
        $this->clock = new TestClock('2026-08-21 00:00:00');
    }

    private function cache(): SimpleCache
    {
        return new SimpleCache(new MemoryCachePool($this->clock));
    }

    /**
     * A Traversable may yield a key of any type, which an array never can.
     *
     * @return Generator<mixed, string>
     */
    private function objectKeyedPairs(): Generator
    {
        yield new stdClass() => 'value';
    }

    /**
     * @return Generator<int, string>
     */
    private function keys(string ...$keys): Generator
    {
        yield from $keys;
    }
}
