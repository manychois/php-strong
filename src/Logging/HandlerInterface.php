<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\Logging;

/**
 * Receives logs from a Logger.
 */
interface HandlerInterface
{
    /**
     * Processes a log.
     *
     * @param Log $log The log to handle.
     */
    public function handle(Log $log): void;
}
