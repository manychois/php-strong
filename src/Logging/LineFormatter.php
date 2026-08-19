<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\Logging;

use Manychois\PhpStrong\Logging\Internal\ContextTail;
use Override;

/**
 * Formats a log as a single line: `[time] channel.LEVEL: message {unused context as JSON}`,
 * followed by the `exception` context entry, if any.
 */
final class LineFormatter implements FormatterInterface
{
    private readonly string $dateFormat;
    private readonly MessageInterpolator $interpolator;
    private readonly ContextTail $tail;

    /**
     * Creates a line formatter.
     *
     * @param string $dateFormat A `date()` format for the timestamp.
     */
    public function __construct(string $dateFormat = 'Y-m-d\TH:i:s.vP')
    {
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
        $line = sprintf(
            '[%s] %s.%s: %s',
            $log->time->format($this->dateFormat),
            $log->channel,
            strtoupper($log->level->value),
            $this->interpolator->interpolate($log->message, $log->context),
        );
        return $line . $this->tail->render($log) . \PHP_EOL;
    }

    #endregion
}
