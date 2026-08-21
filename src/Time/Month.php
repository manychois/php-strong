<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\Time;

use DateTimeInterface;

/**
 * Months of the year, numbered as in the proleptic Gregorian calendar: January is 1 and December is 12.
 *
 * The backing value matches the `n` format character of `DateTimeInterface::format()`.
 */
enum Month: int
{
    case January = 1;
    case February = 2;
    case March = 3;
    case April = 4;
    case May = 5;
    case June = 6;
    case July = 7;
    case August = 8;
    case September = 9;
    case October = 10;
    case November = 11;
    case December = 12;

    /**
     * Resolves the month in which a date falls, in that date's own timezone.
     *
     * @param DateTimeInterface $date The date to inspect.
     *
     * @return self The month.
     */
    public static function fromDate(DateTimeInterface $date): self
    {
        return self::from((int) $date->format('n'));
    }

    /**
     * Returns the number of days in the month for a given year.
     *
     * @param int $year The proleptic Gregorian year, which decides the length of `February`.
     *
     * @return int The number of days, from 28 to 31.
     */
    public function length(int $year): int
    {
        return match ($this) {
            self::February => $year % 4 === 0 && ($year % 100 !== 0 || $year % 400 === 0) ? 29 : 28,
            self::April, self::June, self::September, self::November => 30,
            default => 31,
        };
    }

    /**
     * Returns the following month, wrapping from `December` back to `January`.
     *
     * @return self The next month.
     */
    public function next(): self
    {
        return $this->plus(1);
    }

    /**
     * Returns the month a given number of months later, wrapping around the year.
     *
     * @param int $months The number of months to add. Negative values move backwards.
     *
     * @return self The resulting month.
     */
    public function plus(int $months): self
    {
        return self::from(($this->value + 11 + $months % 12) % 12 + 1);
    }

    /**
     * Returns the preceding month, wrapping from `January` back to `December`.
     *
     * @return self The previous month.
     */
    public function previous(): self
    {
        return $this->plus(-1);
    }
}
