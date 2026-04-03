<?php

declare(strict_types=1);

namespace Manychois\PhpStrongTests\Collections\Internal;

use ArrayIterator;
use Iterator;
use Manychois\PhpStrong\Collections\Internal\CacheIterator;
use Override;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Unit tests for {@see CacheIterator}.
 */
final class CacheIteratorTest extends TestCase
{
    /**
     * @param array<int|string, mixed> $values
     */
    private static function create_iterator(array $values): Iterator
    {
        $iterator = new class($values) extends ArrayIterator implements Iterator {
            private bool $rewinded = false;

            /**
             * @param array<int|string, mixed> $values
             */
            public function __construct(array $values)
            {
                parent::__construct($values);
            }

            public function rewind(): void
            {
                if ($this->rewinded) {
                    throw new RuntimeException('rewind invoked more than once');
                }
                $this->rewinded = true;
                parent::rewind();
            }
        };
        return $iterator;
    }

    #[Test]
    public function iteration_replaysFromCache_afterSecondRewind(): void
    {
        $inner = self::create_iterator(['x' => 'first', 'y' => 'second']);
        $cache = new CacheIterator($inner);
        $cache->rewind();
        self::assertTrue($cache->valid());
        self::assertSame('x', $cache->key());
        self::assertSame('first', $cache->current());
        $cache->next();
        self::assertSame('y', $cache->key());
        self::assertSame('second', $cache->current());
        $cache->next();
        self::assertFalse($cache->valid());
        $cache->rewind();
        self::assertTrue($cache->valid());
        self::assertSame('x', $cache->key());
        self::assertSame('first', $cache->current());
    }

    #[Test]
    public function next_readsFromSource_whenPastEndOfCache(): void
    {
        $inner = self::create_iterator([1, 2, 3]);
        $cache = new CacheIterator($inner);
        $cache->rewind();
        self::assertSame(1, $cache->current());
        $cache->next();
        self::assertSame(2, $cache->current());
        $cache->next();
        self::assertSame(3, $cache->current());
    }

    #[Test]
    public function rewind_invokesUnderlyingRewindOnlyOnce(): void
    {
        $inner = self::create_iterator([1, 2]);
        $cache = new CacheIterator($inner);
        $cache->rewind();
        self::assertSame(1, $cache->current());
        $cache->next();
        self::assertSame(2, $cache->current());
        $cache->next();
        self::assertFalse($cache->valid());
        $cache->rewind();
        self::assertSame(1, $cache->current());
    }

    #[Test]
    public function valid_reportsFalse_whenSourceEmpty(): void
    {
        $cache = new CacheIterator(self::create_iterator([]));
        $cache->rewind();
        self::assertFalse($cache->valid());
    }
}
