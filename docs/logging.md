# PSR-3 Logger — `Manychois\PhpStrong\Logging`

`Logger` implements `Psr\Log\LoggerInterface`. Every call builds an immutable `Log` and passes it, in order, to
each registered handler. The logger itself does no filtering; handlers decide what to keep.

## Quick start

```php
use Manychois\PhpStrong\Logging\ConsoleHandler;
use Manychois\PhpStrong\Logging\Logger;
use Manychois\PhpStrong\Logging\LogLevel;
use Manychois\PhpStrong\Logging\StreamHandler;

$logger = new Logger('app', [
    new ConsoleHandler(),                                      // terminal, coloured when on a TTY
    new StreamHandler('/var/log/app.log', LogLevel::Warning),  // warnings and above to a file
]);

$logger->info('User {name} logged in', ['name' => 'Bob', 'ip' => '10.0.0.1']);
// [2026-08-19T18:55:00.123+00:00] app.INFO: User Bob logged in {"ip":"10.0.0.1"}

$db = $logger->withChannel('db'); // same handlers, different channel
```

## `Logger`

```php
new Logger(string $channel = 'app', iterable $handlers = [], ?ClockInterface $clock = null)
```

| Member | Description |
| ------ | ----------- |
| `$channel` | Name attached to every log (`app`, `db`, `http`, …). |
| `$handlers` | `HandlerInterface` instances; each receives every log. |
| `$clock` | PSR-20 clock for timestamps; defaults to [`UtcClock`](time.md). Use `TestClock` in tests. |
| `pushHandler(HandlerInterface)` | Appends a handler. |
| `withChannel(string): self` | New logger sharing handlers and clock, different channel. |
| `log($level, $message, $context)` + the eight PSR-3 level methods | Invalid levels throw `Psr\Log\InvalidArgumentException`. |

## `LogLevel`

String-backed enum of the eight PSR-3 levels (`LogLevel::Debug` … `LogLevel::Emergency`, values equal to
`Psr\Log\LogLevel` constants).

- `LogLevel::fromPsr(mixed $level): self` — accepts a case or a case-insensitive PSR-3 string; throws otherwise.
- `atLeast(LogLevel $other): bool` — severity comparison.
- `severity(): int` — 0 (debug) to 7 (emergency).

## `Log`

Immutable value object with public readonly properties: `channel`, `level` (`LogLevel`), `message` (raw, not
interpolated), `context` (`array<string, mixed>`), `time` (`DateTimeImmutable`).

## Handlers

All handlers implement `HandlerInterface::handle(Log $log): void` and take a `LogLevel $minLevel`
(default `Debug`); logs below it are ignored.

| Handler | Behaviour |
| ------- | --------- |
| `StreamHandler($stream, $minLevel, ?$formatter)` | `$stream` is an open resource, a file path, or a stream URL (`php://stderr`). Paths are opened lazily in append mode; parent directories are created for plain file paths (not for `scheme://` URLs). Default formatter: `LineFormatter`. |
| `ConsoleHandler($minLevel, ?bool $colors, ?$formatter, $stdout, $stderr)` | `debug`..`notice` → stdout, `warning`+ → stderr; `$stdout`/`$stderr` accept an open resource or a stream URL (default `php://stdout`/`php://stderr`). `$colors = null` auto-detects: on when stderr is a TTY and `NO_COLOR` is unset. `$colors` configures the default `ConsoleFormatter` only; it is ignored when `$formatter` is given. |
| `ArrayHandler($minLevel)` | Keeps logs in `$logs` (`list<Log>`); `clear()` empties it. Handy in tests. |

## Formatters

`FormatterInterface::format(Log $log): string` returns text including a trailing newline.

| Formatter | Output |
| --------- | ------ |
| `LineFormatter(string $dateFormat = 'Y-m-d\TH:i:s.vP')` | `[time] channel.LEVEL: message {extra}` |
| `ConsoleFormatter(bool $colors = false, string $dateFormat = 'H:i:s')` | `HH:MM:SS LEVEL     message {extra}`; level coloured by severity, message too for `error` and above. Empty `$dateFormat` omits the time. |

Both formatters:

- interpolate `{key}` placeholders in the message from context (`MessageInterpolator`);
- append context keys that were **not** interpolated as a JSON object (`{extra}`), skipping `exception`;
- if `context['exception']` is a `Throwable`, append a newline and its string form (class, message, trace).

### Placeholder rendering

`MessageInterpolator` is instantiated by the formatters; its `interpolate(string $message, array $context): string`
instance method replaces `{key}` with:

| Value | Rendered as |
| ----- | ----------- |
| `string`, `Stringable` | verbatim |
| `DateTimeInterface` | RFC 3339 (`2026-01-02T03:04:05+00:00`) |
| anything else | `json_encode()` (`null`, `true`, `1.5`, `[1,"x"]`, `{"a":1}`); `[type]` if encoding fails |

Keys missing from the context leave the placeholder untouched.

## Extending

Implement `HandlerInterface` for new destinations (syslog, UDP, database) and `FormatterInterface` for new layouts
(JSON lines, logfmt). Handlers receive the full `Log`, so they can filter on channel or context as well as level.
