<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\Texts;

/**
 * Represents the results from a single successful text capture.
 */
class Capture
{
    /**
     * @phpstan-var non-negative-int|null
     */
    public readonly ?int $index;
    public readonly string $value;

    /**
     * Initializes a new instance of the Capture class.
     *
     * @param string $value The captured substring.
     * @param ?int $index The index of the capture in the input string.
     *
     * @phpstan-param ?non-negative-int $index
     */
    public function __construct(string $value, ?int $index = null)
    {
        $this->index = $index;
        $this->value = $value;
    }
}
