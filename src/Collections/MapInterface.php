<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\Collections;

use InvalidArgumentException;
use Manychois\PhpStrong\Collections\ReadonlyMapInterface as IReadonlyMap;
use NoDiscard;

/**
 * Defines a mutable map of items.
 *
 * @template TKey
 * @template TValue
 *
 * @extends IReadonlyMap<TKey, TValue>
 */
interface MapInterface extends IReadonlyMap
{
    /**
     * Adds a key-value pair to the map.
     *
     * @param TKey $key The key to add.
     * @param TValue $value The value to add.
     *
     * @throws InvalidArgumentException If the key is not valid for this map, or the key already exists and the
     *     duplication policy is {@see DuplicationPolicy::ThrowError}.
     */
    public function add(mixed $key, mixed $value): void;

    /**
     * Adds a range of key-value pairs to the map.
     *
     * @param iterable<TKey, TValue> ...$ranges The ranges of key-value pairs to add.
     *
     * @throws InvalidArgumentException If any key is not valid for this map, or a key already exists and the
     *     duplication policy is {@see DuplicationPolicy::ThrowError}.
     */
    public function addRange(iterable ...$ranges): void;

    /**
     * Gets a readonly version of the map.
     *
     * @return IReadonlyMap<TKey, TValue> A readonly version of the map.
     */
    #[NoDiscard]
    public function asReadonly(): IReadonlyMap;

    /**
     * Clears all key-value pairs from the map.
     */
    public function clear(): void;

    /**
     * Removes the key-value pair with the specified key from the map.
     *
     * @param TKey $key The key to remove.
     *
     * @return bool `true` if the key-value pair was found and removed; otherwise, `false`.
     *
     * @throws InvalidArgumentException If the key is not valid for this map.
     */
    public function remove(mixed $key): bool;
}
