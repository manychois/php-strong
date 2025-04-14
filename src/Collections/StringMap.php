<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\Collections;

use Manychois\PhpStrong\Collections\Internal\AbstractNativeMap;
use OutOfBoundsException;
use Override;
use Traversable;
use TypeError;

/**
 * Represents a dictionary-like collection of keys and values that provides fast lookups based on keys.
 * Each key in the Map must be unique and can be used to store and retrieve its associated value.
 *
 * @template T
 *
 * @template-extends AbstractNativeMap<string,T>
 */
final class StringMap extends AbstractNativeMap
{
    /**
     * Initializes a new map of objects.
     *
     * @template TObject of object
     *
     * @param class-string<TObject>               $class   The class of the items in the map.
     * @param array<TObject>|Traversable<TObject> $initial The initial items of the map.
     * @param DuplicateKeyPolicy                  $policy  The policy to handle duplicate keys.
     *
     * @return self<TObject> The new instance.
     */
    public static function ofObject(
        string $class,
        array|Traversable $initial = [],
        DuplicateKeyPolicy $policy = DuplicateKeyPolicy::Overwrite
    ): self {
        // @phpstan-ignore argument.type
        return new self($initial, $policy);
    }

    /**
     * Gets the value associated with the specified key in the map.
     *
     * @param string $key The key to retrieve the value for.
     *
     * @return T The value associated with the specified key.
     */
    public function get(string $key): mixed
    {
        if (!isset($this->source[$key])) {
            throw new OutOfBoundsException(\sprintf('Key not found: %s', $key));
        }

        return $this->source[$key];
    }

    /**
     * Gets the value associated with the specified key in the map, or null if the key does not exist.
     *
     * @param string $key The key to retrieve the value for.
     *
     * @return T|null The value associated with the specified key, or null if the key does not exist.
     */
    public function nullGet(string $key): mixed
    {
        return $this->source[$key] ?? null;
    }

    /**
     * Sets the value associated with the specified key in the map.
     *
     * @param string $key   The key to set the value for.
     * @param T      $value The value to set.
     */
    public function set(string $key, mixed $value): void
    {
        if ($this->duplicateKeyPolicy === DuplicateKeyPolicy::ThrowException) {
            if (isset($this->source[$key])) {
                throw new OutOfBoundsException(\sprintf('Duplicate key: %s', $key));
            }
        } elseif ($this->duplicateKeyPolicy === DuplicateKeyPolicy::Ignore) {
            if (isset($this->source[$key])) {
                return;
            }
        }

        $this->source[$key] = $value;
    }

    #region extends AbstractNativeMap

    /**
     * @inheritDoc
     */
    #[Override]
    public function offsetExists(mixed $offset): bool
    {
        if (!\is_string($offset)) {
            throw new TypeError('Key must be a string.');
        }

        return isset($this->source[$offset]);
    }

    /**
     * @inheritDoc
     *
     * @return T The value associated with the specified key.
     */
    #[Override]
    public function offsetGet(mixed $offset): mixed
    {

        if (!\is_string($offset)) {
            throw new TypeError('Key must be a string.');
        }
        if (!isset($this->source[$offset])) {
            throw new OutOfBoundsException(\sprintf('Key not found: %s', $offset));
        }

        return $this->source[$offset];
    }


    /**
     * @inheritDoc
     */
    #[Override]
    public function offsetSet(mixed $offset, mixed $value): void
    {
        if (!\is_string($offset)) {
            throw new TypeError('Key must be a string.');
        }
        $this->set($offset, $value);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function offsetUnset(mixed $offset): void
    {
        if (!\is_string($offset)) {
            throw new TypeError('Key must be a string.');
        }
        unset($this->source[$offset]);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    protected function internalSet(int|string $key, mixed $value): void
    {
        if (\is_int($key)) {
            $key = \strval($key);
        }
        $this->set($key, $value);
    }

    #endregion extends AbstractNativeMap
}
