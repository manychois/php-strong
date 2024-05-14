<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\Psr\Log;

use Manychois\PhpStrong\Collections\StringMap;

/**
 * A logger that writes log messages to a file.
 */
class FileLogger extends AbstractLogger
{
    private readonly string $destination;

    public function __construct(LogLevel $level, string $destination)
    {
        parent::__construct($level);
        $this->destination = $destination;
    }

    #region extends AbstractLogger

    protected function writeLog(string $message, StringMap $context): void
    {
        if ($this->destination === '') {
            \error_log($message);
        } else {
            if (!\str_ends_with($message, "\n")) {
                $message .= "\n";
            }
            \error_log($message, 3, $this->destination);
        }
    }

    #endregion extends AbstractLogger
}
