<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\Collections;

use Manychois\PhpStrong\Collections\Internal\AbstractMap;

/**
 * Represents a read-only collection of key-value pairs.
 *
 * @template TKey of bool|int|string|object
 * @template TValue
 *
 * @template-extends AbstractMap<TKey,TValue>
 */
class ReadonlyMap extends AbstractMap
{
    /**
     * Initializes a new instance of the ReadonlyMap class.
     *
     * @template TObject of object
     *
     * @param class-string<TObject>    $class              The class of the values in the map.
     * @param iterable<string,TObject> $initial            The initial items of the map.
     * @param DuplicateKeyPolicy       $duplicateKeyPolicy Action to take when a duplicate key is found.
     *
     * @return self<string,TObject> The new instance.
     */
    public static function ofStringToObject(
        string $class,
        iterable $initial = [],
        $duplicateKeyPolicy = DuplicateKeyPolicy::ThrowException
    ): self {
        // @phpstan-ignore return.type
        return new self($initial, $duplicateKeyPolicy);
    }
}
