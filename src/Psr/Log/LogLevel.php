<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\Psr\Log;

use Psr\Log\LogLevel as PsrLogLevel;

/**
 * Describes log levels.
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
     * Returns whether the severity of the log level is lower than the given one.
     *
     * @param LogLevel $level The log level to compare against.
     *
     * @return bool True if the severity of the log level is lower than the given one, false otherwise.
     */
    public function isLowerThan(self $level): bool
    {
        return $this->severity() < $level->severity();
    }

    /**
     * Returns the severity of the log level.
     *
     * @return int The severity of the log level.
     */
    public function severity(): int
    {
        return match ($this) {
            self::Debug => 1,
            self::Info => 2,
            self::Notice => 3,
            self::Warning => 4,
            self::Error => 5,
            self::Critical => 6,
            self::Alert => 7,
            self::Emergency => 8,
        };
    }
}
