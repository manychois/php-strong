<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\Collections\Internal;

use Countable;
use Generator;
use IteratorAggregate;
use Manychois\PhpStrong\Collections\DuplicateKeyPolicy;
use Manychois\PhpStrong\Collections\ReadonlyMap;
use Manychois\PhpStrong\Collections\ReadonlySequence;
use Manychois\PhpStrong\Collections\Sequence;
use Manychois\PhpStrong\Defaults\DefaultEqualityComparer;
use Manychois\PhpStrong\EqualityComparerInterface;
use Manychois\PhpStrong\KeyValuePair;
use Manychois\PhpStrong\ValueObject;
use OutOfBoundsException;
use Traversable;

/**
 * Base class for a collection of key-value pairs based on arrays.
 *
 * @template TKey
 * @template TValue
 *
 * @template-implements IteratorAggregate<TKey,TValue>
 */
abstract class AbstractArrayMap implements Countable, IteratorAggregate
{
    public readonly DuplicateKeyPolicy $duplicateKeyPolicy;
    /**
     * @var array<TKey>|null
     */
    protected array|null $keys;
    /**
     * @var array<TValue>
     */
    protected array $values;

    /**
     * Initializes a new instance of the Map class.
     *
     * @param array<TKey,TValue>|Traversable<TKey,TValue> $initial The initial items of the map.
     * @param DuplicateKeyPolicy                          $policy  Action to take when a duplicate key is found.
     */
    public function __construct(
        array|Traversable $initial = [],
        DuplicateKeyPolicy $policy = DuplicateKeyPolicy::ThrowException
    ) {
        $this->duplicateKeyPolicy = $policy;
        if (\is_array($initial) && \count($initial) > 0) {
            $this->keys = null;
            $this->values = $initial;
        } elseif ($initial instanceof self && $policy === $initial->duplicateKeyPolicy) {
            $this->keys = $initial->keys;
            $this->values = $initial->values;
        } else {
            $this->keys = [];
            $this->values = [];
            foreach ($initial as $key => $value) {
                $this->internalSet($key, $value);
            }
        }
    }

    /**
     * Returns the map as an array.
     * The original keys are not preserved if they are not integers or strings.
     *
     * @return array<TValue> The map as an array.
     */
    public function asArray(): array
    {
        return $this->values;
    }

    /**
     * Returns the map as a sequence of key-value pairs.
     *
     * @return Sequence<KeyValuePair<TKey,TValue>> The map as a sequence of key-value pairs.
     */
    final public function asKeyValuePairs(): Sequence
    {
        $pairs = [];
        foreach ($this->getIterator() as $key => $value) {
            $pairs[] = new KeyValuePair($key, $value);
        }

        // @phpstan-ignore return.type
        return new Sequence($pairs);
    }

    /**
     * Returns the map as a readonly map.
     *
     * @return ReadonlyMap<TKey,TValue> The map as a readonly map.
     */
    final public function asReadonly(): ReadonlyMap
    {
        if ($this instanceof ReadonlyMap) {
            return $this;
        }

        return new ReadonlyMap($this, $this->duplicateKeyPolicy);
    }

    /**
     * Determines whether the map contains a specific value.
     *
     * @param TValue                         $target The value to locate in the map.
     * @param EqualityComparerInterface|null $eq     The equality comparer to use.
     *                                               If null, the default equality
     *                                               comparer is used.
     *
     * @return bool true if the map contains the value; otherwise, false.
     */
    final public function contains(mixed $target, ?EqualityComparerInterface $eq = null): bool
    {
        $eq ??= new DefaultEqualityComparer();
        foreach ($this->getIterator() as $value) {
            if ($eq->equals($value, $target)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Performs the specified action on each key and value in the map.
     *
     * @param callable $action The action to perform on each key and value.
     *                         If the action returns `false`, the iteration stops.
     *
     * @phpstan-param callable(TValue,TKey):mixed $action
     */
    final public function each(callable $action): void
    {
        foreach ($this->getIterator() as $key => $value) {
            $result = $action($value, $key);
            if ($result === false) {
                break;
            }
        }
    }

    /**
     * Gets the value associated with the specified key.
     * If the key does not exist, an `OutOfBoundsException` is thrown.
     *
     * @param TKey $key The key of the value to get.
     *
     * @return TValue The value associated with the specified key.
     */
    public function get(mixed $key): mixed
    {
        if ($this->keys === null) {
            \assert(\is_int($key) || \is_string($key));
            if (\array_key_exists($key, $this->values)) {
                return $this->values[$key];
            }
        } else {
            $validKey = $this->getArrayKey($key);
            if (\array_key_exists($validKey, $this->values)) {
                return $this->values[$validKey];
            }
        }

        throw new OutOfBoundsException('The key does not exist.');
    }

    /**
     * Returns a new value object that wraps the value associated with the specified key.
     *
     * If the key does not exist, an `OutOfBoundsException` is thrown.
     *
     * @param TKey $key The key of the value to get.
     *
     * @return ValueObject The value object that wraps the value associated with the specified key.
     */
    final public function getValueObject(mixed $key): ValueObject
    {
        return new ValueObject($this->get($key));
    }

    /**
     * Determines whether the map contains a specific key.
     *
     * @param TKey $key The key to locate in the map.
     *
     * @return bool true if the map contains the key; otherwise, false.
     */
    public function hasKey(mixed $key): bool
    {
        if ($this->keys === null) {
            \assert(\is_int($key) || \is_string($key));

            return \array_key_exists($key, $this->values);
        }

        $validKey = $this->getArrayKey($key);

        return \array_key_exists($validKey, $this->values);
    }

    /**
     * Returns the keys of the map.
     *
     * @return ReadonlySequence<TKey> The keys of the map.
     */
    public function keys(): ReadonlySequence
    {
        if ($this->keys === null) {
            $keys = \array_keys($this->values);
        } else {
            $keys = $this->keys;
        }

        // @phpstan-ignore return.type
        return new ReadonlySequence($keys);
    }

    /**
     * Returns a new map that uses the values of the current map as keys and the keys of the current map as values.
     *
     * @param DuplicateKeyPolicy $policy The action to take when a duplicate key is found.
     *
     * @return ReadonlyMap<TValue,TKey> A new map that has the keys and values swapped.
     */
    public function swap(DuplicateKeyPolicy $policy = DuplicateKeyPolicy::ThrowException): ReadonlyMap
    {
        $generator = function () {
            foreach ($this->getIterator() as $key => $value) {
                yield $value => $key;
            }
        };

        return new ReadonlyMap($generator(), $policy);
    }

    /**
     * Returns the values of the map.
     *
     * @return ReadonlySequence<TValue> The values of the map.
     */
    public function values(): ReadonlySequence
    {
        return new ReadonlySequence($this->values);
    }

    #region implements Countable

    /**
     * Returns the number of items in the map.
     *
     * @return non-negative-int The number of items in the map.
     */
    public function count(): int
    {
        return \count($this->values);
    }

    #endregion implements Countable

    #region implements IteratorAggregate

    /**
     * Returns an iterator that iterates through the map.
     *
     * @return Generator<TKey,TValue> An iterator that iterates through the map.
     */
    public function getIterator(): Generator
    {
        if ($this->keys === null) {
            foreach ($this->values as $key => $value) {
                // @phpstan-ignore generator.keyType
                yield $key => $value;
            }
        } else {
            foreach ($this->keys as $validKey => $key) {
                yield $key => $this->values[$validKey];
            }
        }
    }

    #endregion implements IteratorAggregate

    /**
     * Converts the map key into a key compatible with the native array.
     *
     * @param mixed $key The key to convert.
     *
     * @return int|string The key compatible with the native array.
     */
    final protected function getArrayKey(mixed $key): int|string
    {
        $eq = new DefaultEqualityComparer();

        return $eq->hash($key);
    }

    /**
     * Sets the value associated with the specified key.
     * This method should only be called in the constructor if the map is read-only.
     *
     * @param TKey   $key   The key of the value.
     * @param TValue $value The value to set.
     */
    final protected function internalSet(mixed $key, mixed $value): void
    {
        if ($this->keys === null) {
            \assert(\is_int($key) || \is_string($key));
            if ($this->duplicateKeyPolicy === DuplicateKeyPolicy::ThrowException) {
                $exists = \array_key_exists($key, $this->values);
                if ($exists) {
                    throw new OutOfBoundsException('The key already exists.');
                }
            } elseif ($this->duplicateKeyPolicy === DuplicateKeyPolicy::Ignore) {
                if (\array_key_exists($key, $this->values)) {
                    return;
                }
            }
            $this->values[$key] = $value;
        } else {
            $validKey = $this->getArrayKey($key);
            if ($this->duplicateKeyPolicy === DuplicateKeyPolicy::ThrowException) {
                $exists = \in_array($validKey, $this->keys, true);
                if ($exists) {
                    throw new OutOfBoundsException('The key already exists.');
                }
            } elseif ($this->duplicateKeyPolicy === DuplicateKeyPolicy::Ignore) {
                if (\in_array($validKey, $this->keys, true)) {
                    return;
                }
            }
            $this->keys[$validKey] = $key;
            $this->values[$validKey] = $value;
        }
    }
}
