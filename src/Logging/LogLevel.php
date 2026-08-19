<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\Logging;

use Psr\Log\InvalidArgumentException;
use Psr\Log\LogLevel as PsrLogLevel;

/**
 * PSR-3 log levels ordered by severity.
 */
enum LogLevel: string
{
    case Debug = PsrLogLevel::DEBUG;
    case Info = PsrLogLevel::INFO;
    case Notice = PsrLogLevel::NOTICE;
    case Warning = PsrLogLevel::WARNING;
    case Error = PsrLogLevel::ERROR;
    case Critical = PsrLogLevel::CRITICAL;
    case Alert = PsrLogLevel::ALERT;
    case Emergency = PsrLogLevel::EMERGENCY;

    /**
     * Converts a PSR-3 level value to a LogLevel case.
     *
     * @param mixed $level A LogLevel case or a PSR-3 level string (case-insensitive).
     *
     * @return self The matching level.
     *
     * @throws InvalidArgumentException If the level is not a valid PSR-3 level.
     */
    public static function fromPsr(mixed $level): self
    {
        if ($level instanceof self) {
            return $level;
        }
        if (is_string($level)) {
            $case = self::tryFrom(strtolower($level));
            if ($case !== null) {
                return $case;
            }
        }

        throw new InvalidArgumentException(sprintf('Invalid log level "%s".', get_debug_type($level)));
    }

    /**
     * Returns true if this level is at least as severe as the given level.
     *
     * @param LogLevel $other The level to compare against.
     *
     * @return bool True if this level's severity is greater than or equal to `$other`'s.
     */
    public function atLeast(self $other): bool
    {
        return $this->severity() >= $other->severity();
    }

    /**
     * Returns the numeric severity; higher is more severe.
     *
     * @return int Severity from 0 (debug) to 7 (emergency).
     */
    public function severity(): int
    {
        return match ($this) {
            self::Debug => 0,
            self::Info => 1,
            self::Notice => 2,
            self::Warning => 3,
            self::Error => 4,
            self::Critical => 5,
            self::Alert => 6,
            self::Emergency => 7,
        };
    }
}
