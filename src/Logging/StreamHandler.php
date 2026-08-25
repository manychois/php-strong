<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\Logging;

use InvalidArgumentException;
use Override;
use RuntimeException;

/**
 * Writes formatted logs to a stream resource or a file path.
 */
final class StreamHandler implements HandlerInterface
{
    private readonly FormatterInterface $formatter;
    private readonly LogLevel $minLevel;
    /** @var resource|string */
    private mixed $stream;

    /**
     * Creates a handler that writes to a stream.
     *
     * @param resource|string $stream An open stream resource, or a path / stream URL opened lazily in append mode.
     * @param LogLevel $minLevel Logs below this level are ignored.
     * @param ?FormatterInterface $formatter Renders each log; defaults to `LineFormatter`.
     *
     * @throws InvalidArgumentException If `$stream` is neither a resource nor a string.
     */
    public function __construct(
        mixed $stream,
        LogLevel $minLevel = LogLevel::Debug,
        ?FormatterInterface $formatter = null,
    ) {
        if (!is_resource($stream) && !is_string($stream)) {
            throw new InvalidArgumentException('Stream must be a resource or a string path.');
        }
        $this->stream = $stream;
        $this->minLevel = $minLevel;
        $this->formatter = $formatter ?? new LineFormatter();
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
        $stream = $this->openStream();
        fwrite($stream, $this->formatter->format($log));
    }

    #endregion

    /**
     * Returns the stream, opening the configured path on first use.
     *
     * @return resource The open stream.
     *
     * @throws RuntimeException If the path cannot be opened.
     */
    private function openStream(): mixed
    {
        $path = $this->stream;
        if (!is_string($path)) {
            return $path;
        }
        $dir = dirname($path);
        if (!str_contains($path, '://') && !is_dir($dir) && !@mkdir($dir, 0o777, true) && !is_dir($dir)) {
            throw new RuntimeException(sprintf('Unable to create log directory "%s".', $dir));
        }
        $stream = @fopen($path, 'a');
        if ($stream === false) {
            throw new RuntimeException(sprintf('Unable to open log stream "%s".', $path));
        }
        $this->stream = $stream;

        return $stream;
    }
}
