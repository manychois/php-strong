<?php

declare(strict_types=1);

namespace Manychois\PhpStrongTests;

use DateInterval;
use Manychois\PhpStrong\UtcClock;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface as IClock;

/**
 * Unit tests for {@see UtcClock}.
 */
final class UtcClockTest extends TestCase
{
    #[Test]
    public function constructor_exposes_utc_timezone(): void
    {
        $clock = new UtcClock();

        self::assertSame('UTC', $clock->timezone->getName());
        self::assertFalse($clock->isFrozen);
    }

    #[Test]
    public function now_is_usable_as_psr_clock(): void
    {
        $run = static fn (IClock $clock): int => $clock->now()->getTimestamp();
        $clock = new UtcClock();
        $clock->freeze('99');

        self::assertSame(99, $run($clock));
    }

    #[Test]
    public function now_returns_datetime_in_utc_when_live(): void
    {
        $clock = new UtcClock();

        self::assertSame('UTC', $clock->now()->getTimezone()->getName());
    }

    #[Test]
    public function freeze_fixes_now_and_create_now_until_unfreeze(): void
    {
        $clock = new UtcClock();
        $epoch = '1735689600';
        $clock->freeze($epoch);

        self::assertTrue($clock->isFrozen);
        self::assertSame((int) $epoch, $clock->now()->getTimestamp());
        self::assertSame((int) $epoch, $clock->create('now')->getTimestamp());

        $clock->unfreeze();

        self::assertFalse($clock->isFrozen);
    }

    #[Test]
    public function unfreeze_restores_advancing_clock(): void
    {
        $clock = new UtcClock();
        $clock->freeze('1000000000');
        $clock->unfreeze();

        $a = $clock->now()->getTimestamp();
        $b = $clock->now()->getTimestamp();

        self::assertGreaterThanOrEqual($a, $b);
    }

    #[Test]
    public function create_parses_time_string_in_utc(): void
    {
        $clock = new UtcClock();
        $dt = $clock->create('2024-06-15 12:34:56');

        self::assertSame('UTC', $dt->getTimezone()->getName());
        self::assertSame('2024-06-15 12:34:56', $dt->format('Y-m-d H:i:s'));
    }

    #[Test]
    public function add_from_now_adds_interval_to_current_instant(): void
    {
        $clock = new UtcClock();
        $clock->freeze('1735689600');

        $later = $clock->addFromNow(new DateInterval('PT1H'));

        self::assertSame(1735693200, $later->getTimestamp());
        self::assertSame(0, $later->getOffset());
    }

    #[Test]
    public function subtract_from_now_subtracts_interval_from_current_instant(): void
    {
        $clock = new UtcClock();
        $clock->freeze('1735689600');

        $earlier = $clock->subtractFromNow(new DateInterval('PT30M'));

        self::assertSame(1735687800, $earlier->getTimestamp());
    }

    #[Test]
    public function freeze_can_be_updated_to_a_new_epoch(): void
    {
        $clock = new UtcClock();
        $clock->freeze('1000');
        self::assertSame(1000, $clock->now()->getTimestamp());

        $clock->freeze('2000');
        self::assertSame(2000, $clock->now()->getTimestamp());
    }
}
