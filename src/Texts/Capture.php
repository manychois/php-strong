<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\Texts;

use InvalidArgumentException;

/**
 * Represents the result from a single successful text capture.
 */
class Capture
{
    /**
     * The zero-based byte offset of the capture in the subject string, or `null` when the offset is unknown
     * or the group did not participate in the match.
     *
     * @var ?int
     *
     * @phpstan-var ?non-negative-int
     */
    public readonly ?int $index;
    public readonly string $value;

    /**
     * Initializes a new instance of the Capture class.
     *
     * @param string $value The captured substring.
     * @param ?int $index The byte offset of the capture in the subject string.
     *
     * @throws InvalidArgumentException if $index is negative.
     *
     * @phpstan-param ?non-negative-int $index
     */
    public function __construct(string $value, ?int $index = null)
    {
        if ($index !== null && $index < 0) {
            throw new InvalidArgumentException('Index must not be negative.');
        }

        $this->index = $index;
        $this->value = $value;
    }
}
