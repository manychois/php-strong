<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\Collections;

use BadMethodCallException;

/**
 * Represents a read-only map of keys and values with string keys.
 *
 * @template TValue The type of the values in the map.
 *
 * @phpstan-extends StringMap<TValue>
 */
class ReadonlyStringMap extends StringMap
{
    /**
     * Initializes a new map that contains keys and values copied from the specified iterable.
     *
     * @param string            $className The class name of the values in the map.
     * @param iterable<TObject> $source    The iterable whose keys and values are copied to the new map.
     *                                     If the iterable happens to have duplicate keys, the later
     *                                     value will overwrite the previous one.
     *
     * @return self<TObject> The new list.
     *
     * @template TObject The type of the values in the map.
     *
     * @phpstan-param class-string<TObject> $className
     */
    public static function ofType(string $className, iterable $source = []): self
    {
        /** @var self<TObject> $result */
        $result = new self($source);

        return $result;
    }

    #region extends StringMap

    public function add(string $key, mixed $value): void
    {
        throw new BadMethodCallException('This map is read-only.');
    }

    public function clear(): void
    {
        throw new BadMethodCallException('This map is read-only.');
    }

    public function remove(string $key): mixed
    {
        throw new BadMethodCallException('This map is read-only.');
    }

    public function set(string $key, mixed $value): mixed
    {
        throw new BadMethodCallException('This map is read-only.');
    }

    #endregion extends StringMap
}
