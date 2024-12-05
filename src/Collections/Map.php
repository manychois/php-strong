<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\Collections;

use Manychois\PhpStrong\Collections\Internal\AbstractArrayMap;
use Manychois\PhpStrong\Collections\Internal\MapFactoryTrait;

/**
 * Represents a collection of key-value pairs.
 *
 * @template TKey
 * @template TValue
 *
 * @template-extends AbstractArrayMap<TKey,TValue>
 */
class Map extends AbstractArrayMap
{
    use MapFactoryTrait;

    /**
     * Sets the value associated with the specified key.
     * If the policy does not allow duplicate keys, and the key already exists,
     * an `OutOfBoundsException` is thrown.
     *
     * @param TKey   $key   The key of the value to set.
     * @param TValue $value The value to set.
     */
    public function set(mixed $key, mixed $value): void
    {
        $this->internalSet($key, $value);
    }

    /**
     * Removes the value associated with the specified key.
     *
     * @param TKey $key The key of the value to remove.
     */
    public function remove(mixed $key): void
    {
        if ($this->keys === null) {
            unset($this->values[$key]);
        } else {
            $validKey = $this->getArrayKey($key);
            unset($this->keys[$validKey]);
            unset($this->values[$validKey]);
        }
    }
}
