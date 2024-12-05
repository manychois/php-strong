<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\Collections\Internal;

use Countable;
use Generator;
use IteratorAggregate;
use Manychois\PhpStrong\Collections\DuplicateKeyPolicy;
use Manychois\PhpStrong\Collections\ReadonlyMap;
use Manychois\PhpStrong\Defaults\DefaultEqualityComparer;
use Manychois\PhpStrong\EqualityComparerInterface;
use OutOfBoundsException;
use Stringable;
use TypeError;

/**
 * Represents the vase class for a collection of key-value pairs.
 *
 * @template TKey
 * @template TValue
 *
 * @template-implements IteratorAggregate<TKey,TValue>
 */
abstract class AbstractMap implements Countable, IteratorAggregate
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
     * @param iterable<TKey,TValue> $initial            The initial items of the map.
     * @param DuplicateKeyPolicy    $duplicateKeyPolicy Action to take when a duplicate key is found.
     */
    public function __construct(
        iterable $initial = [],
        DuplicateKeyPolicy $duplicateKeyPolicy = DuplicateKeyPolicy::ThrowException
    ) {
        $this->duplicateKeyPolicy = $duplicateKeyPolicy;
        if (\is_array($initial) && \count($initial) > 0) {
            $this->keys = null;
            $this->values = $initial;
        } elseif ($initial instanceof self && $duplicateKeyPolicy === $initial->duplicateKeyPolicy) {
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
                    throw new \InvalidArgumentException('The key already exists.');
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
                    throw new \InvalidArgumentException('The key already exists.');
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

    /**
     * Returns a read-only version of the map.
     *
     * @param DuplicateKeyPolicy|null $duplicateKeyPolicy The policy to apply when a duplicate key is found.
     *                                                    If it is null, the policy of the original map is used.
     *
     * @return ReadonlyMap<TKey,TValue> The read-only version of the map.
     */
    final public function asReadonly(?DuplicateKeyPolicy $duplicateKeyPolicy = null): ReadonlyMap
    {
        $duplicateKeyPolicy ??= $this->duplicateKeyPolicy;
        if ($this instanceof ReadonlyMap && $duplicateKeyPolicy === $this->duplicateKeyPolicy) {
            return $this;
        }

        // @phpstan-ignore return.type
        return new ReadonlyMap($this, $duplicateKeyPolicy);
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
        $found = \in_array($target, $this->values, true);
        if (!$found) {
            $eq ??= new DefaultEqualityComparer();
            foreach ($this->values as $value) {
                if ($eq->equals($value, $target)) {
                    $found = true;

                    break;
                }
            }
        }

        return $found;
    }

    /**
     * Gets the value associated with the specified key.
     * If the key does not exist, an `OutOfBoundsException` is thrown.
     *
     * @param TKey $key The key of the value to get.
     *
     * @return TValue The value associated with the specified key.
     */
    final public function get(mixed $key): mixed
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
     * Gets the value as an integer.
     * If the key does not exist, an `OutOfBoundsException` is thrown.
     * If the value is not an integer, or cannot be converted to an integer, a `TypeError` is thrown.
     *
     * @param TKey $key The key of the value to get.
     *
     * @return int The value associated with the specified key.
     */
    final public function getInt(mixed $key): int
    {
        $value = $this->get($key);
        if (\is_int($value)) {
            return $value;
        }

        if (\is_string($value) && !\is_numeric($value)) {
            throw new TypeError('Value is not a numeric string.');
        }
        if (\is_scalar($value) || $value === null) {
            return \intval($value);
        }

        throw new TypeError(\sprintf('Value type is %s.', \get_debug_type($value)));
    }

    /**
     * Gets the value as a string.
     * If the key does not exist, an `OutOfBoundsException` is thrown.
     * If the value is not a string, or cannot be converted to a string, a `TypeError` is thrown.
     *
     * @param TKey $key The key of the value to get.
     *
     * @return string The value associated with the specified key.
     */
    final public function getString(mixed $key): string
    {
        $value = $this->get($key);
        if (\is_string($value)) {
            return $value;
        }
        if (\is_scalar($value) || $value instanceof Stringable) {
            return \strval($value);
        }
        if ($value === null) {
            return '';
        }

        throw new TypeError(\sprintf('Value type is %s.', \get_debug_type($value)));
    }

    /**
     * Gets the value as the specified class object.
     * If the key does not exist, an `OutOfBoundsException` is thrown.
     * If the value is not of the specified class, a `TypeError` is thrown.
     *
     * @template TObject of object
     *
     * @param TKey                  $key   The key of the value to get.
     * @param class-string<TObject> $class The class name of the object.
     *
     * @return TObject The value associated with the specified key.
     */
    final public function getObject(mixed $key, string $class): object
    {
        $value = $this->get($key);
        if (\is_object($value) && $value instanceof $class) {
            return $value;
        }

        throw new TypeError(\sprintf('Value type is %s.', \get_debug_type($value)));
    }

    /**
     * Determines whether the map contains a specific key.
     *
     * @param TKey $key The key to locate in the map.
     *
     * @return bool true if the map contains the key; otherwise, false.
     */
    final public function hasKey(mixed $key): bool
    {
        if ($this->keys === null) {
            \assert(\is_int($key) || \is_string($key));

            return \array_key_exists($key, $this->values);
        }

        $validKey = $this->getArrayKey($key);

        return \array_key_exists($validKey, $this->values);
    }

    /**
     * Returns the map as an array.
     * The original keys are not preserved if they are not integers or strings.
     *
     * @return array<TValue> The map as an array.
     */
    final public function toArray(): array
    {
        return $this->values;
    }

    #region implements Countable

    /**
     * Returns the number of items in the map.
     *
     * @return non-negative-int The number of items in the map.
     */
    final public function count(): int
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
    final public function getIterator(): Generator
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
}
