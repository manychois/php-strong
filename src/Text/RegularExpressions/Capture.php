<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\Text\RegularExpressions;

use Manychois\PhpStrong\AbstractObject;

/**
 * Represents the results from a single successful subexpression capture.
 */
class Capture extends AbstractObject
{
    public readonly int $index;
    public readonly string $value;

    public function __construct(string $value, int $index = -1)
    {
        $this->index = $index;
        $this->value = $value;
    }
}
