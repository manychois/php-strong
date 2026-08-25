<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\Logging\Internal;

use Manychois\PhpStrong\Logging\Log;
use Throwable;

/**
 * Renders the part of a log that follows the message: leftover context as JSON and the `exception` entry.
 *
 * @internal
 */
final class ContextTail
{
    /**
     * Renders the tail for a log.
     *
     * @param Log $log The log.
     *
     * @return string Empty, or ` {json}` for context keys not interpolated into the message, then a newline and the
     * stringified `exception` context entry if it is a `Throwable`. No trailing newline.
     */
    public function render(Log $log): string
    {
        $tail = '';
        $extra = [];
        foreach ($log->context as $key => $value) {
            if ($key !== 'exception' && !str_contains($log->message, '{' . $key . '}')) {
                $extra[$key] = $value;
            }
        }
        if ($extra !== []) {
            $flags = \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_PARTIAL_OUTPUT_ON_ERROR;
            $json = json_encode($extra, $flags);
            $tail .= ' ' . ($json === false ? '{}' : $json);
        }
        $exception = $log->context['exception'] ?? null;
        if ($exception instanceof Throwable) {
            $tail .= \PHP_EOL . $exception;
        }

        return $tail;
    }
}
