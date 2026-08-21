<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\Time;

use DateTimeInterface;

/**
 * Days of the week, numbered as in ISO-8601: Monday is 1 and Sunday is 7.
 *
 * The backing value matches the `N` format character of `DateTimeInterface::format()`.
 */
enum DayOfWeek: int
{
    case Monday = 1;
    case Tuesday = 2;
    case Wednesday = 3;
    case Thursday = 4;
    case Friday = 5;
    case Saturday = 6;
    case Sunday = 7;

    /**
     * Resolves the day of the week on which a date falls, in that date's own timezone.
     *
     * @param DateTimeInterface $date The date to inspect.
     *
     * @return self The day of the week.
     */
    public static function fromDate(DateTimeInterface $date): self
    {
        return self::from((int) $date->format('N'));
    }

    /**
     * Returns the following day, wrapping from `Sunday` back to `Monday`.
     *
     * @return self The next day.
     */
    public function next(): self
    {
        return $this->plus(1);
    }

    /**
     * Returns the day a given number of days later, wrapping around the week.
     *
     * @param int $days The number of days to add. Negative values move backwards.
     *
     * @return self The resulting day.
     */
    public function plus(int $days): self
    {
        // Reduce $days first so that a large value cannot overflow the sum below into a float.
        $shift = $days % 7;
        $index = ($this->value - 1 + $shift + 7) % 7;

        return self::from($index + 1);
    }

    /**
     * Returns the preceding day, wrapping from `Monday` back to `Sunday`.
     *
     * @return self The previous day.
     */
    public function previous(): self
    {
        return $this->plus(-1);
    }
}
