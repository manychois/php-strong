<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\Psr\Clock;

use DateTimeImmutable;
use DateTimeZone;
use LogicException;
use Psr\Clock\ClockInterface;

/**
 * Represents a clock in the UTC timezone.
 */
class UtcClock implements ClockInterface
{
    public readonly bool $isTestMode;
    public readonly DateTimeZone $timezone;
    private int $timestamp = 0;

    /**
     * Initializes a new instance of the Clock class.
     *
     * @param bool $isTestMode Whether the clock is in test mode.
     * @param int  $timestamp  The timestamp to set when the clock is in test mode.
     */
    public function __construct(bool $isTestMode, int $timestamp = 0)
    {
        $this->isTestMode = $isTestMode;
        $this->timezone = new DateTimeZone('UTC');
        $this->timestamp = $timestamp;
    }

    #region implements ClockInterface

    public function now(): DateTimeImmutable
    {
        if ($this->isTestMode) {
            return new DateTimeImmutable('@' . $this->timestamp, $this->timezone);
        }

        return new DateTimeImmutable('now', $this->timezone);
    }

    #endregion implements ClockInterface

    /**
     * Changes the timestamp of the clock.
     * It can only be called when the clock is in test mode.
     *
     * @param int $timestamp The timestamp to set.
     */
    public function changeTimestamp(int $timestamp): void
    {
        if (!$this->isTestMode) {
            throw new LogicException('The clock is not in test mode.');
        }

        $this->timestamp = $timestamp;
    }
}
