<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\Collections;

use Manychois\PhpStrong\Collections\Internal\AbstractMap;

/**
 * Represents a collection of key-value pairs.
 *
 * @template TKey of bool|int|string|object
 * @template TValue
 *
 * @template-extends AbstractMap<TKey,TValue>
 */
class Map extends AbstractMap
{
    /**
     * Initializes a new instance of the Map class.
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

    /**
     * Removes all items from the map.
     */
    public function clear(): void
    {
        if ($this->keys !== null) {
            $this->keys = [];
        }
        $this->values = [];
    }

    /**
     * Removes the value associated with the specified key.
     *
     * @param TKey $key The key of the value to remove.
     */
    public function remove(mixed $key): void
    {
        if ($this->keys === null) {
            \assert(\is_int($key) || \is_string($key));
            unset($this->values[$key]);
        } else {
            $validKey = $this->getArrayKey($key);
            unset($this->keys[$validKey], $this->values[$validKey]);
        }
    }

    /**
     * Sets the value associated with the specified key.
     *
     * @param TKey   $key   The key of the value.
     * @param TValue $value The value to set.
     */
    public function set(mixed $key, mixed $value): void
    {
        $this->internalSet($key, $value);
    }
}
