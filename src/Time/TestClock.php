<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\Time;

use DateInterval;
use DateTimeImmutable;
use DateTimeInterface as IDateTime;
use DateTimeZone;
use Override;

/**
 * PSR-20 clock whose current time is set by the caller; intended for tests.
 */
class TestClock extends UtcClock
{
    private DateTimeImmutable $now;

    /**
     * Creates a clock frozen at the given time.
     *
     * @param IDateTime|string|null $now Initial time, as a `DateTimeInterface` or a `strtotime()`-style string;
     * `null` freezes at the current UTC time. Non-UTC values are converted to UTC.
     */
    public function __construct(IDateTime|string|null $now = null)
    {
        parent::__construct();
        $this->setNow($now ?? parent::now());
    }

    /**
     * Moves the clock forward (or backward, with an inverted interval).
     *
     * @param DateInterval|string $interval A `DateInterval` or an ISO 8601 duration such as `PT5M`.
     */
    public function advance(DateInterval|string $interval): void
    {
        $this->now = $this->now->add(is_string($interval) ? new DateInterval($interval) : $interval);
    }

    /**
     * Sets the time the clock reports.
     *
     * @param IDateTime|string $now New time, as a `DateTimeInterface` or a `strtotime()`-style string
     * (interpreted in UTC when it carries no offset). Non-UTC values are converted to UTC.
     */
    public function setNow(IDateTime|string $now): void
    {
        $utc = new DateTimeZone('UTC');
        $this->now = is_string($now)
            ? new DateTimeImmutable($now, $utc)
            : DateTimeImmutable::createFromInterface($now)->setTimezone($utc);
    }

    #region extends UtcClock

    /**
     * @inheritDoc
     */
    #[Override]
    public function now(): DateTimeImmutable
    {
        return $this->now;
    }

    #endregion
}
