<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\Logging;

use Manychois\PhpStrong\Clock\UtcClock;
use Override;
use Psr\Clock\ClockInterface as IClock;
use Psr\Log\LoggerInterface as ILogger;
use Psr\Log\LoggerTrait;
use Stringable;

/**
 * PSR-3 logger that dispatches logs to a list of handlers.
 */
final class Logger implements ILogger
{
    use LoggerTrait;

    private readonly string $channel;
    /** @var list<HandlerInterface> */
    private array $handlers;
    private readonly IClock $clock;

    /**
     * Creates a logger for a channel.
     *
     * @param string $channel Name attached to every log, e.g. `app`, `db`, `http`; lets handlers and readers
     * tell apart logs from different parts of the application.
     * @param iterable<HandlerInterface> $handlers Handlers that receive every log, in order. Each handler decides
     * on its own whether to act on a log (e.g. by minimum level); the logger does no filtering.
     * @param ?IClock $clock Source of log timestamps. Defaults to `UtcClock`; pass a `TestClock` in tests for
     * deterministic output.
     */
    public function __construct(string $channel = 'app', iterable $handlers = [], ?IClock $clock = null)
    {
        $this->channel = $channel;
        $this->handlers = [];
        foreach ($handlers as $handler) {
            $this->handlers[] = $handler;
        }
        $this->clock = $clock ?? new UtcClock();
    }

    /**
     * Appends a handler.
     *
     * @param HandlerInterface $handler Handler to receive subsequent logs.
     */
    public function pushHandler(HandlerInterface $handler): void
    {
        $this->handlers[] = $handler;
    }

    /**
     * Returns a copy of this logger with a different channel name, sharing the same handlers.
     *
     * @param string $channel The new channel name.
     *
     * @return self The new logger.
     */
    public function withChannel(string $channel): self
    {
        return new self($channel, $this->handlers, $this->clock);
    }

    #region implements ILogger

    /**
     * @inheritDoc
     *
     * @param array<mixed> $context Values for `{placeholder}` interpolation and structured data.
     */
    #[Override]
    public function log(mixed $level, string|Stringable $message, array $context = []): void
    {
        $normalized = [];
        foreach ($context as $key => $value) {
            $normalized[(string) $key] = $value;
        }
        $log = new Log(
            $this->channel,
            LogLevel::fromPsr($level),
            (string) $message,
            $normalized,
            $this->clock->now(),
        );
        foreach ($this->handlers as $handler) {
            $handler->handle($log);
        }
    }

    #endregion
}
