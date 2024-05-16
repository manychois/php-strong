<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\Collections;

use Countable;
use InvalidArgumentException;
use IteratorAggregate;
use Manychois\PhpStrong\AbstractObject;
use OutOfBoundsException;
use Traversable;

/**
 * Represents a map of keys and values with integer keys.
 *
 * @template TValue The type of the values in the map.
 *
 * @phpstan-implements IteratorAggregate<int,TValue>
 *
 * @phpstan-type Predicate callable(TValue,int=):bool
 */
class IntMap extends AbstractObject implements Countable, IteratorAggregate
{
    /**
     * @var array<int,TValue> The internal map.
     */
    private array $map = [];

    /**
     * Initializes a new map that contains keys and values copied from the specified iterable.
     *
     * @param iterable<TValue> $source The iterable whose keys and values are copied to the new map.
     *                                 If the iterable happens to have duplicate keys, the later value will overwrite
     *                                 the previous one.
     */
    public function __construct(iterable $source = [])
    {
        foreach ($source as $key => $value) {
            if (!\is_int($key)) {
                throw new InvalidArgumentException('The iterable key must be an integer.');
            }
            $this->map[$key] = $value;
        }
    }

    #region implements Countable

    public function count(): int
    {
        return \count($this->map);
    }

    #endregion implements Countable

    #region implements IteratorAggregate

    /**
     * @inheritDoc
     *
     * @return Traversable<int,TValue>
     */
    public function getIterator(): Traversable
    {
        foreach ($this->map as $key => $value) {
            yield $key => $value;
        }
    }

    #endregion implements IteratorAggregate

    /**
     * Adds the specified key and value to the map.
     *
     * @param int    $key   The key of the item to add.
     * @param TValue $value The value of the item to add.
     *
     * @throws InvalidArgumentException if the key already exists in the map.
     */
    public function add(int $key, mixed $value): void
    {
        if (isset($this->map[$key])) {
            throw new InvalidArgumentException(\sprintf('The key %s already exists in the map.', $key));
        }

        $this->map[$key] = $value;
    }

    /**
     * Removes all keys and values from the map.
     */
    public function clear(): void
    {
        $this->map = [];
    }

    /**
     * Determines whether the map contains the specified value.
     *
     * @param mixed $value The value to locate in the map.
     *
     * @return bool `true` if the map contains the specified value; otherwise, `false`.
     */
    public function contains(mixed $value): bool
    {
        $found = \in_array($value, $this->map, true);

        if (!$found && $value instanceof AbstractObject) {
            foreach ($this->map as $v) {
                if ($v->equals($value)) {
                    return true;
                }
            }
        }

        return $found;
    }

    /**
     * Performs the specified action on each value in the map.
     *
     * @param callable $action The action to perform on each value.
     *                         The first argument is the value.
     *                         The second argument is the key.
     *                         If the action returns `false`, the iteration will stop.
     *
     * @phpstan-param callable(TValue,int=):mixed $action
     */
    public function each(callable $action): void
    {
        foreach ($this->map as $key => $value) {
            if ($action($value, $key) === false) {
                break;
            }
        }
    }

    /**
     * Returns the key of the first value that satisfies the specified predicate.
     *
     * @param callable $predicate The predicate to test each value against.
     *                            The first argument is the value.
     *                            The second argument is the key.
     *
     * @return int|null The key of the first value that satisfies the predicate; or `null` if no value satisfies the
     * predicate.
     *
     * @phpstan-param Predicate $predicate
     */
    public function findKey(callable $predicate): ?int
    {
        foreach ($this->map as $key => $value) {
            if ($predicate($value, $key)) {
                return $key;
            }
        }

        return null;
    }

    /**
     * Returns the value in the map by the specified key.
     *
     * @param int $key The key of the value to get.
     *
     * @return TValue The value in the map by the specified key.
     *
     * @throws OutOfBoundsException if the key does not exist in the map.
     */
    public function get(int $key): mixed
    {
        if (!\array_key_exists($key, $this->map)) {
            throw new OutOfBoundsException(\sprintf('The key %s does not exist in the map.', $key));
        }

        return $this->map[$key];
    }

    /**
     * Determines whether the map contains the specified key.
     *
     * @param int $key The key to locate in the map.
     *
     * @return bool `true` if the map contains the specified key; otherwise, `false`.
     */
    public function hasKey(int $key): bool
    {
        return \array_key_exists($key, $this->map);
    }

    /**
     * Returns all the keys in the map.
     *
     * @return ArrayList<int> The keys in the map.
     */
    public function keys(): ArrayList
    {
        return new ArrayList(\array_keys($this->map));
    }

    /**
     * Removes the value in the map by the specified key.
     *
     * @param int $key The key of the value to remove.
     *
     * @return TValue|null The removed value, or `null` if the key does not exist in the map.
     */
    public function remove(int $key): mixed
    {
        $removed = $this->map[$key] ?? null;
        unset($this->map[$key]);

        return $removed;
    }

    /**
     * Adds or overrides the value in the map by the specified key.
     *
     * @param int    $key   The key of the item to add.
     * @param TValue $value The value of the item to add.
     *
     * @return TValue|null The previous value of the key; or `null` if the key does not exist in the map.
     */
    public function set(int $key, mixed $value): mixed
    {
        $old = $this->map[$key] ?? null;
        $this->map[$key] = $value;

        return $old;
    }

    /**
     * Returns the native array representation of the map.
     *
     * @return array<int,TValue> The native array representation of the map.
     */
    public function toArray(): array
    {
        return $this->map;
    }

    /**
     * Returns the value in the map by the specified key, or a default value if the key does not exist in the map.
     *
     * @param int         $key     The key of the value to get.
     * @param TValue|null $default The default value to return if the key does not exist in the map.
     *
     * @return TValue|null The value in the map by the specified key, or the default value if the key does not exist in
     * the map.
     */
    public function tryGet(int $key, mixed $default = null): mixed
    {
        if (\array_key_exists($key, $this->map)) {
            return $this->map[$key];
        }

        return $default;
    }

    /**
     * Returns all the values in the map.
     *
     * @return ArrayList<TValue> The values in the map.
     */
    public function values(): ArrayList
    {
        return new ArrayList($this->map);
    }

    #region extends AbstractObject

    public function equals(mixed $other): bool
    {
        if ($this === $other) {
            return true;
        }
        if ($other instanceof self) {
            if (\count($this) !== \count($other)) {
                return false;
            }

            foreach ($this->map as $key => $value) {
                if (!\array_key_exists($key, $other->map)) {
                    return false;
                }

                if (DefaultComparer::areEqual($value, $other->map[$key])) {
                    return false;
                }
            }

            return true;
        }

        return false;
    }

    #endregion extends AbstractObject
}
