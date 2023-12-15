<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\Preg;

/**
 * Represents a capture result.
 */
class Capture
{
    public readonly int $offset;
    public readonly string $value;

    /**
     * Creates a capture result.
     *
     * @param array<int, int|string> $result First item is the captured text, second item is the offset.
     *
     * @phpstan-param array{}|array{0: string, 1: int} $result
     */
    public function __construct(array $result)
    {
        if (\count($result) === 0) {
            $this->offset = 0;
            $this->value = '';
        } else {
            $this->value = $result[0];
            $this->offset = $result[1];
        }
    }
}
