<?php

declare(strict_types=1);

namespace Manychois\PhpStrong;

/**
 * Represents a day of the week.
 */
enum DayOfWeek: int
{
    case Sunday = 0;
    case Monday = 1;
    case Tuesday = 2;
    case Wednesday = 3;
    case Thursday = 4;
    case Friday = 5;
    case Saturday = 6;
}
