<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\Collections;

/**
 * Specifies the behavior to use when a duplicate key is found.
 */
enum DuplicateKeyPolicy: int
{
    /**
     * Overwrite the existing item with the new item when a duplicate key is found.
     */
    case Overwrite = 0;
    /**
     * Throw an exception when a duplicate key is found.
     */
    case ThrowException = 1;
    /**
     * Ignore the new item when a duplicate key is found.
     */
    case Ignore = 2;
}
