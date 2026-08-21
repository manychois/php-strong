<?php

declare(strict_types=1);

namespace Manychois\PhpStrongTests\Cache;

use FilesystemIterator;
use Manychois\PhpStrong\Cache\CacheException;
use Manychois\PhpStrong\Cache\CacheItem;
use Manychois\PhpStrong\Cache\FileCachePool;
use Manychois\PhpStrong\Cache\InvalidArgumentException;
use Manychois\PhpStrong\Clock\TestClock;
use Override;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final class FileCachePoolTest extends TestCase
{
    private string $dir = '';
    private TestClock $clock;

    /**
     * @return list<array{string}>
     */
    public static function provideInvalidKeys(): array
    {
        return [[''], ['a{b'], ['a}b'], ['a(b'], ['a)b'], ['a/b'], ['a\\b'], ['a@b'], ['a:b']];
    }

    private static function skipWhenRoot(): void
    {
        if (function_exists('posix_geteuid') && posix_geteuid() === 0) {
            self::markTestSkipped('Permission-based failures cannot be simulated as root.');
        }
    }

    private static function removeTree(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        chmod($dir, 0o777);
        $entries = scandir($dir);
        foreach ($entries === false ? [] : $entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $path = $dir . '/' . $entry;
            if (is_dir($path)) {
                self::removeTree($path);
            } else {
                chmod($path, 0o777);
                unlink($path);
            }
        }

        rmdir($dir);
    }

    #[Test]
    public function construct_createsTheDirectoryWhenItIsMissing(): void
    {
        self::assertDirectoryDoesNotExist($this->dir);

        $this->pool();

        self::assertDirectoryExists($this->dir);
    }

    #[Test]
    public function construct_throwsWhenThePathIsAFile(): void
    {
        file_put_contents($this->dir, 'not a directory');

        try {
            $this->expectException(CacheException::class);
            new FileCachePool($this->dir, $this->clock);
        } finally {
            unlink($this->dir);
        }
    }

    #[Test]
    public function construct_defaultsToAUtcClock(): void
    {
        $pool = new FileCachePool($this->dir);
        $item = $pool->getItem('k')->set('v')->expiresAfter(60);

        self::assertTrue($pool->save($item));
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

        $reread = $this->pool()->getItem('user.1');

        self::assertTrue($reread->isHit());
        self::assertSame(['name' => 'Ada'], $reread->get());
        self::assertCount(1, $this->cacheFiles());
    }

    #[Test]
    public function save_storesFalseAsAValueWithoutTreatingItAsCorruption(): void
    {
        $pool = $this->pool();
        $pool->save($pool->getItem('flag')->set(false));

        $reread = $this->pool()->getItem('flag');

        self::assertTrue($reread->isHit());
        self::assertFalse($reread->get());
    }

    #[Test]
    public function save_writesTheExpiryTimestampAsTheFirstLine(): void
    {
        $pool = $this->pool();
        $pool->save($pool->getItem('k')->set('v')->expiresAfter(60));

        $files = $this->cacheFiles();
        self::assertCount(1, $files);
        $raw = file_get_contents($files[0]);
        self::assertIsString($raw);
        $stamp = strstr($raw, "\n", true);
        self::assertIsString($stamp);
        self::assertSame($this->clock->now()->getTimestamp() + 60, (int) $stamp);
    }

    #[Test]
    public function save_withoutExpiryWritesAZeroTimestamp(): void
    {
        $pool = $this->pool();
        $pool->save($pool->getItem('k')->set('v'));

        $files = $this->cacheFiles();
        self::assertSame('0', strstr((string) file_get_contents($files[0]), "\n", true));
    }

    #[Test]
    public function getItem_missesOnceTheItemHasExpiredAndRemovesTheFile(): void
    {
        $pool = $this->pool();
        $pool->save($pool->getItem('k')->set('v')->expiresAfter(60));

        $this->clock->advance('PT61S');
        $fresh = $this->pool();

        self::assertFalse($fresh->getItem('k')->isHit());
        self::assertSame([], $this->cacheFiles());
    }

    #[Test]
    public function getItem_stillHitsOneSecondBeforeExpiry(): void
    {
        $pool = $this->pool();
        $pool->save($pool->getItem('k')->set('v')->expiresAfter(60));

        $this->clock->advance('PT59S');

        self::assertTrue($this->pool()->getItem('k')->isHit());
    }

    #[Test]
    public function getItem_missesExactlyAtTheExpiryMoment(): void
    {
        $pool = $this->pool();
        $pool->save($pool->getItem('k')->set('v')->expiresAfter(60));

        $this->clock->advance('PT60S');

        self::assertFalse($this->pool()->getItem('k')->isHit());
    }

    #[Test]
    public function save_withAnAlreadyExpiredItemStoresNothingAndRemovesTheOldFile(): void
    {
        $pool = $this->pool();
        $pool->save($pool->getItem('k')->set('old'));
        self::assertCount(1, $this->cacheFiles());

        self::assertTrue($pool->save($pool->getItem('k')->set('new')->expiresAfter(-1)));

        self::assertSame([], $this->cacheFiles());
        self::assertFalse($this->pool()->getItem('k')->isHit());
    }

    #[Test]
    public function save_acceptsAForeignCacheItemAndStoresItWithoutExpiry(): void
    {
        $pool = $this->pool();

        self::assertTrue($pool->save(new ForeignCacheItem('foreign', 'value')));

        $reread = $this->pool()->getItem('foreign');
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
    public function getItem_readsTheFileOnlyOnceForTheSameKey(): void
    {
        $pool = $this->pool();
        $pool->save($pool->getItem('k')->set('stored'));
        self::assertTrue($pool->getItem('k')->isHit());

        foreach ($this->cacheFiles() as $file) {
            unlink($file);
        }

        self::assertTrue($pool->getItem('k')->isHit());
        self::assertFalse($this->pool()->getItem('k')->isHit());
    }

    #[Test]
    public function hasItem_reflectsWhetherALiveEntryExists(): void
    {
        $pool = $this->pool();

        self::assertFalse($pool->hasItem('k'));
        $pool->save($pool->getItem('k')->set('v')->expiresAfter(60));
        self::assertTrue($this->pool()->hasItem('k'));

        $this->clock->advance('PT61S');
        self::assertFalse($this->pool()->hasItem('k'));
    }

    #[Test]
    public function deleteItem_removesTheFileAndReturnsTrueForAMissingKey(): void
    {
        $pool = $this->pool();
        $pool->save($pool->getItem('k')->set('v'));

        self::assertTrue($pool->deleteItem('k'));
        self::assertSame([], $this->cacheFiles());
        self::assertFalse($pool->getItem('k')->isHit());
        self::assertTrue($pool->deleteItem('k'));
    }

    #[Test]
    public function getItem_treatsAFileWithoutANewlineAsAMissAndDeletesIt(): void
    {
        $this->writeRawFile('k', 'garbage-without-newline');

        self::assertFalse($this->pool()->getItem('k')->isHit());
        self::assertSame([], $this->cacheFiles());
    }

    #[Test]
    public function getItem_treatsANonNumericExpiryAsAMissAndDeletesIt(): void
    {
        $this->writeRawFile('k', "later\n" . serialize('v'));

        self::assertFalse($this->pool()->getItem('k')->isHit());
        self::assertSame([], $this->cacheFiles());
    }

    #[Test]
    public function getItem_treatsAnUnserializableBodyAsAMissAndDeletesIt(): void
    {
        $this->writeRawFile('k', "0\nnot-serialized");

        self::assertFalse($this->pool()->getItem('k')->isHit());
        self::assertSame([], $this->cacheFiles());
    }

    #[Test]
    public function getItems_returnsAnItemPerRequestedKeyInOrder(): void
    {
        $pool = $this->pool();
        $pool->save($pool->getItem('a')->set(1));
        $pool->save($pool->getItem('c')->set(3));

        $items = $this->pool()->getItems(['a', 'b', 'c']);

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
        $pool = $this->pool();
        $pool->save($pool->getItem('a')->set(1));

        $this->expectException(InvalidArgumentException::class);

        $pool->getItems(['a', 'bad:key']);
    }

    #[Test]
    public function deleteItems_removesEveryKey(): void
    {
        $pool = $this->pool();
        $pool->save($pool->getItem('a')->set(1));
        $pool->save($pool->getItem('b')->set(2));

        self::assertTrue($pool->deleteItems(['a', 'b', 'missing']));
        self::assertSame([], $this->cacheFiles());
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
            self::assertCount(1, $this->cacheFiles());
        }
    }

    #[Test]
    public function clear_removesEveryStoredItemButKeepsTheRootDirectory(): void
    {
        $pool = $this->pool();
        $pool->save($pool->getItem('a')->set(1));
        $pool->save($pool->getItem('b')->set(2));

        self::assertTrue($pool->clear());

        self::assertDirectoryExists($this->dir);
        self::assertSame([], $this->cacheFiles());
        self::assertFalse($pool->getItem('a')->isHit());
    }

    #[Test]
    public function clear_leavesUnrelatedFilesInTheRootDirectoryAlone(): void
    {
        $pool = $this->pool();
        $pool->save($pool->getItem('a')->set(1));
        file_put_contents($this->dir . '/README.txt', 'keep me');

        self::assertTrue($pool->clear());

        self::assertFileExists($this->dir . '/README.txt');
    }

    #[Test]
    public function clear_dropsPendingDeferredItems(): void
    {
        $pool = $this->pool();
        $pool->saveDeferred($pool->getItem('a')->set(1));

        self::assertTrue($pool->clear());
        self::assertFalse($pool->hasItem('a'));

        self::assertTrue($pool->commit());
        self::assertSame([], $this->cacheFiles());
    }

    #[Test]
    public function saveDeferred_makesTheItemVisibleBeforeCommitWithoutWritingAFile(): void
    {
        $pool = $this->pool();

        self::assertTrue($pool->saveDeferred($pool->getItem('a')->set('later')));

        self::assertTrue($pool->hasItem('a'));
        self::assertTrue($pool->getItem('a')->isHit());
        self::assertSame('later', $pool->getItem('a')->get());
        self::assertSame([], $this->cacheFiles());
        self::assertFalse($this->pool()->hasItem('a'));
    }

    #[Test]
    public function commit_writesEveryDeferredItemAndEmptiesTheQueue(): void
    {
        $pool = $this->pool();
        $pool->saveDeferred($pool->getItem('a')->set(1));
        $pool->saveDeferred($pool->getItem('b')->set(2));

        self::assertTrue($pool->commit());

        $fresh = $this->pool();
        self::assertSame(1, $fresh->getItem('a')->get());
        self::assertSame(2, $fresh->getItem('b')->get());

        self::assertTrue($pool->commit());
        self::assertCount(2, $this->cacheFiles());
    }

    #[Test]
    public function commit_deletesADeferredItemThatIsAlreadyExpired(): void
    {
        $pool = $this->pool();
        $pool->save($pool->getItem('a')->set('old'));
        $pool->saveDeferred($pool->getItem('a')->set('new')->expiresAfter(-1));

        self::assertTrue($pool->commit());

        self::assertSame([], $this->cacheFiles());
        self::assertFalse($this->pool()->hasItem('a'));
    }

    #[Test]
    public function deleteItem_dropsAPendingDeferredItem(): void
    {
        $pool = $this->pool();
        $pool->saveDeferred($pool->getItem('a')->set(1));

        self::assertTrue($pool->deleteItem('a'));
        self::assertFalse($pool->hasItem('a'));

        self::assertTrue($pool->commit());
        self::assertSame([], $this->cacheFiles());
    }

    #[Test]
    public function save_replacesAPendingDeferredItemForTheSameKey(): void
    {
        $pool = $this->pool();
        $pool->saveDeferred($pool->getItem('a')->set('deferred'));

        self::assertTrue($pool->save($pool->getItem('a')->set('immediate')));
        self::assertTrue($pool->commit());

        self::assertSame('immediate', $this->pool()->getItem('a')->get());
    }

    #[Test]
    public function saveDeferred_rejectsAnInvalidKey(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->pool()->saveDeferred(new ForeignCacheItem('bad/key', 'v'));
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
    public function prune_deletesOnlyExpiredEntriesAndReturnsTheCount(): void
    {
        $pool = $this->pool();
        $pool->save($pool->getItem('short')->set(1)->expiresAfter(60));
        $pool->save($pool->getItem('long')->set(2)->expiresAfter(3600));
        $pool->save($pool->getItem('forever')->set(3));

        $this->clock->advance('PT61S');

        self::assertSame(1, $pool->prune());
        self::assertCount(2, $this->cacheFiles());
        self::assertTrue($this->pool()->hasItem('long'));
        self::assertTrue($this->pool()->hasItem('forever'));
        self::assertFalse($this->pool()->hasItem('short'));
    }

    #[Test]
    public function prune_deletesMalformedFiles(): void
    {
        $this->writeRawFile('broken', 'no newline here');

        self::assertSame(1, $this->pool()->prune());
        self::assertSame([], $this->cacheFiles());
    }

    #[Test]
    public function prune_returnsZeroWhenNothingHasExpired(): void
    {
        $pool = $this->pool();
        $pool->save($pool->getItem('a')->set(1)->expiresAfter(3600));

        self::assertSame(0, $pool->prune());
        self::assertCount(1, $this->cacheFiles());
    }

    #[Test]
    public function prune_dropsTheMemoSoLaterReadsSeeTheDeletion(): void
    {
        $pool = $this->pool();
        $pool->save($pool->getItem('a')->set(1)->expiresAfter(60));
        self::assertTrue($pool->hasItem('a'));

        $this->clock->advance('PT61S');
        self::assertSame(1, $pool->prune());

        self::assertFalse($pool->hasItem('a'));
    }

    #[Test]
    public function prune_onAnEmptyPoolReturnsZero(): void
    {
        self::assertSame(0, $this->pool()->prune());
    }

    #[Test]
    public function construct_throwsWhenTheDirectoryIsNotWritable(): void
    {
        self::skipWhenRoot();
        mkdir($this->dir, 0o555, true);

        $this->expectException(CacheException::class);

        new FileCachePool($this->dir, $this->clock);
    }

    #[Test]
    public function save_returnsFalseWhenTheShardDirectoryCannotBeCreated(): void
    {
        self::skipWhenRoot();
        $pool = $this->pool();
        chmod($this->dir, 0o555);

        self::assertFalse($pool->save($pool->getItem('a')->set(1)));
    }

    #[Test]
    public function save_returnsFalseWhenTheTemporaryFileCannotBeWritten(): void
    {
        self::skipWhenRoot();
        $pool = $this->pool();
        $pool->save($pool->getItem('a')->set(1));
        chmod(dirname($this->cacheFiles()[0]), 0o555);

        self::assertFalse($pool->save($pool->getItem('a')->set(2)));
    }

    #[Test]
    public function save_returnsFalseWhenTheTemporaryFileCannotBeRenamed(): void
    {
        $pool = $this->pool();
        $hash = hash('sha256', 'a');
        $path = sprintf('%s/%s/%s/%s.cache', $this->dir, substr($hash, 0, 2), substr($hash, 2, 2), $hash);
        mkdir($path, 0o777, true);
        file_put_contents($path . '/blocker', 'x');

        self::assertFalse($pool->save($pool->getItem('a')->set(1)));
        self::assertFileExists($path . '/blocker');
        self::assertCount(0, (array) glob(dirname($path) . '/*.tmp'));
    }

    #[Test]
    public function getItem_missesWhenTheFileCannotBeRead(): void
    {
        self::skipWhenRoot();
        $pool = $this->pool();
        $pool->save($pool->getItem('a')->set(1));
        chmod($this->cacheFiles()[0], 0o000);

        self::assertFalse($this->pool()->getItem('a')->isHit());
    }

    #[Test]
    public function clear_returnsFalseWhenAShardDirectoryCannotBeRead(): void
    {
        self::skipWhenRoot();
        $pool = $this->pool();
        $pool->save($pool->getItem('a')->set(1));
        chmod(dirname(dirname($this->cacheFiles()[0])), 0o000);

        self::assertFalse($pool->clear());
    }

    #[Test]
    public function save_returnsFalseForAValueThatCannotBeSerialised(): void
    {
        $pool = $this->pool();

        self::assertFalse($pool->save($pool->getItem('closure')->set(static fn (): int => 1)));
        self::assertFalse($pool->hasItem('closure'));
        self::assertSame([], $this->cacheFiles());
    }

    #[Test]
    public function save_returnsFalseForAResource(): void
    {
        $pool = $this->pool();
        $handle = fopen('php://memory', 'rb');

        try {
            self::assertFalse($pool->save($pool->getItem('res')->set($handle)));
            self::assertFalse($pool->hasItem('res'));
        } finally {
            if (is_resource($handle)) {
                fclose($handle);
            }
        }
    }

    #[Test]
    public function getItem_missesWhenTheStoredObjectClassNoLongerExists(): void
    {
        $this->writeRawFile('ghost', "0\n" . 'O:11:"GoneAwayCls":1:{s:1:"n";i:1;}');

        self::assertFalse($this->pool()->getItem('ghost')->isHit());
        self::assertSame([], $this->cacheFiles());
    }

    #[Test]
    public function destruct_swallowsAFailureRaisedByTheDeferredCommit(): void
    {
        $pool = $this->pool();
        $pool->saveDeferred(new ThrowingCacheItem('boom'));

        unset($pool);

        self::assertSame([], $this->cacheFiles());
    }

    #[Test]
    public function destruct_commitsPendingDeferredItems(): void
    {
        $pool = $this->pool();
        $pool->saveDeferred($pool->getItem('a')->set('later'));
        unset($pool);

        self::assertSame('later', $this->pool()->getItem('a')->get());
    }

    #[Override]
    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/php-strong-cache-' . bin2hex(random_bytes(8));
        $this->clock = new TestClock('2026-08-21 00:00:00');
    }

    #[Override]
    protected function tearDown(): void
    {
        self::removeTree($this->dir);
    }

    /**
     * @return list<string> Every `.cache` file under the pool directory.
     */
    private function cacheFiles(): array
    {
        $found = glob($this->dir . '/*/*/*.cache');

        return $found === false ? [] : array_values($found);
    }

    private function pool(): FileCachePool
    {
        return new FileCachePool($this->dir, $this->clock);
    }

    private function writeRawFile(string $key, string $body): void
    {
        $this->pool();
        $hash = hash('sha256', $key);
        $dir = sprintf('%s/%s/%s', $this->dir, substr($hash, 0, 2), substr($hash, 2, 2));
        mkdir($dir, 0o777, true);
        file_put_contents($dir . '/' . $hash . '.cache', $body);
    }
}
