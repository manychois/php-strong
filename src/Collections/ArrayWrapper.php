<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\Collections;

use RuntimeException;
use Stringable;

/**
 * A wrapper class for array to provide a more type-safe way to manipulate array.
 */
class ArrayWrapper
{
    /**
     * @var array<mixed>
     */
    private array $array;

    /**
     * Create a new instance of ArrayWrapper.
     *
     * @param array<mixed> $array The array to be wrapped.
     */
    public function __construct(array $array)
    {
        $this->array = $array;
    }

    /**
     * Returns the value of the specified key as a string.
     *
     * @param int|string $key             The key of the value to be returned.
     * @param bool       $allowStringable Whether to accept Stringable object as a valid string value.
     *
     * @return null|string The value of the specified key as a string, or null if the key does not exist.
     */
    public function getString(int|string $key, bool $allowStringable = true): ?string
    {
        $value = $this->array[$key] ?? null;
        if ($value !== null && !\is_string($value)) {
            if ($allowStringable && $value instanceof Stringable) {
                $value = $value->__toString();
            } else {
                throw new RuntimeException("The value of key '$key' is not a string.");
            }
        }

        return $value;
    }

    /**
     * Returns the array representation of this object.
     *
     * @return array<mixed> The array representation of this object.
     */
    public function toArray(): array
    {
        return $this->array;
    }
}
