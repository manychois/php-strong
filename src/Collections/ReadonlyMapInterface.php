<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\Collections;

use ArrayAccess;
use BadMethodCallException;
use Countable;
use InvalidArgumentException;
use Iterator;
use IteratorAggregate;
use Manychois\PhpStrong\Collections\SequenceInterface as ISequence;
use OutOfBoundsException;
use RuntimeException;

/**
 * Defines a map of items.
 *
 * @template TKey
 * @template TValue
 *
 * @extends ArrayAccess<TKey, TValue>
 * @extends IteratorAggregate<TKey, TValue>
 */
interface ReadonlyMapInterface extends ArrayAccess, Countable, IteratorAggregate
{
    /**
     * The policy for handling duplicate keys.
     */
    public DuplicationPolicy $duplicationPolicy { get; }

    /**
     * Returns the map as an array.
     *
     * @return array<TKey, TValue> The map as an array.
     *
     * @throws RuntimeException If the map cannot be converted to an array due to incompatible key types.
     */
    public function asArray(): array;

    /**
     * Returns a sequence of the entries in the map.
     *
     * @return ISequence<Entry<TKey, TValue>> A sequence of the entries in the map.
     */
    public function entries(): ISequence;

    /**
     * Returns an iterator with the keys and values flipped.
     *
     * @return Iterator<TValue, TKey> An iterator with the keys and values flipped.
     */
    public function flip(): Iterator;

    /**
     * Gets the value associated with the specified key.
     *
     * @param TKey $key The key to get the value for.
     *
     * @return TValue The value associated with the key.
     *
     * @throws InvalidArgumentException If the key is not a valid key type for this map.
     * @throws OutOfBoundsException If the key is not found.
     */
    public function get(mixed $key): mixed;

    /**
     * Checks if the map contains the specified key.
     *
     * @param TKey $key The key to check.
     *
     * @return bool `true` if the map contains the key; otherwise, `false`.
     *
     * @throws InvalidArgumentException If the key is not a valid key type for this map.
     */
    public function has(mixed $key): bool;

    /**
     * Returns a sequence of the keys in the map.
     *
     * @return ISequence<TKey> A sequence of the keys in the map.
     */
    public function keys(): ISequence;

    /**
     * Gets the value associated with the specified key, or `null` if the key is not found.
     *
     * @param TKey $key The key to get the value for.
     *
     * @return ?TValue The value associated with the key, or `null` if the key is not found.
     *
     * @throws InvalidArgumentException If the key is not a valid key type for this map.
     */
    public function nullGet(mixed $key): mixed;

    /**
     * Returns a sequence of the values in the map.
     *
     * @return ISequence<TValue> A sequence of the values in the map.
     */
    public function values(): ISequence;

    #region extends ArrayAccess

    /**
     * @inheritDoc
     *
     * @throws InvalidArgumentException If the offset is not a valid key type.
     */
    public function offsetExists(mixed $offset): bool;

    /**
     * @inheritDoc
     *
     * @throws InvalidArgumentException If the offset is not a valid key type.
     * @throws OutOfBoundsException If the offset is out of bounds.
     */
    public function offsetGet(mixed $offset): mixed;

    /**
     * @inheritDoc
     *
     * @throws BadMethodCallException if the map is readonly.
     * @throws InvalidArgumentException If the offset is not a valid key type.
     * @throws OutOfBoundsException If the offset is out of bounds.
     */
    public function offsetSet(mixed $offset, mixed $value): void;

    /**
     * Unsets the value associated with the specified offset.
     * Unsetting an out-of-bounds offset is a no-op.
     *
     * @throws BadMethodCallException if the map is readonly.
     * @throws InvalidArgumentException If the offset is not a valid key type.
     */
    public function offsetUnset(mixed $offset): void;

    #endregion extends ArrayAccess

    #region extends IteratorAggregate

    /**
     * Returns the iterator for the map.
     *
     * @return Iterator<TKey, TValue> The iterator for the map.
     */
    public function getIterator(): Iterator;

    #endregion extends IteratorAggregate
}
