# PSR-20 Clock — `Manychois\PhpStrong\Clock`

Two implementations of `Psr\Clock\ClockInterface`. All times are returned in UTC; convert to a user or site timezone
only at the presentation edge.

## `UtcClock`

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
