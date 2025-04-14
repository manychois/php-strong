<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\Collections\Internal;

use Manychois\PhpStrong\Collections\DuplicateKeyPolicy;

/**
 * Represents a dictionary-like collection of keys and values that provides fast lookups based on keys.
 * Each key in the Map must be unique and can be used to store and retrieve its associated value.
 *
 * @template TKey of int|string
 * @template TValue
 *
 * @template-extends AbstractArray<TKey,TValue>
 */
abstract class AbstractNativeMap extends AbstractArray
{
    public readonly DuplicateKeyPolicy $duplicateKeyPolicy;

    /**
     * Creates a new instance of the map.
     *
     * @param iterable<TKey,TValue> $initial The initial values to populate the map with.
     * @param DuplicateKeyPolicy    $policy  The policy to handle duplicate keys.
     */
    final public function __construct(
        iterable $initial = [],
        DuplicateKeyPolicy $policy = DuplicateKeyPolicy::Overwrite
    ) {
        $this->duplicateKeyPolicy = $policy;
        if ($initial instanceof self) {
            // @phpstan-ignore assign.propertyType
            $this->source = $initial->source;
        } elseif (\is_array($initial)) {
            $this->source = $initial;
        } else {
            foreach ($initial as $key => $value) {
                $this->internalSet($key, $value);
            }
        }
    }

    /**
     * Sets the value associated with the specified key in the map.
     *
     * @param int|string $key   The key to set the value for.
     * @param TValue     $value The value to set.
     */
    abstract protected function internalSet(int|string $key, mixed $value): void;
}
