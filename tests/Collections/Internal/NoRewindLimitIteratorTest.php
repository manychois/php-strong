<?php

declare(strict_types=1);

namespace Manychois\PhpStrongTests\Collections\Internal;

use ArrayIterator;
use InvalidArgumentException;
use Manychois\PhpStrong\Collections\Internal\NoRewindLimitIterator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for {@see NoRewindLimitIterator}.
 */
final class NoRewindLimitIteratorTest extends TestCase
{
    #[Test]
    public function exhaust_consumesRemainingStepsOnUnderlyingIterator(): void
    {
        $inner = new ArrayIterator([10, 20, 30, 40]);
        $limit = new NoRewindLimitIterator($inner, 2);
        self::assertSame(10, $limit->current());
        $limit->next();
        self::assertSame(20, $limit->current());
        $limit->exhaust();
        self::assertFalse($limit->valid());
        self::assertSame(30, $inner->current());
    }

    #[Test]
    public function next_stopsAdvancingUnderlying_whenLimitReached(): void
    {
        $inner = new ArrayIterator([1, 2, 3]);
        $limit = new NoRewindLimitIterator($inner, 2);
        self::assertTrue($limit->valid());
        self::assertSame(1, $limit->current());
        $limit->next();
        self::assertSame(2, $limit->current());
        $limit->next();
        self::assertFalse($limit->valid());
        self::assertSame(3, $inner->current());
    }

    #[Test]
    public function rewind_isNoOpForUnderlyingIterator(): void
    {
        $inner = new ArrayIterator(['a', 'b', 'c']);
        $inner->next();
        self::assertSame('b', $inner->current());
        $limit = new NoRewindLimitIterator($inner, 2);
        $limit->rewind();
        self::assertSame('b', $limit->current());
        self::assertSame(1, $limit->key());
    }

    #[Test]
    public function throwsInvalidArgumentException_whenSizeIsNegative(): void
    {
        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage('Size must be greater than 0');
        new NoRewindLimitIterator(new ArrayIterator([1]), -1);
    }

    #[Test]
    public function throwsInvalidArgumentException_whenSizeIsZero(): void
    {
        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage('Size must be greater than 0');
        new NoRewindLimitIterator(new ArrayIterator([1]), 0);
    }

    #[Test]
    public function valid_requiresUnderlyingValid_andPositiveRemaining(): void
    {
        $inner = new ArrayIterator([7]);
        $limit = new NoRewindLimitIterator($inner, 3);
        self::assertTrue($limit->valid());
        self::assertSame(7, $limit->current());
        $limit->next();
        self::assertFalse($limit->valid());
    }

    #[Test]
    public function yieldsFromCurrentUnderlyingPosition_withoutRewinding(): void
    {
        $inner = new ArrayIterator([10, 20, 30, 40]);
        $inner->next();
        $limit = new NoRewindLimitIterator($inner, 2);
        self::assertSame(20, $limit->current());
        self::assertSame(1, $limit->key());
        $limit->next();
        self::assertSame(30, $limit->current());
        self::assertSame(2, $limit->key());
    }
}
