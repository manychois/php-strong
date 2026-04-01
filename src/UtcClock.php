<?php

declare(strict_types=1);

namespace Manychois\PhpStrong;

use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use NoDiscard;
use Override;
use Psr\Clock\ClockInterface as IClock;

/**
 * A {@see IClock} implementation that always uses UTC and supports freezing time for tests.
 */
class UtcClock implements IClock
{
    /**
     * @var string Either `'now'` for the live instant or a frozen value such as `@1735689600`.
     */
    private string $now = 'now';

    /**
     * Whether the clock is frozen at a fixed instant instead of advancing with real time.
     */
    public bool $isFrozen { get => $this->now !== 'now'; }

    /**
     * @var DateTimeZone The timezone used by the clock, always UTC.
     */
    public readonly DateTimeZone $timezone;

    /**
     * Creates a new clock fixed to the UTC timezone.
     */
    public function __construct()
    {
        $this->timezone = new DateTimeZone('UTC');
    }

    /**
     * Returns the current instant plus the given interval.
     *
     * @param DateInterval $interval The interval to add.
     *
     * @return DateTimeImmutable The result in UTC.
     */
    #[NoDiscard]
    public function addFromNow(DateInterval $interval): DateTimeImmutable
    {
        return $this->now()->add($interval);
    }

    /**
     * Parses a time string in UTC, or returns the frozen or live instant when `$time` is `'now'`.
     *
     * @param string $time A `DateTimeImmutable` time string, or `'now'` to use this clock's current instant.
     *
     * @return DateTimeImmutable The parsed or current instant in UTC.
     */
    #[NoDiscard]
    public function create(string $time): DateTimeImmutable
    {
        if ($time === 'now') {
            return $this->now();
        }
        return new DateTimeImmutable($time, $this->timezone);
    }

    /**
     * Locks the clock to a Unix timestamp so {@see now} and `'now'` in {@see create} stay fixed.
     *
     * @param string $now Seconds since the Unix epoch, without a leading `@`.
     */
    public function freeze(string $now): void
    {
        $this->now = '@' . $now;
    }

    /**
     * Returns the current instant minus the given interval.
     *
     * @param DateInterval $interval The interval to subtract.
     *
     * @return DateTimeImmutable The result in UTC.
     */
    #[NoDiscard]
    public function subtractFromNow(DateInterval $interval): DateTimeImmutable
    {
        return $this->now()->sub($interval);
    }

    /**
     * Restores the live clock so {@see now} follows real time again.
     */
    public function unfreeze(): void
    {
        $this->now = 'now';
    }

    #region implements IClock

    /**
     * @inheritDoc
     */
    #[Override]
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable($this->now, $this->timezone);
    }

    #endregion implements IClock
}
