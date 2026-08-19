<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\Logging;

use Override;

/**
 * Keeps logs in memory; useful for tests and buffering.
 */
final class ArrayHandler implements HandlerInterface
{
    /** @var list<Log> */
    public private(set) array $logs = [];
    private readonly LogLevel $minLevel;

    /**
     * Creates a handler that stores logs in memory.
     *
     * @param LogLevel $minLevel Logs below this level are ignored.
     */
    public function __construct(LogLevel $minLevel = LogLevel::Debug)
    {
        $this->minLevel = $minLevel;
    }

    /**
     * Removes all stored logs.
     */
    public function clear(): void
    {
        $this->logs = [];
    }

    #region implements HandlerInterface

    /**
     * @inheritDoc
     */
    #[Override]
    public function handle(Log $log): void
    {
        if ($log->level->atLeast($this->minLevel)) {
            $this->logs[] = $log;
        }
    }

    #endregion
}
