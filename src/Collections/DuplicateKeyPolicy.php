<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\Collections;

/**
 * Specifies the behavior to use when a duplicate key is found.
 */
enum DuplicateKeyPolicy: int
{
    /**
     * Throw an exception when a duplicate key is found.
     */
    case ThrowException = 0;
    /**
     * Ignore the new item when a duplicate key is found.
     */
    case Ignore = 1;
    /**
     * Overwrite the existing item with the new item when a duplicate key is found.
     */
    case Overwrite = 2;
}
