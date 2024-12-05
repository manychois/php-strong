<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\Collections\Internal;

use Manychois\PhpStrong\Collections\DuplicateKeyPolicy;
use Traversable;

/**
 * A trait that provides factory methods for creating instances of the map.
 */
trait MapFactoryTrait
{
    /**
     * Initializes a new map of objects using string keys.
     *
     * @template T of object
     *
     * @param class-string<T>                       $class   The class of the values in the map.
     * @param array<string,T>|Traversable<string,T> $initial The initial items of the map.
     * @param DuplicateKeyPolicy                    $policy  Action to take when a duplicate key is found.
     *
     * @return self<string,T> The new instance.
     */
    public static function ofStringToObject(
        string $class,
        array|Traversable $initial = [],
        DuplicateKeyPolicy $policy = DuplicateKeyPolicy::ThrowException
    ): self {
        // @phpstan-ignore return.type
        return new self($initial, $policy);
    }
}
