<?php

declare(strict_types=1);

namespace Manychois\PhpStrongTests\Clock;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use Manychois\PhpStrong\Clock\UtcClock;

final class UtcClockTest extends TestCase
{
    #[Test]
    public function now_returnsCurrentTimeInUtc(): void
    {
        $clock = new UtcClock();
        self::assertInstanceOf(ClockInterface::class, $clock);

        $before = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $now = $clock->now();
        $after = new DateTimeImmutable('now', new DateTimeZone('UTC'));

        self::assertSame('UTC', $now->getTimezone()->getName());
        self::assertGreaterThanOrEqual($before, $now);
        self::assertLessThanOrEqual($after, $now);
    }

    #[Test]
    public function now_ignoresDefaultTimezone(): void
    {
        $previous = date_default_timezone_get();
        date_default_timezone_set('Australia/Sydney');
        try {
            self::assertSame('UTC', (new UtcClock())->now()->getTimezone()->getName());
        } finally {
            date_default_timezone_set($previous);
        }
    }
}
