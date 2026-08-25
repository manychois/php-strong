<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\Logging;

/**
 * Renders a log as a string.
 */
interface FormatterInterface
{
    /**
     * Formats a log.
     *
     * @param Log $log The log to render.
     *
     * @return string The rendered text, including any trailing newline.
     */
    public function format(Log $log): string;
}
