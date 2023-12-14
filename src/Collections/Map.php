<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\Collections;

use ArrayAccess;
use Countable;
use InvalidArgumentException;
use IteratorAggregate;
use Traversable;

/**
 * Represents a strongly-typed collection of keys and values.
 *
 * @template TKey of int|string|object
 *
 * @template TValue
 *
 * @implements ArrayAccess<TKey, TValue>
 * @implements IteratorAggregate<TKey, TValue>
 */
class Map implements ArrayAccess, Countable, IteratorAggregate
{
    public readonly string $keyConstraint;
    public readonly string $valueConstraint;
    private readonly bool $requireObjKeyMap;
    /** @var array<int, TKey> */
    private array $objKeyMap = [];
    /** @var array<int|string, TValue> */
    private array $map = [];

    #region factory methods

    /**
     * Creates a new map of integer keys and boolean values.
     *
     * @return self<int, bool> The new map of integer keys and boolean values.
     */
    public static function intToBool(): self
    {
        /** @var self<int, bool> $map */
        $map = new self('int', 'bool');

        return $map;
    }

    /**
     * Creates a new map of integer keys and float values.
     *
     * @return self<int, float> The new map of integer keys and float values.
     */
    public static function intToFloat(): self
    {
        /** @var self<int, float> $map */
        $map = new self('int', 'float');

        return $map;
    }

    /**
     * Creates a new map of integer keys and integer values.
     *
     * @return self<int, int> The new map of integer keys and integer values.
     */
    public static function intToInt(): self
    {
        /** @var self<int, int> $map */
        $map = new self('int', 'int');

        return $map;
    }

    /**
     * Creates a new map of integer keys and object values.
     *
     * @template V of object
     *
     * @param class-string<V> $valueClass The class name of the values.
     *
     * @return self<int, V> The new map of integer keys and object values.
     */
    public static function intToObject(string $valueClass): self
    {
        /** @var self<int, V> $map */
        $map = new self('int', $valueClass);

        return $map;
    }

    /**
     * Creates a new map of integer keys and string values.
     *
     * @return self<int, string> The new map of integer keys and string values.
     */
    public static function intToString(): self
    {
        /** @var self<int, string> $map */
        $map = new self('int', 'string');

        return $map;
    }

    /**
     * Creates a new map of object keys and boolean values.
     *
     * @template K of object
     *
     * @param class-string<K> $keyClass The class name of the keys.
     *
     * @return Map<K, bool> The new map of object keys and boolean values.
     */
    public static function objectToBool(string $keyClass): self
    {
        /** @var self<K, bool> $map */
        $map = new self($keyClass, 'bool');

        return $map;
    }

    /**
     * Creates a new map of object keys and float values.
     *
     * @template K of object
     *
     * @param class-string<K> $keyClass The class name of the keys.
     *
     * @return Map<K, float> The new map of object keys and float values.
     */
    public static function objectToFloat(string $keyClass): self
    {
        /** @var self<K, float> $map */
        $map = new self($keyClass, 'float');

        return $map;
    }

    /**
     * Creates a new map of object keys and integer values.
     *
     * @template K of object
     *
     * @param class-string<K> $keyClass The class name of the keys.
     *
     * @return Map<K, int> The new map of object keys and integer values.
     */
    public static function objectToInt(string $keyClass): self
    {
        /** @var self<K, int> $map */
        $map = new self($keyClass, 'int');

        return $map;
    }

    /**
     * Creates a new map of object keys and object values.
     *
     * @template K of object
     * @template V of object
     *
     * @param class-string<K> $keyClass   The class name of the keys.
     * @param class-string<V> $valueClass The class name of the values.
     *
     * @return Map<K, V> The new map of object keys and object values.
     */
    public static function objectToObject(string $keyClass, string $valueClass): self
    {
        /** @var Map<K, V> $map */
        $map = new self($keyClass, $valueClass);

        return $map;
    }

    /**
     * Creates a new map of object keys and string values.
     *
     * @template K of object
     *
     * @param class-string<K> $keyClass The class name of the keys.
     *
     * @return Map<K, string> The new map of object keys and string values.
     */
    public static function objectToString(string $keyClass): self
    {
        /** @var self<K, string> $map */
        $map = new self($keyClass, 'string');

        return $map;
    }

    /**
     * Creates a new map of string keys and boolean values.
     *
     * @return self<string, bool> The new map of string keys and boolean values.
     */
    public static function stringToBool(): self
    {
        /** @var self<string, bool> $map */
        $map = new self('string', 'bool');

        return $map;
    }

    /**
     * Creates a new map of string keys and float values.
     *
     * @return self<string, float> The new map of string keys and float values.
     */
    public static function stringToFloat(): self
    {
        /** @var self<string, float> $map */
        $map = new self('string', 'float');

        return $map;
    }

    /**
     * Creates a new map of string keys and integer values.
     *
     * @return self<string, int> The new map of string keys and integer values.
     */
    public static function stringToInt(): self
    {
        /** @var self<string, int> $map */
        $map = new self('string', 'int');

        return $map;
    }

    /**
     * Creates a new map of string keys and object values.
     *
     * @template V of object
     *
     * @param class-string<V> $valueClass The class name of the values.
     *
     * @return self<string, V> The new map of string keys and object values.
     */
    public static function stringToObject(string $valueClass): self
    {
        /** @var self<string, V> $map */
        $map = new self('string', $valueClass);

        return $map;
    }

    /**
     * Creates a new map of string keys and string values.
     *
     * @return self<string, string> The new map of string keys and string values.
     */
    public static function stringToString(): self
    {
        /** @var self<string, string> $map */
        $map = new self('string', 'string');

        return $map;
    }

    #endregion factory methods

    /**
     * Creates a strongly-typed map.
     *
     * @param string $keyConstraint   The type constraint for the keys.
     * @param string $valueConstraint The type constraint for the values.
     */
    private function __construct(string $keyConstraint, string $valueConstraint)
    {
        $this->keyConstraint = $keyConstraint;
        $this->valueConstraint = $valueConstraint;
        $this->requireObjKeyMap = $keyConstraint !== 'int' && $keyConstraint !== 'string';
    }

    #region implements ArrayAccess

    /**
     * @inheritDoc
     */
    public function offsetExists(mixed $offset): bool
    {
        if ($this->requireObjKeyMap) {
            if (\is_object($offset)) {
                $id = \spl_object_id($offset);

                return isset($this->objKeyMap[$id]);
            }

            return false;
        }

        if (!$this->matchKeyConstraint($offset)) {
            return false;
        }

        \assert(\is_int($offset) || \is_string($offset));

        return isset($this->map[$offset]);
    }

    /**
     * @inheritDoc
     */
    public function offsetGet(mixed $offset): mixed
    {
        if (!$this->matchKeyConstraint($offset)) {
            throw new InvalidArgumentException(\sprintf('The offset must be of type %s.', $this->keyConstraint));
        }

        if ($this->requireObjKeyMap) {
            \assert(\is_object($offset));
            $id = \spl_object_id($offset);
            if (!isset($this->map[$id])) {
                throw new InvalidArgumentException('The offset does not exist in the map.');
            }

            return $this->map[$id];
        }

        \assert(\is_int($offset) || \is_string($offset));
        if (!isset($this->map[$offset])) {
            throw new InvalidArgumentException('The offset does not exist in the map.');
        }

        return $this->map[$offset];
    }

    /**
     * @inheritDoc
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        if (!$this->matchKeyConstraint($offset)) {
            throw new InvalidArgumentException(\sprintf('The offset must be of type %s.', $this->keyConstraint));
        }
        if (!$this->matchValueConstraint($value)) {
            throw new InvalidArgumentException(\sprintf('The value must be of type %s.', $this->valueConstraint));
        }

        if ($this->requireObjKeyMap) {
            \assert(\is_object($offset));
            $id = \spl_object_id($offset);
            $this->objKeyMap[$id] = $offset;
            $this->map[$id] = $value;
        } else {
            \assert(\is_int($offset) || \is_string($offset));
            $this->map[$offset] = $value;
        }
    }

    /**
     * @inheritDoc
     */
    public function offsetUnset(mixed $offset): void
    {
        if ($this->requireObjKeyMap && \is_object($offset)) {
            $id = \spl_object_id($offset);
            unset($this->objKeyMap[$id]);
            unset($this->map[$id]);
        } elseif (\is_int($offset) || \is_string($offset)) {
            unset($this->map[$offset]);
        }
    }

    #endregion implements ArrayAccess

    #region implements Countable

    /**
     * @inheritDoc
     */
    public function count(): int
    {
        return \count($this->map);
    }

    #endregion implements Countable

    #region implements IteratorAggregate

    /**
     * @inheritDoc
     *
     * @return Traversable<TKey, TValue>
     */
    public function getIterator(): Traversable
    {
        if ($this->requireObjKeyMap) {
            foreach ($this->objKeyMap as $id => $key) {
                yield $key => $this->map[$id];
            }
        } else {
            foreach ($this->map as $key => $value) {
                yield $key => $value; // @phpstan-ignore-line
            }
        }
    }

    #endregion implements IteratorAggregate

    /**
     * Adds a value to the map.
     *
     * @param TKey   $key   The key to add.
     * @param TValue $value The value to add.
     */
    public function add(mixed $key, mixed $value): void
    {
        if (!$this->matchKeyConstraint($key)) {
            throw new InvalidArgumentException(\sprintf('The key must be of type %s.', $this->keyConstraint));
        }
        if (!$this->matchValueConstraint($value)) {
            throw new InvalidArgumentException(\sprintf('The value must be of type %s.', $this->valueConstraint));
        }

        if ($this->requireObjKeyMap) {
            \assert(\is_object($key));
            $id = \spl_object_id($key);
            $this->objKeyMap[$id] = $key;
            $this->map[$id] = $value;
        } else {
            \assert(\is_int($key) || \is_string($key));
            $this->map[$key] = $value;
        }
    }

    /**
     * Finds the first key that matches the specified value.
     *
     * @param TValue $value The value to find.
     *
     * @return TKey The first key that matches the specified value.
     */
    public function find(mixed $value): mixed
    {
        if (!$this->matchValueConstraint($value)) {
            throw new InvalidArgumentException(\sprintf('The value must be of type %s..', $this->valueConstraint));
        }
        $index = \array_search($value, $this->map, true);
        if ($index === false) {
            throw new \InvalidArgumentException('The value does not exist in the map.');
        }

        /** @var TKey $key */
        $key = $this->requireObjKeyMap ? $this->objKeyMap[$index] : $index;

        return $key;
    }

    /**
     * Gets the value associated with the specified key.
     *
     * @param TKey $key The key of the value to get.
     *
     * @return TValue The value associated with the specified key.
     */
    public function get(mixed $key): mixed
    {
        if (!$this->matchKeyConstraint($key)) {
            throw new InvalidArgumentException(\sprintf('The key must be of type %s.', $this->keyConstraint));
        }
        if ($this->requireObjKeyMap) {
            \assert(\is_object($key));
            $id = \spl_object_id($key);
            if (!isset($this->map[$id])) {
                throw new InvalidArgumentException('The key does not exist in the map.');
            }

            return $this->map[$id];
        }

        \assert(\is_int($key) || \is_string($key));
        if (!isset($this->map[$key])) {
            throw new InvalidArgumentException('The key does not exist in the map.');
        }

        return $this->map[$key];
    }

    /**
     * Determines whether the specified value matches the key type constraint.
     *
     * @param mixed $value The key to check.
     *
     * @return bool true if the key matches the key type constraint; otherwise, false.
     */
    public function matchKeyConstraint(mixed $value): bool
    {
        return match ($this->keyConstraint) {
            'int' => \is_int($value),
            'string' => \is_string($value),
            default => $value instanceof $this->keyConstraint,
        };
    }

    /**
     * Determines whether the specified value matches the value type constraint.
     *
     * @param mixed $value The value to check.
     *
     * @return bool true if the value matches the value type constraint; otherwise, false.
     */
    public function matchValueConstraint(mixed $value): bool
    {
        return match ($this->valueConstraint) {
            'bool' => \is_bool($value),
            'float' => \is_float($value),
            'int' => \is_int($value),
            'string' => \is_string($value),
            default => $value instanceof $this->valueConstraint,
        };
    }

    /**
     * Removes the value with the specified key from the map.
     *
     * @param mixed $key The key of the value to remove.
     *
     * @return bool true if the value is successfully removed; otherwise, false.
     */
    public function remove(mixed $key): bool
    {
        if (!$this->matchKeyConstraint($key)) {
            throw new InvalidArgumentException(\sprintf('The key must be of type %s.', $this->keyConstraint));
        }

        if ($this->requireObjKeyMap) {
            \assert(\is_object($key));
            $id = \spl_object_id($key);
            if (!isset($this->map[$id])) {
                return false;
            }
            unset($this->objKeyMap[$id]);
            unset($this->map[$id]);

            return true;
        }

        \assert(\is_int($key) || \is_string($key));
        if (!isset($this->map[$key])) {
            return false;
        }
        unset($this->map[$key]);

        return true;
    }

    /**
     * Finds the first key that matches the specified value.
     * Null is returned if no match is found.
     *
     * @param TValue $value The value to find.
     *
     * @return null|TKey The first key that matches the specified value, or null if no match is found.
     */
    public function tryFind(mixed $value): mixed
    {
        if (!$this->matchValueConstraint($value)) {
            throw new InvalidArgumentException(\sprintf('The value must be of type %s..', $this->valueConstraint));
        }
        $index = \array_search($value, $this->map, true);
        if ($index === false) {
            return null;
        }

        /** @var TKey $key */
        $key = $this->requireObjKeyMap ? $this->objKeyMap[$index] : $index;

        return $key;
    }

    /**
     * Gets the value associated with the specified key.
     * A default value is returned if no match is found.
     *
     * @param TKey        $key     The key of the value to get.
     * @param null|TValue $default The default value to return if no match is found.
     *
     * @return null|TValue The value associated with the specified key.
     */
    public function tryGet(mixed $key, mixed $default = null): mixed
    {
        if (!$this->matchKeyConstraint($key)) {
            throw new InvalidArgumentException(\sprintf('The key must be of type %s.', $this->keyConstraint));
        }
        if ($this->requireObjKeyMap) {
            \assert(\is_object($key));
            $id = \spl_object_id($key);
            if (!isset($this->map[$id])) {
                return null;
            }

            return $this->map[$id];
        }

        \assert(\is_int($key) || \is_string($key));
        if (!isset($this->map[$key])) {
            return null;
        }

        return $this->map[$key];
    }
}
