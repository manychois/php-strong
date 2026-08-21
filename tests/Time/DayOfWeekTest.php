<?php

declare(strict_types=1);

namespace Manychois\PhpStrongTests\Time;

use DateTimeImmutable;
use DateTimeZone;
use Manychois\PhpStrong\Time\DayOfWeek;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class DayOfWeekTest extends TestCase
{
    #[Test]
    public function cases_followIso8601Numbering(): void
    {
        self::assertSame(1, DayOfWeek::Monday->value);
        self::assertSame(7, DayOfWeek::Sunday->value);
        self::assertCount(7, DayOfWeek::cases());
    }

    #[Test]
    public function fromDate_matchesTheNFormatCharacter(): void
    {
        $date = new DateTimeImmutable('2026-08-21', new DateTimeZone('UTC'));

        self::assertSame(DayOfWeek::Friday, DayOfWeek::fromDate($date));
        self::assertSame((int) $date->format('N'), DayOfWeek::fromDate($date)->value);
    }

    #[Test]
    public function fromDate_usesTheTimezoneOfTheDate(): void
    {
        $utc = new DateTimeImmutable('2026-08-21 23:30:00', new DateTimeZone('UTC'));
        $sydney = $utc->setTimezone(new DateTimeZone('Australia/Sydney'));

        self::assertSame(DayOfWeek::Friday, DayOfWeek::fromDate($utc));
        self::assertSame(DayOfWeek::Saturday, DayOfWeek::fromDate($sydney));
    }

    #[Test]
    public function next_wrapsFromSundayToMonday(): void
    {
        self::assertSame(DayOfWeek::Tuesday, DayOfWeek::Monday->next());
        self::assertSame(DayOfWeek::Monday, DayOfWeek::Sunday->next());
    }

    #[Test]
    public function previous_wrapsFromMondayToSunday(): void
    {
        self::assertSame(DayOfWeek::Monday, DayOfWeek::Tuesday->previous());
        self::assertSame(DayOfWeek::Sunday, DayOfWeek::Monday->previous());
    }

    #[Test]
    #[DataProvider('provideOffsets')]
    public function plus_wrapsAroundTheWeek(DayOfWeek $day, int $offset, DayOfWeek $expected): void
    {
        self::assertSame($expected, $day->plus($offset));
    }

    /**
     * @return array<int, array{DayOfWeek, int, DayOfWeek}>
     */
    public static function provideOffsets(): array
    {
        return [
            [DayOfWeek::Monday, 0, DayOfWeek::Monday],
            [DayOfWeek::Monday, 7, DayOfWeek::Monday],
            [DayOfWeek::Monday, 8, DayOfWeek::Tuesday],
            [DayOfWeek::Monday, -1, DayOfWeek::Sunday],
            [DayOfWeek::Monday, -8, DayOfWeek::Sunday],
            [DayOfWeek::Sunday, 1, DayOfWeek::Monday],
            [DayOfWeek::Sunday, -1, DayOfWeek::Saturday],
            [DayOfWeek::Friday, 3, DayOfWeek::Monday],
            [DayOfWeek::Friday, 700, DayOfWeek::Friday],
            [DayOfWeek::Friday, -700, DayOfWeek::Friday],
        ];
    }

    #[Test]
    public function plus_staysExactAtIntegerLimits(): void
    {
        // PHP_INT_MAX is an exact multiple of 7, so PHP_INT_MAX - 3 shifts by 4 and PHP_INT_MIN shifts by -1.
        self::assertSame(DayOfWeek::Thursday, DayOfWeek::Sunday->plus(\PHP_INT_MAX - 3));
        self::assertSame(DayOfWeek::Sunday, DayOfWeek::Sunday->plus(\PHP_INT_MAX));
        self::assertSame(DayOfWeek::Sunday, DayOfWeek::Monday->plus(\PHP_INT_MIN));
    }

    #[Test]
    public function plus_agreesWithRepeatedNextCalls(): void
    {
        foreach (DayOfWeek::cases() as $day) {
            $walked = $day;
            for ($i = 0; $i < 5; $i++) {
                $walked = $walked->next();
            }

            self::assertSame($walked, $day->plus(5), $day->name);
        }
    }
}
