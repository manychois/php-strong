<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\Collections;

use Manychois\PhpStrong\Collections\Internal\AbstractNativeMap;
use OutOfBoundsException;
use Override;
use TypeError;

/**
 * Represents a dictionary-like collection of keys and values that provides fast lookups based on keys.
 * Each key in the Map must be unique and can be used to store and retrieve its associated value.
 *
 * @template T
 *
 * @template-extends AbstractNativeMap<int,T>
 */
final class IntMap extends AbstractNativeMap
{
    /**
     * Gets the value associated with the specified key in the map.
     *
     * @param int $key The key to retrieve the value for.
     *
     * @return T The value associated with the specified key.
     */
    public function get(int $key): mixed
    {
        if (!isset($this->source[$key])) {
            throw new OutOfBoundsException(\sprintf('Key not found: %d', $key));
        }

        return $this->source[$key];
    }

    /**
     * Gets the value associated with the specified key in the map, or null if the key does not exist.
     *
     * @param int $key The key to retrieve the value for.
     *
     * @return T|null The value associated with the specified key, or null if the key does not exist.
     */
    public function nullGet(int $key): mixed
    {
        return $this->source[$key] ?? null;
    }

    /**
     * Sets the value associated with the specified key in the map.
     *
     * @param int $key   The key to set the value for.
     * @param T   $value The value to set.
     */
    public function set(int $key, mixed $value): void
    {
        if ($this->duplicateKeyPolicy === DuplicateKeyPolicy::ThrowException) {
            if (isset($this->source[$key])) {
                throw new OutOfBoundsException(\sprintf('Duplicate key: %d', $key));
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
        if (!\is_int($offset)) {
            throw new TypeError('Key must be an integer.');
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

        if (!\is_int($offset)) {
            throw new TypeError('Key must be an integer.');
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
        if (!\is_int($offset)) {
            throw new TypeError('Key must be an integer.');
        }
        $this->set($offset, $value);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function offsetUnset(mixed $offset): void
    {
        if (!\is_int($offset)) {
            throw new TypeError('Key must be an integer.');
        }
        unset($this->source[$offset]);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    protected function internalSet(int|string $key, mixed $value): void
    {
        \assert(\is_int($key), 'Key must be an integer.');
        $this->set($key, $value);
    }

    #endregion extends AbstractNativeMap
}
