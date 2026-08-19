<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\Logging;

use Manychois\PhpStrong\Logging\Internal\ContextTail;
use Override;

/**
 * Human-oriented single-line format for terminals: `HH:MM:SS LEVEL message {unused context}`, optionally coloured
 * by level with ANSI escapes, followed by the `exception` context entry, if any.
 */
final class ConsoleFormatter implements FormatterInterface
{
    private readonly bool $colors;
    private readonly string $dateFormat;
    private readonly MessageInterpolator $interpolator;
    private readonly ContextTail $tail;

    /**
     * Creates a console formatter.
     *
     * @param bool $colors Whether to wrap the level (and message for error levels) in ANSI colour codes.
     * @param string $dateFormat A `date()` format for the timestamp; empty string omits it.
     */
    public function __construct(bool $colors = false, string $dateFormat = 'H:i:s')
    {
        $this->colors = $colors;
        $this->dateFormat = $dateFormat;
        $this->interpolator = new MessageInterpolator();
        $this->tail = new ContextTail();
    }

    #region implements FormatterInterface

    /**
     * @inheritDoc
     */
    #[Override]
    public function format(Log $log): string
    {
        $level = str_pad(strtoupper($log->level->value), 9);
        $message = $this->interpolator->interpolate($log->message, $log->context);
        if ($this->colors) {
            $code = $this->colorCode($log->level);
            $level = "\e[{$code}m{$level}\e[0m";
            if ($log->level->atLeast(LogLevel::Error)) {
                $message = "\e[{$code}m{$message}\e[0m";
            }
        }
        $line = $this->dateFormat === '' ? '' : $log->time->format($this->dateFormat) . ' ';
        $line .= $level . ' ' . $message;
        return $line . $this->tail->render($log) . \PHP_EOL;
    }

    #endregion

    /**
     * Returns the SGR parameter for a level.
     *
     * @param LogLevel $level The log level.
     *
     * @return string ANSI SGR code, e.g. `1;31` for bold red.
     */
    private function colorCode(LogLevel $level): string
    {
        return match ($level) {
            LogLevel::Debug => '90',
            LogLevel::Info => '32',
            LogLevel::Notice => '36',
            LogLevel::Warning => '33',
            LogLevel::Error => '31',
            LogLevel::Critical, LogLevel::Alert, LogLevel::Emergency => '1;31',
        };
    }
}
