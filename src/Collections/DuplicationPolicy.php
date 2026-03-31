<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\Collections;

/**
 * Defines the policy for handling duplicate keys in a map.
 */
enum DuplicationPolicy: string
{
    case Overwrite = 'overwrite';
    case Ignore = 'ignore';
    case ThrowError = 'throw_error';
}
