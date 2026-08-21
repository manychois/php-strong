<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\Time;

use DateTimeInterface;
use InvalidArgumentException;

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
     * Resolves a day by its English name. Matching is case-insensitive.
     *
     * @param string $day The day name, e.g. `Monday` or `monday`.
     *
     * @return self The day of the week.
     *
     * @throws InvalidArgumentException if the name is not an English day name.
     */
    public static function fromString(string $day): self
    {
        $normalized = ucfirst(strtolower(trim($day)));
        foreach (self::cases() as $case) {
            if ($case->name === $normalized) {
                return $case;
            }
        }

        throw new InvalidArgumentException(sprintf('Unknown day of the week: %s', $day));
    }

    /**
     * Tells whether the day falls on Monday to Friday.
     *
     * @return bool `true` if the day is not `Saturday` or `Sunday`.
     */
    public function isWeekday(): bool
    {
        return !$this->isWeekend();
    }

    /**
     * Tells whether the day falls on Saturday or Sunday.
     *
     * @return bool `true` if the day is `Saturday` or `Sunday`.
     */
    public function isWeekend(): bool
    {
        return $this === self::Saturday || $this === self::Sunday;
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
        $offset = ($this->value - 1 + $days % 7 + 7) % 7;

        return self::from($offset + 1);
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
