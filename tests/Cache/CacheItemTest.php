<?php

declare(strict_types=1);

namespace Manychois\PhpStrongTests\Cache;

use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use Manychois\PhpStrong\Cache\CacheItem;
use Manychois\PhpStrong\Time\TestClock;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CacheItemTest extends TestCase
{
    #[Test]
    public function getKey_returnsTheKeyGivenToTheConstructor(): void
    {
        $item = new CacheItem('user.1', null, false, null, new TestClock('2026-08-21 00:00:00'));

        self::assertSame('user.1', $item->getKey());
    }

    #[Test]
    public function get_returnsTheStoredValueOfAHit(): void
    {
        $item = new CacheItem('k', ['a' => 1], true, null, new TestClock('2026-08-21 00:00:00'));

        self::assertTrue($item->isHit());
        self::assertSame(['a' => 1], $item->get());
    }

    #[Test]
    public function get_returnsNullForAFreshMiss(): void
    {
        $item = new CacheItem('k', null, false, null, new TestClock('2026-08-21 00:00:00'));

        self::assertFalse($item->isHit());
        self::assertNull($item->get());
    }

    #[Test]
    public function set_replacesTheValueWithoutMakingTheItemAHit(): void
    {
        $item = new CacheItem('k', null, false, null, new TestClock('2026-08-21 00:00:00'));

        $returned = $item->set('fresh');

        self::assertSame($item, $returned);
        self::assertFalse($item->isHit());
        self::assertNull($item->get());
        self::assertSame('fresh', $item->getRawValue());
    }

    #[Test]
    public function getRawValue_returnsTheValueEvenWhenTheItemIsNotAHit(): void
    {
        $item = new CacheItem('k', 'stored', true, null, new TestClock('2026-08-21 00:00:00'));

        self::assertSame('stored', $item->getRawValue());
        self::assertSame('replaced', $item->set('replaced')->getRawValue());
    }

    #[Test]
    public function expiresAfter_withAnExtremeNegativeSecondCountDoesNotThrow(): void
    {
        $clock = new TestClock('2026-08-21 00:00:00');
        $item = new CacheItem('k', null, false, null, $clock);

        $item->expiresAfter(PHP_INT_MIN);

        self::assertNotNull($item->getExpiry());
        self::assertLessThanOrEqual($clock->now(), $item->getExpiry());
    }

    #[Test]
    public function expiresAfter_withAnExtremePositiveSecondCountDoesNotThrow(): void
    {
        $clock = new TestClock('2026-08-21 00:00:00');
        $item = new CacheItem('k', null, false, null, $clock);

        $item->expiresAfter(PHP_INT_MAX);

        self::assertNotNull($item->getExpiry());
        self::assertGreaterThan($clock->now(), $item->getExpiry());
    }

    #[Test]
    public function expiresAt_withADateTimeSetsTheExpiry(): void
    {
        $item = new CacheItem('k', null, false, null, new TestClock('2026-08-21 00:00:00'));
        $when = new DateTimeImmutable('2026-08-21 01:00:00', new DateTimeZone('UTC'));

        $returned = $item->expiresAt($when);

        self::assertSame($item, $returned);
        self::assertNotNull($item->getExpiry());
        self::assertSame($when->getTimestamp(), $item->getExpiry()->getTimestamp());
    }

    #[Test]
    public function expiresAt_withNullClearsTheExpiry(): void
    {
        $clock = new TestClock('2026-08-21 00:00:00');
        $item = new CacheItem('k', null, false, $clock->now(), $clock);

        $item->expiresAt(null);

        self::assertNull($item->getExpiry());
    }

    #[Test]
    public function expiresAfter_withSecondsIsRelativeToTheClock(): void
    {
        $clock = new TestClock('2026-08-21 00:00:00');
        $item = new CacheItem('k', null, false, null, $clock);

        $returned = $item->expiresAfter(90);

        self::assertSame($item, $returned);
        self::assertNotNull($item->getExpiry());
        self::assertSame('2026-08-21 00:01:30', $item->getExpiry()->format('Y-m-d H:i:s'));
    }

    #[Test]
    public function expiresAfter_withNegativeSecondsMovesTheExpiryIntoThePast(): void
    {
        $clock = new TestClock('2026-08-21 00:00:00');
        $item = new CacheItem('k', null, false, null, $clock);

        $item->expiresAfter(-30);

        self::assertNotNull($item->getExpiry());
        self::assertSame('2026-08-20 23:59:30', $item->getExpiry()->format('Y-m-d H:i:s'));
    }

    #[Test]
    public function expiresAfter_withADateIntervalIsRelativeToTheClock(): void
    {
        $clock = new TestClock('2026-08-21 00:00:00');
        $item = new CacheItem('k', null, false, null, $clock);

        $item->expiresAfter(new DateInterval('PT2H'));

        self::assertNotNull($item->getExpiry());
        self::assertSame('2026-08-21 02:00:00', $item->getExpiry()->format('Y-m-d H:i:s'));
    }

    #[Test]
    public function expiresAfter_withNullClearsTheExpiry(): void
    {
        $clock = new TestClock('2026-08-21 00:00:00');
        $item = new CacheItem('k', null, false, $clock->now(), $clock);

        $item->expiresAfter(null);

        self::assertNull($item->getExpiry());
    }
}
