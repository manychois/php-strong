<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\Collections;

use ArrayAccess;
use Countable;
use IteratorAggregate;
use JsonSerializable;
use Manychois\PhpStrong\Collections\Internal\AbstractArray;
use Manychois\PhpStrong\EqualInterface;
use Manychois\PhpStrong\EqualityComparerInterface;
use Manychois\PhpStrong\KeyValuePair;
use Manychois\PhpStrong\Registry;
use OutOfBoundsException;
use Traversable;
use TypeError;

/**
 * Represents a dictionary-like collection of keys and values that provides fast lookups based on keys.
 * Each key in the Map must be unique and can be used to store and retrieve its associated value.
 *
 * @template TKey
 * @template TValue
 *
 * @template-implements ArrayAccess<TKey,TValue>
 * @template-implements IteratorAggregate<TKey,TValue>
 */
final class Map implements ArrayAccess, Countable, EqualInterface, IteratorAggregate, JsonSerializable
{
    public readonly DuplicateKeyPolicy $duplicateKeyPolicy;
    /**
     * @var array<non-negative-int,TKey>
     */
    private array $keyOrders = [];
    /**
     * @var array<array<KeyValuePair<TKey,TValue>>>
     */
    private array $kvpListMap = [];

    /**
     * Creates a new instance of the map.
     *
     * @param iterable<TKey,TValue> $initial The initial values to populate the map with.
     * @param DuplicateKeyPolicy    $policy  The policy to handle duplicate keys.
     */
    public function __construct(iterable $initial = [], DuplicateKeyPolicy $policy = DuplicateKeyPolicy::Overwrite)
    {
        $this->duplicateKeyPolicy = $policy;
        if ($initial instanceof self) {
            // @phpstan-ignore assign.propertyType
            $this->kvpListMap = $initial->kvpListMap;
            $this->keyOrders = $initial->keyOrders;
        } else {
            foreach ($initial as $key => $value) {
                $this->set($key, $value);
            }
        }
    }

    /**
     * Gets the value associated with the specified key in the map.
     *
     * @param TKey $key The key to retrieve the value for.
     *
     * @return TValue The value associated with the specified key.
     */
    public function get(mixed $key): mixed
    {
        $eq = $this->getDefaultEqualityComparer();
        $hash = $eq->hash($key);
        if (!isset($this->kvpListMap[$hash])) {
            throw new OutOfBoundsException('Key not found.');
        }
        $pairs = $this->kvpListMap[$hash];
        foreach ($pairs as $pair) {
            if ($eq->equals($pair->key, $key)) {
                return $pair->value;
            }
        }

        throw new OutOfBoundsException('Key not found.');
    }

    /**
     * Gets the value associated with the specified key in the map, or null if the key does not exist.
     *
     * @param TKey $key The key to retrieve the value for.
     *
     * @return TValue|null The value associated with the specified key, or null if the key does not exist.
     */
    public function nullGet(mixed $key): mixed
    {
        $eq = $this->getDefaultEqualityComparer();
        $hash = $eq->hash($key);
        if (!isset($this->kvpListMap[$hash])) {
            return null;
        }
        $pairs = $this->kvpListMap[$hash];
        foreach ($pairs as $pair) {
            if ($eq->equals($pair->key, $key)) {
                return $pair->value;
            }
        }

        return null;
    }

    /**
     * Removes the specified key and its associated value from the map.
     *
     * @param TKey $key The key to remove.
     *
     * @return bool `true` if the key was found and removed, `false` otherwise.
     */
    public function remove(mixed $key): bool
    {
        $eq = $this->getDefaultEqualityComparer();
        $hash = $eq->hash($key);
        if (isset($this->kvpListMap[$hash])) {
            $kvpList = $this->kvpListMap[$hash];
            $count = \count($kvpList);
            for ($i = 0; $i < $count; $i++) {
                $kvp = $kvpList[$i];
                if (!$eq->equals($kvp->key, $key)) {
                    continue;
                }

                \array_splice($kvpList, $i, 1);
                if ($count === 1) {
                    unset($this->kvpListMap[$hash]);
                } else {
                    $this->kvpListMap[$hash] = $kvpList;
                }

                $count = \count($this->keyOrders);
                for ($j = 0; $j < $count; $j++) {
                    if ($eq->equals($this->keyOrders[$j], $key)) {
                        \array_splice($this->keyOrders, $j, 1);

                        break;
                    }
                }

                return true;
            }
        }

        return false;
    }

    /**
     * Sets the value associated with the specified key in the map.
     *
     * @param TKey   $key   The key to set the value for.
     * @param TValue $value The value to set.
     */
    public function set(mixed $key, mixed $value): void
    {
        $eq = $this->getDefaultEqualityComparer();
        $hash = $eq->hash($key);

        if (isset($this->kvpListMap[$hash])) {
            $kvpList = $this->kvpListMap[$hash];
            $count = \count($kvpList);
            for ($i = 0; $i < $count; $i++) {
                $kvp = $kvpList[$i];
                if (!$eq->equals($kvp->key, $key)) {
                    continue;
                }

                if ($this->duplicateKeyPolicy === DuplicateKeyPolicy::ThrowException) {
                    throw new OutOfBoundsException('Duplicate key found.');
                }
                if ($this->duplicateKeyPolicy === DuplicateKeyPolicy::Overwrite) {
                    $kvpList[$i] = new KeyValuePair($key, $value);
                    $this->kvpListMap[$hash] = $kvpList;
                }

                break;
            }
        } else {
            $this->kvpListMap[$hash] = [new KeyValuePair($key, $value)];
            $this->keyOrders[] = $key;
        }
    }

    /**
     * Gets the default equality comparer for the map.
     *
     * @return EqualityComparerInterface The default equality comparer.
     */
    protected function getDefaultEqualityComparer(): EqualityComparerInterface
    {
        return Registry::getEqualityComparer();
    }

    #region implements ArrayAccess

    /**
     * @inheritDoc
     */
    public function offsetExists(mixed $offset): bool
    {
        $eq = $this->getDefaultEqualityComparer();
        $hash = $eq->hash($offset);
        $kvpList = $this->kvpListMap[$hash] ?? null;
        if ($kvpList === null) {
            return false;
        }

        foreach ($kvpList as $kvp) {
            if ($eq->equals($kvp->key, $offset)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @inheritDoc
     *
     * @return TValue The value associated with the specified key.
     */
    public function offsetGet(mixed $offset): mixed
    {
        return $this->get($offset);
    }

    /**
     * @inheritDoc
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        if ($offset === null) {
            throw new TypeError('Key cannot be null.');
        }
        $this->set($offset, $value);
    }

    /**
     * @inheritDoc
     */
    public function offsetUnset(mixed $offset): void
    {
        if ($offset === null) {
            throw new TypeError('Key cannot be null.');
        }
        $this->remove($offset);
    }

    #endregion implements ArrayAccess

    #region implements Countable

    /**
     * @inheritDoc
     */
    public function count(): int
    {
        return \count($this->keyOrders);
    }

    #endregion implements Countable

    #region implements EqualInterface

    /**
     * @inheritDoc
     */
    public function equals(mixed $other): bool
    {
        $eq = $this->getDefaultEqualityComparer();
        if ($other instanceof self) {
            if ($this->count() !== $other->count()) {
                return false;
            }

            foreach ($this->kvpListMap as $kvpList) {
                foreach ($kvpList as $kvp) {
                    $otherValue = $other->nullGet($kvp->key);
                    if (!$eq->equals($kvp->value, $otherValue)) {
                        return false;
                    }
                }
            }

            return true;
        }

        if (\is_array($other) || $other instanceof AbstractArray) {
            if ($this->count() !== \count($other)) {
                return false;
            }

            foreach ($this->kvpListMap as $kvpList) {
                foreach ($kvpList as $kvp) {
                    if (!\is_int($kvp->key) && !\is_string($kvp->key)) {
                        return false;
                    }
                    if (!isset($other[$kvp->key])) {
                        return false;
                    }
                    $otherValue = $other[$kvp->key];
                    if (!$eq->equals($kvp->value, $otherValue)) {
                        return false;
                    }
                }
            }

            return true;
        }

        return false;
    }

    #endregion implements EqualInterface

    #region implements IteratorAggregate

    /**
     * @inheritDoc
     */
    public function getIterator(): Traversable
    {
        foreach ($this->keyOrders as $key) {
            yield $key => $this->get($key);
        }
    }

    #endregion implements IteratorAggregate

    #region implements JsonSerializable

    /**
     * @inheritDoc
     */
    public function jsonSerialize(): mixed
    {
        $result = [];
        foreach ($this->keyOrders as $key) {
            if (!\is_int($key) && !\is_string($key)) {
                throw new TypeError('All keys must be an integer or string for JSON serialization.');
            }

            $result[$key] = $this->get($key);
        }

        return $result;
    }

    #endregion implements JsonSerializable
}
