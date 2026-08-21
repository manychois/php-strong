# Time — `Manychois\PhpStrong\Time`

Date and time support: PSR-20 clocks, plus calendar types that no PSR covers.

## Clocks (PSR-20)

Two implementations of `Psr\Clock\ClockInterface`. All times are returned in UTC; convert to a user or site timezone
only at the presentation edge.

### `UtcClock`

```php
$clock = new UtcClock();
$clock->now(); // DateTimeImmutable in UTC, independent of date_default_timezone_get()
```

`now()` builds `new DateTimeImmutable('now', new DateTimeZone('UTC'))`, so it ignores PHP's default timezone setting
(`date.timezone` / `date_default_timezone_set()`). Inject it wherever "the current time" is needed instead of calling
`new DateTimeImmutable()` directly — that keeps the code testable with `TestClock`.

## `TestClock extends UtcClock`

A clock frozen at a caller-controlled instant, for deterministic tests.

```php
$clock = new TestClock('2026-01-01T00:00:00Z');   // DateTimeInterface | string | null (null = now)
$clock->now();                                     // 2026-01-01T00:00:00+00:00, every call
$clock->advance('PT5M');                           // DateInterval or ISO 8601 duration
$clock->setNow(new DateTime('2026-06-01T12:00:00+10:00')); // converted to UTC: 02:00:00+00:00
```

| Method | Notes |
| ------ | ----- |
| `__construct(DateTimeInterface\|string\|null $now = null)` | Strings are parsed as UTC unless they carry an offset. |
| `setNow(DateTimeInterface\|string $now): void` | Non-UTC values are converted to UTC. |
| `advance(DateInterval\|string $interval): void` | Use an inverted `DateInterval` to go backwards. |
| `now(): DateTimeImmutable` | Returns the same instance until changed. |

Because it extends `UtcClock`, code type-hinted on `UtcClock` (not only `ClockInterface`) also accepts it.

## `DayOfWeek`

An `int`-backed enum numbered as in ISO-8601, so `Monday` is `1` and `Sunday` is `7` — the same numbering as the
`N` format character of `DateTimeInterface::format()`.

```php
use Manychois\PhpStrong\Time\DayOfWeek;

DayOfWeek::fromDate(new DateTimeImmutable('2026-08-21')); // DayOfWeek::Friday
DayOfWeek::Friday->next();                                // DayOfWeek::Saturday
DayOfWeek::Friday->plus(3);                               // DayOfWeek::Monday, wraps around the week
```

| Member | Description |
| ------ | ----------- |
| `fromDate(DateTimeInterface $date)` | The day on which `$date` falls, read in that date's own timezone. |
| `next()` | The following day, wrapping `Sunday` to `Monday`. |
| `plus(int $days)` | The day `$days` later, wrapping around the week. Negative values move backwards. |
| `previous()` | The preceding day, wrapping `Monday` to `Sunday`. |
