<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\Clock;

use DateTimeImmutable;
use DateTimeZone;
use Override;
use Psr\Clock\ClockInterface as IClock;

/**
 * PSR-20 clock that reports the current system time in UTC.
 */
class UtcClock implements IClock
{
    private readonly DateTimeZone $utc;

    /**
     * Creates a UTC clock.
     */
    public function __construct()
    {
        $this->utc = new DateTimeZone('UTC');
    }

    #region implements IClock

    /**
     * @inheritDoc
     */
    #[Override]
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', $this->utc);
    }

    #endregion
}
