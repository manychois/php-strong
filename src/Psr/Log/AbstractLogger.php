<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\Psr\Log;

use Manychois\PhpStrong\AbstractObject;
use Manychois\PhpStrong\Collections\StringMap;
use Manychois\PhpStrong\Text\RegularExpressions\Regex;
use Psr\Log\LoggerInterface;
use Stringable;
use TypeError;

/**
 * An abstract logger that provides a default implementation of the log methods.
 */
abstract class AbstractLogger extends AbstractObject implements LoggerInterface
{
    public readonly LogLevel $level;

    public function __construct(LogLevel $level)
    {
        $this->level = $level;
    }

    #region implements LoggerInterface

    public function emergency(string|Stringable $message, array $context = []): void
    {
        $this->logOnLevel(LogLevel::Emergency, $message, $context);
    }

    public function alert(string|Stringable $message, array $context = []): void
    {
        $this->logOnLevel(LogLevel::Alert, $message, $context);
    }

    public function critical(string|Stringable $message, array $context = []): void
    {
        $this->logOnLevel(LogLevel::Critical, $message, $context);
    }

    public function error(string|Stringable $message, array $context = []): void
    {
        $this->logOnLevel(LogLevel::Error, $message, $context);
    }

    public function warning(string|Stringable $message, array $context = []): void
    {
        $this->logOnLevel(LogLevel::Warning, $message, $context);
    }

    public function notice(string|Stringable $message, array $context = []): void
    {
        $this->logOnLevel(LogLevel::Notice, $message, $context);
    }

    public function info(string|Stringable $message, array $context = []): void
    {
        $this->logOnLevel(LogLevel::Info, $message, $context);
    }

    public function debug(string|Stringable $message, array $context = []): void
    {
        $this->logOnLevel(LogLevel::Debug, $message, $context);
    }

    public function log($level, string|Stringable $message, array $context = []): void
    {
        if (!($level instanceof LogLevel)) {
            if (\is_string($level)) {
                $level = LogLevel::from($level);
            } elseif ($level instanceof Stringable) {
                $level = LogLevel::from($level->__toString());
            } else {
                throw new TypeError(\sprintf('Invalid type used for log level: %s', \get_debug_type($level)));
            }
        }
        $this->logOnLevel($level, $message, $context);
    }

    #endreigon implements LoggerInterface

    /**
     * Logs a message if the specified log level is greater than or equal to the logger's level.
     *
     * @param LogLevel             $level   The log level of the message.
     * @param string|Stringable    $message The message to log.
     * @param array<string, mixed> $context The context to interpolate into the message.
     */
    public function logOnLevel(LogLevel $level, string|Stringable $message, array $context = []): void
    {
        if ($this->level->isLowerThan($level)) {
            return;
        }

        $context = new StringMap($context);
        if (!$context->hasKey('level')) {
            $context->add('level', $level);
        }

        $this->writeLog($this->interpolate($level, $message, $context), $context);
    }

    /**
     * Writes a log message.
     *
     * @param string           $message The interpolated message to write.
     * @param StringMap<mixed> $context The context to interpolate into the message.
     */
    abstract protected function writeLog(string $message, StringMap $context): void;

    /**
     * Interpolates context values into the message.
     *
     * @param LogLevel          $level   The log level of the message.
     * @param string|Stringable $message The message to interpolate.
     * @param StringMap<mixed>  $context The context to interpolate into the message.
     *
     * @return string The interpolated message.
     */
    protected function interpolate(LogLevel $level, string|Stringable $message, StringMap $context): string
    {
        $message = \is_string($message) ? $message : $message->__toString();

        $regex = new Regex('/(?<!\\\\)\{(\w+)\}/');
        $matches = $regex->matchAll($message);
        $foundKeys = [];
        foreach ($matches as $match) {
            /** @var \Manychois\PhpStrong\Text\RegularExpressions\Capture $capture */
            $capture = $match->captures->item(0);
            $foundKeys[] = $capture->value;
        }
        $foundKeys = \array_unique($foundKeys);

        $replacePairs = [];
        foreach ($foundKeys as $key) {
            $contextValue = $context[$key] ?? '';
            if (!\is_scalar($contextValue)) {
                if ($contextValue instanceof Stringable) {
                    $contextValue = $contextValue->__toString();
                } else {
                    $contextValue = '(' . \get_debug_type($contextValue) . ')';
                }
            }
            $replacePairs['{' . $key . '}'] = $contextValue;
        }

        if (\count($replacePairs) > 0) {
            $message = \strtr($message, $replacePairs);
        }

        return $message;
    }
}
