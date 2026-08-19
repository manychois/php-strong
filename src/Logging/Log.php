<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\Logging;

use DateTimeImmutable;

/**
 * An immutable log entry produced by the logger and passed to handlers.
 */
final class Log
{
    public readonly string $channel;
    public readonly LogLevel $level;
    public readonly string $message;
    /** @var array<string, mixed> */
    public readonly array $context;
    public readonly DateTimeImmutable $time;

    /**
     * @param array<string, mixed> $context
     */
    public function __construct(
        string $channel,
        LogLevel $level,
        string $message,
        array $context,
        DateTimeImmutable $time,
    ) {
        $this->channel = $channel;
        $this->level = $level;
        $this->message = $message;
        $this->context = $context;
        $this->time = $time;
    }
}
