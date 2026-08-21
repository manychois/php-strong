<?php

declare(strict_types=1);

namespace Manychois\PhpStrongTests\Cache;

use Manychois\PhpStrong\Cache\CacheItem;
use Manychois\PhpStrong\Cache\InvalidArgumentException;
use Manychois\PhpStrong\Cache\MemoryCachePool;
use Manychois\PhpStrong\Clock\TestClock;
use Override;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use stdClass;

final class MemoryCachePoolTest extends TestCase
{
    private TestClock $clock;

    /**
     * @return list<array{string}>
     */
    public static function provideInvalidKeys(): array
    {
        return [[''], ['a{b'], ['a}b'], ['a(b'], ['a)b'], ['a/b'], ['a\\b'], ['a@b'], ['a:b']];
    }

    #[Test]
    public function construct_defaultsToAUtcClock(): void
    {
        $pool = new MemoryCachePool();

        self::assertTrue($pool->save($pool->getItem('k')->set('v')->expiresAfter(60)));
        self::assertTrue($pool->hasItem('k'));
    }

    #[Test]
    #[DataProvider('provideInvalidKeys')]
    public function getItem_rejectsAnInvalidKey(string $key): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->pool()->getItem($key);
    }

    #[Test]
    #[DataProvider('provideInvalidKeys')]
    public function hasItem_rejectsAnInvalidKey(string $key): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->pool()->hasItem($key);
    }

    #[Test]
    #[DataProvider('provideInvalidKeys')]
    public function deleteItem_rejectsAnInvalidKey(string $key): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->pool()->deleteItem($key);
    }

    #[Test]
    public function getItem_returnsAMissForAnUnknownKey(): void
    {
        $item = $this->pool()->getItem('nope');

        self::assertInstanceOf(CacheItem::class, $item);
        self::assertSame('nope', $item->getKey());
        self::assertFalse($item->isHit());
        self::assertNull($item->get());
    }

    #[Test]
    public function save_thenGetItem_returnsTheStoredValue(): void
    {
        $pool = $this->pool();

        self::assertTrue($pool->save($pool->getItem('user.1')->set(['name' => 'Ada'])));

        $reread = $pool->getItem('user.1');
        self::assertTrue($reread->isHit());
        self::assertSame(['name' => 'Ada'], $reread->get());
    }

    #[Test]
    public function save_storesFalseAsAValue(): void
    {
        $pool = $this->pool();
        $pool->save($pool->getItem('flag')->set(false));

        $reread = $pool->getItem('flag');

        self::assertTrue($reread->isHit());
        self::assertFalse($reread->get());
    }

    #[Test]
    public function save_keepsTheExpiryOnTheRetrievedItem(): void
    {
        $pool = $this->pool();
        $pool->save($pool->getItem('k')->set('v')->expiresAfter(60));

        $expiry = $pool->getItem('k')->getExpiry();

        self::assertNotNull($expiry);
        self::assertSame($this->clock->now()->getTimestamp() + 60, $expiry->getTimestamp());
    }

    #[Test]
    public function getItem_storesTheValueByReference(): void
    {
        $pool = $this->pool();
        $value = new stdClass();
        $value->n = 0;
        $pool->save($pool->getItem('k')->set($value));

        $value->n = 1;

        $retrieved = $pool->getItem('k')->get();
        self::assertInstanceOf(stdClass::class, $retrieved);
        self::assertSame($value, $retrieved);
        self::assertSame(1, $retrieved->n);
    }

    #[Test]
    public function getItem_missesOnceTheItemHasExpired(): void
    {
        $pool = $this->pool();
        $pool->save($pool->getItem('k')->set('v')->expiresAfter(60));

        $this->clock->advance('PT61S');

        self::assertFalse($pool->getItem('k')->isHit());
    }

    #[Test]
    public function getItem_stillHitsOneSecondBeforeExpiry(): void
    {
        $pool = $this->pool();
        $pool->save($pool->getItem('k')->set('v')->expiresAfter(60));

        $this->clock->advance('PT59S');

        self::assertTrue($pool->getItem('k')->isHit());
    }

    #[Test]
    public function getItem_missesExactlyAtTheExpiryMoment(): void
    {
        $pool = $this->pool();
        $pool->save($pool->getItem('k')->set('v')->expiresAfter(60));

        $this->clock->advance('PT60S');

        self::assertFalse($pool->getItem('k')->isHit());
    }

    #[Test]
    public function save_withAnAlreadyExpiredItemStoresNothingAndDropsTheOldEntry(): void
    {
        $pool = $this->pool();
        $pool->save($pool->getItem('k')->set('old'));

        self::assertTrue($pool->save($pool->getItem('k')->set('new')->expiresAfter(-1)));

        self::assertFalse($pool->getItem('k')->isHit());
    }

    #[Test]
    public function save_acceptsAForeignCacheItemAndStoresItWithoutExpiry(): void
    {
        $pool = $this->pool();

        self::assertTrue($pool->save(new ForeignCacheItem('foreign', 'value')));

        $reread = $pool->getItem('foreign');
        self::assertTrue($reread->isHit());
        self::assertSame('value', $reread->get());
        self::assertNull($reread->getExpiry());
    }

    #[Test]
    public function getItem_returnsAFreshItemEachTimeSoMutationDoesNotLeak(): void
    {
        $pool = $this->pool();
        $pool->save($pool->getItem('k')->set('stored'));

        $first = $pool->getItem('k');
        $first->set('mutated');
        $second = $pool->getItem('k');

        self::assertNotSame($first, $second);
        self::assertSame('stored', $second->get());
    }

    #[Test]
    public function hasItem_reflectsWhetherALiveEntryExists(): void
    {
        $pool = $this->pool();

        self::assertFalse($pool->hasItem('k'));
        $pool->save($pool->getItem('k')->set('v')->expiresAfter(60));
        self::assertTrue($pool->hasItem('k'));

        $this->clock->advance('PT61S');
        self::assertFalse($pool->hasItem('k'));
    }

    #[Test]
    public function deleteItem_dropsTheEntryAndReturnsTrueForAMissingKey(): void
    {
        $pool = $this->pool();
        $pool->save($pool->getItem('k')->set('v'));

        self::assertTrue($pool->deleteItem('k'));
        self::assertFalse($pool->getItem('k')->isHit());
        self::assertTrue($pool->deleteItem('k'));
    }

    #[Test]
    public function getItems_returnsAnItemPerRequestedKeyInOrder(): void
    {
        $pool = $this->pool();
        $pool->save($pool->getItem('a')->set(1));
        $pool->save($pool->getItem('c')->set(3));

        $items = $pool->getItems(['a', 'b', 'c']);

        self::assertSame(['a', 'b', 'c'], array_keys($items));
        self::assertSame(1, $items['a']->get());
        self::assertTrue($items['a']->isHit());
        self::assertFalse($items['b']->isHit());
        self::assertSame(3, $items['c']->get());
    }

    #[Test]
    public function getItems_withNoKeysReturnsAnEmptyArray(): void
    {
        self::assertSame([], $this->pool()->getItems());
    }

    #[Test]
    public function getItems_rejectsTheWholeCallWhenAnyKeyIsInvalid(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->pool()->getItems(['a', 'bad:key']);
    }

    #[Test]
    public function deleteItems_removesEveryKey(): void
    {
        $pool = $this->pool();
        $pool->save($pool->getItem('a')->set(1));
        $pool->save($pool->getItem('b')->set(2));

        self::assertTrue($pool->deleteItems(['a', 'b', 'missing']));
        self::assertFalse($pool->hasItem('a'));
        self::assertFalse($pool->hasItem('b'));
    }

    #[Test]
    public function deleteItems_rejectsTheWholeCallWhenAnyKeyIsInvalid(): void
    {
        $pool = $this->pool();
        $pool->save($pool->getItem('a')->set(1));

        try {
            $pool->deleteItems(['a', 'bad@key']);
            self::fail('Expected an InvalidArgumentException.');
        } catch (InvalidArgumentException) {
            self::assertTrue($pool->hasItem('a'));
        }
    }

    #[Test]
    public function clear_removesEveryStoredItemAndPendingDeferredItem(): void
    {
        $pool = $this->pool();
        $pool->save($pool->getItem('a')->set(1));
        $pool->saveDeferred($pool->getItem('b')->set(2));

        self::assertTrue($pool->clear());

        self::assertFalse($pool->hasItem('a'));
        self::assertFalse($pool->hasItem('b'));
        self::assertTrue($pool->commit());
        self::assertFalse($pool->hasItem('b'));
    }

    #[Test]
    public function saveDeferred_makesTheItemVisibleBeforeCommit(): void
    {
        $pool = $this->pool();

        self::assertTrue($pool->saveDeferred($pool->getItem('a')->set('later')));

        self::assertTrue($pool->hasItem('a'));
        self::assertTrue($pool->getItem('a')->isHit());
        self::assertSame('later', $pool->getItem('a')->get());
    }

    #[Test]
    public function saveDeferred_keepsTheExpiryOfTheDeferredItem(): void
    {
        $pool = $this->pool();
        $pool->saveDeferred($pool->getItem('a')->set('later')->expiresAfter(60));

        $expiry = $pool->getItem('a')->getExpiry();

        self::assertNotNull($expiry);
        self::assertSame($this->clock->now()->getTimestamp() + 60, $expiry->getTimestamp());
    }

    #[Test]
    public function saveDeferred_rejectsAnInvalidKey(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->pool()->saveDeferred(new ForeignCacheItem('bad/key', 'v'));
    }

    #[Test]
    public function commit_storesEveryDeferredItemAndEmptiesTheQueue(): void
    {
        $pool = $this->pool();
        $pool->saveDeferred($pool->getItem('a')->set(1));
        $pool->saveDeferred($pool->getItem('b')->set(2));

        self::assertTrue($pool->commit());

        self::assertSame(1, $pool->getItem('a')->get());
        self::assertSame(2, $pool->getItem('b')->get());

        $pool->deleteItem('a');
        self::assertTrue($pool->commit());
        self::assertFalse($pool->hasItem('a'));
    }

    #[Test]
    public function commit_deletesADeferredItemThatIsAlreadyExpired(): void
    {
        $pool = $this->pool();
        $pool->save($pool->getItem('a')->set('old'));
        $pool->saveDeferred($pool->getItem('a')->set('new')->expiresAfter(-1));

        self::assertTrue($pool->commit());

        self::assertFalse($pool->hasItem('a'));
    }

    #[Test]
    public function deleteItem_dropsAPendingDeferredItem(): void
    {
        $pool = $this->pool();
        $pool->saveDeferred($pool->getItem('a')->set(1));

        self::assertTrue($pool->deleteItem('a'));

        self::assertFalse($pool->hasItem('a'));
        self::assertTrue($pool->commit());
        self::assertFalse($pool->hasItem('a'));
    }

    #[Test]
    public function save_replacesAPendingDeferredItemForTheSameKey(): void
    {
        $pool = $this->pool();
        $pool->saveDeferred($pool->getItem('a')->set('deferred'));

        self::assertTrue($pool->save($pool->getItem('a')->set('immediate')));
        self::assertTrue($pool->commit());

        self::assertSame('immediate', $pool->getItem('a')->get());
    }

    #[Test]
    public function getItems_seesPendingDeferredItems(): void
    {
        $pool = $this->pool();
        $pool->saveDeferred($pool->getItem('a')->set('later'));

        $items = $pool->getItems(['a']);

        self::assertTrue($items['a']->isHit());
        self::assertSame('later', $items['a']->get());
    }

    #[Test]
    public function prune_dropsOnlyExpiredEntriesAndReturnsTheCount(): void
    {
        $pool = $this->pool();
        $pool->save($pool->getItem('short')->set(1)->expiresAfter(60));
        $pool->save($pool->getItem('long')->set(2)->expiresAfter(3600));
        $pool->save($pool->getItem('forever')->set(3));

        $this->clock->advance('PT61S');

        self::assertSame(1, $pool->prune());
        self::assertFalse($pool->hasItem('short'));
        self::assertTrue($pool->hasItem('long'));
        self::assertTrue($pool->hasItem('forever'));
    }

    #[Test]
    public function prune_returnsZeroWhenNothingHasExpired(): void
    {
        $pool = $this->pool();
        $pool->save($pool->getItem('a')->set(1)->expiresAfter(3600));

        self::assertSame(0, $pool->prune());
        self::assertTrue($pool->hasItem('a'));
    }

    #[Test]
    public function prune_onAnEmptyPoolReturnsZero(): void
    {
        self::assertSame(0, $this->pool()->prune());
    }

    #[Test]
    public function save_acceptsAValueThatCannotBeSerialised(): void
    {
        $pool = $this->pool();
        $closure = static fn (): int => 1;

        self::assertTrue($pool->save($pool->getItem('closure')->set($closure)));
        self::assertSame($closure, $pool->getItem('closure')->get());
    }

    #[Override]
    protected function setUp(): void
    {
        $this->clock = new TestClock('2026-08-21 00:00:00');
    }

    private function pool(): MemoryCachePool
    {
        return new MemoryCachePool($this->clock);
    }
}
