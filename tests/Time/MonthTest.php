<?php

declare(strict_types=1);

namespace Manychois\PhpStrongTests\Time;

use DateTimeImmutable;
use DateTimeZone;
use Manychois\PhpStrong\Time\Month;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class MonthTest extends TestCase
{
    #[Test]
    public function cases_areNumberedFromOne(): void
    {
        self::assertSame(1, Month::January->value);
        self::assertSame(12, Month::December->value);
        self::assertCount(12, Month::cases());
    }

    #[Test]
    public function fromDate_matchesTheNFormatCharacter(): void
    {
        $date = new DateTimeImmutable('2026-08-21', new DateTimeZone('UTC'));

        self::assertSame(Month::August, Month::fromDate($date));
        self::assertSame((int) $date->format('n'), Month::fromDate($date)->value);
    }

    #[Test]
    public function fromDate_usesTheTimezoneOfTheDate(): void
    {
        $utc = new DateTimeImmutable('2026-08-31 23:30:00', new DateTimeZone('UTC'));
        $sydney = $utc->setTimezone(new DateTimeZone('Australia/Sydney'));

        self::assertSame(Month::August, Month::fromDate($utc));
        self::assertSame(Month::September, Month::fromDate($sydney));
    }

    #[Test]
    #[DataProvider('provideMonthLengths')]
    public function length_matchesTheCalendar(Month $month, int $year, int $expected): void
    {
        self::assertSame($expected, $month->length($year));
    }

    /**
     * @return array<int, array{Month, int, int}>
     */
    public static function provideMonthLengths(): array
    {
        return [
            [Month::January, 2026, 31],
            [Month::April, 2026, 30],
            [Month::June, 2026, 30],
            [Month::September, 2026, 30],
            [Month::November, 2026, 30],
            [Month::December, 2026, 31],
            [Month::February, 2023, 28],
            [Month::February, 2024, 29],
            [Month::February, 1900, 28],
            [Month::February, 2000, 29],
            [Month::February, 2100, 28],
            [Month::February, 2400, 29],
        ];
    }

    #[Test]
    public function length_agreesWithTheDateExtension(): void
    {
        foreach ([1999, 2000, 2023, 2024, 2100] as $year) {
            foreach (Month::cases() as $month) {
                $reference = new DateTimeImmutable(
                    sprintf('%04d-%02d-01', $year, $month->value),
                    new DateTimeZone('UTC'),
                );

                self::assertSame(
                    (int) $reference->format('t'),
                    $month->length($year),
                    sprintf('%s %d', $month->name, $year),
                );
            }
        }
    }

    #[Test]
    public function next_wrapsFromDecemberToJanuary(): void
    {
        self::assertSame(Month::February, Month::January->next());
        self::assertSame(Month::January, Month::December->next());
    }

    #[Test]
    public function previous_wrapsFromJanuaryToDecember(): void
    {
        self::assertSame(Month::January, Month::February->previous());
        self::assertSame(Month::December, Month::January->previous());
    }

    #[Test]
    #[DataProvider('provideOffsets')]
    public function plus_wrapsAroundTheYear(Month $month, int $offset, Month $expected): void
    {
        self::assertSame($expected, $month->plus($offset));
    }

    /**
     * @return array<int, array{Month, int, Month}>
     */
    public static function provideOffsets(): array
    {
        return [
            [Month::January, 0, Month::January],
            [Month::January, 12, Month::January],
            [Month::January, 13, Month::February],
            [Month::January, -1, Month::December],
            [Month::January, -13, Month::December],
            [Month::December, 1, Month::January],
            [Month::December, -1, Month::November],
            [Month::October, 5, Month::March],
            [Month::October, 1200, Month::October],
            [Month::October, -1200, Month::October],
        ];
    }

    #[Test]
    public function plus_staysExactAtIntegerLimits(): void
    {
        // PHP_INT_MAX % 12 is 7 and PHP_INT_MIN % 12 is -8.
        self::assertSame(Month::August, Month::January->plus(\PHP_INT_MAX));
        self::assertSame(Month::May, Month::January->plus(\PHP_INT_MIN));
    }

    #[Test]
    public function plus_agreesWithRepeatedNextCalls(): void
    {
        foreach (Month::cases() as $month) {
            $walked = $month;
            for ($i = 0; $i < 7; $i++) {
                $walked = $walked->next();
            }

            self::assertSame($walked, $month->plus(7), $month->name);
        }
    }
}
