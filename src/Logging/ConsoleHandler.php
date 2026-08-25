<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\Logging;

use InvalidArgumentException;
use Override;
use RuntimeException;

/**
 * Writes logs to the terminal: levels below `warning` go to stdout, `warning` and above go to stderr.
 * Colours are enabled automatically when stderr is a TTY and `NO_COLOR` is not set.
 */
final class ConsoleHandler implements HandlerInterface
{
    private readonly FormatterInterface $formatter;
    private readonly LogLevel $minLevel;
    /** @var resource */
    private readonly mixed $stdout;
    /** @var resource */
    private readonly mixed $stderr;

    /**
     * Creates a console handler.
     *
     * @param LogLevel $minLevel Logs below this level are ignored.
     * @param ?bool $colors Force colours on/off; `null` auto-detects from the stderr stream and `NO_COLOR`. Ignored
     * when `$formatter` is given.
     * @param ?FormatterInterface $formatter Renders each log; defaults to `ConsoleFormatter`.
     * @param resource|string|null $stdout Stream (or stream URL) for `debug`..`notice`; defaults to `php://stdout`.
     * @param resource|string|null $stderr Stream (or stream URL) for `warning` and above; defaults to `php://stderr`.
     *
     * @throws InvalidArgumentException If `$stdout` or `$stderr` is neither a resource nor a string.
     * @throws RuntimeException If a stream URL cannot be opened.
     */
    public function __construct(
        LogLevel $minLevel = LogLevel::Debug,
        ?bool $colors = null,
        ?FormatterInterface $formatter = null,
        mixed $stdout = null,
        mixed $stderr = null,
    ) {
        $this->minLevel = $minLevel;
        $this->stdout = self::open($stdout ?? 'php://stdout');
        $this->stderr = self::open($stderr ?? 'php://stderr');
        $this->formatter = $formatter ?? new ConsoleFormatter($colors ?? $this->detectColors());
    }

    #region implements HandlerInterface

    /**
     * @inheritDoc
     */
    #[Override]
    public function handle(Log $log): void
    {
        if (!$log->level->atLeast($this->minLevel)) {
            return;
        }
        $stream = $log->level->atLeast(LogLevel::Warning) ? $this->stderr : $this->stdout;
        fwrite($stream, $this->formatter->format($log));
    }

    #endregion

    /**
     * Returns the given stream, or opens a stream URL for writing.
     *
     * @param mixed $stream An open stream resource or a stream URL.
     *
     * @return resource The open stream.
     *
     * @throws InvalidArgumentException If `$stream` is neither a resource nor a string.
     * @throws RuntimeException If the stream URL cannot be opened.
     */
    private static function open(mixed $stream): mixed
    {
        if (is_resource($stream)) {
            return $stream;
        }
        if (!is_string($stream)) {
            throw new InvalidArgumentException('Stream must be a resource or a string URL.');
        }
        $resource = @fopen($stream, 'w');
        if ($resource === false) {
            throw new RuntimeException(sprintf('Unable to open "%s".', $stream));
        }

        return $resource;
    }

    /**
     * Decides whether to emit ANSI colours.
     *
     * @return bool True if stderr is a TTY and `NO_COLOR` is unset.
     */
    private function detectColors(): bool
    {
        return getenv('NO_COLOR') === false && stream_isatty($this->stderr);
    }
}
