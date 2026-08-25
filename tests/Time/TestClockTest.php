<?php

declare(strict_types=1);

namespace Manychois\PhpStrongTests\Time;

use DateInterval;
use DateTime;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Manychois\PhpStrong\Time\TestClock;
use Manychois\PhpStrong\Time\UtcClock;

final class TestClockTest extends TestCase
{
    #[Test]
    public function now_returnsFrozenTime(): void
    {
        $clock = new TestClock('2026-01-02T03:04:05+00:00');
        self::assertInstanceOf(UtcClock::class, $clock);
        self::assertSame('2026-01-02T03:04:05+00:00', $clock->now()->format(DATE_ATOM));
        self::assertSame($clock->now(), $clock->now());
    }

    #[Test]
    public function construct_defaultsToCurrentUtcTime(): void
    {
        $before = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $clock = new TestClock();
        self::assertGreaterThanOrEqual($before, $clock->now());
        self::assertSame('UTC', $clock->now()->getTimezone()->getName());
    }

    #[Test]
    public function setNow_convertsToUtc(): void
    {
        $clock = new TestClock();
        $clock->setNow(new DateTime('2026-06-01T12:00:00+10:00'));
        self::assertSame('2026-06-01T02:00:00+00:00', $clock->now()->format(DATE_ATOM));

        $clock->setNow('2026-06-01 12:00:00');
        self::assertSame('2026-06-01T12:00:00+00:00', $clock->now()->format(DATE_ATOM));
    }

    #[Test]
    public function advance_movesTime(): void
    {
        $clock = new TestClock('2026-01-01T00:00:00Z');
        $clock->advance('PT90M');
        self::assertSame('2026-01-01T01:30:00+00:00', $clock->now()->format(DATE_ATOM));
        $clock->advance(new DateInterval('P1D'));
        self::assertSame('2026-01-02T01:30:00+00:00', $clock->now()->format(DATE_ATOM));
    }
}
