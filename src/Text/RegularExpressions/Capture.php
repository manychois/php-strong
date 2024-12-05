<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\Text\RegularExpressions;

/**
 * Represents the results from a single successful subexpression capture.
 */
class Capture
{
    public readonly int $index;
    public readonly string $value;

    /**
     * Initializes a new instance of the Capture class.
     *
     * @param string $value The captured substring.
     * @param int    $index The position in the input string where the capture was made.
     *                      If it is not known, use -1.
     */
    public function __construct(string $value, int $index = -1)
    {
        $this->index = $index;
        $this->value = $value;
    }
}
