<?php

declare(strict_types=1);

namespace Manychois\PhpStrong;

use OutOfBoundsException;
use TypeError;

/**
 * Provides a way to access array values with type safety.
 */
class ArrayAccessor implements ArrayAccessorInterface
{
    /**
     * @var array<string,mixed>
     */
    protected array $inner;

    /**
     * Creates a new ArrayAccessor instance.
     *
     * @param array<string,mixed> $inner The array to be accessed by reference.
     */
    public function __construct(array &$inner)
    {
        $this->inner = &$inner;
    }

    #region implementation ArrayAccessorInterface

    /**
     * @inheritDoc
     */
    public function accessor(string $key): ArrayAccessorInterface
    {
        if ($this->has($key)) {
            $value = &$this->inner[$key];
            if (\is_array($value)) {
                // @phpstan-ignore argument.type
                return new self($value);
            }
            if ($value instanceof ArrayAccessorInterface) {
                return $value;
            }

            throw new TypeError(
                \sprintf(
                    'The value associated with key "%s" is not an array or an ArrayAccessor, but of type %s.',
                    $key,
                    \get_debug_type($value)
                )
            );
        }

        throw new OutOfBoundsException(
            \sprintf(
                'The key "%s" does not exist in the array.',
                $key
            )
        );
    }

    /**
     * @inheritDoc
     */
    public function get(string $key): mixed
    {
        return $this->inner[$key] ?? null;
    }

    /**
     * @inheritDoc
     */
    public function set(string $key, mixed $value): void
    {
        $this->inner[$key] = $value;
    }

    /**
     * @inheritDoc
     */
    public function has(string $key): bool
    {
        return \array_key_exists($key, $this->inner);
    }

    /**
     * @inheritDoc
     */
    public function delete(string $key): void
    {
        unset($this->inner[$key]);
    }

    /**
     * @inheritDoc
     */
    public function asBool(string $key, bool $default = false): bool
    {
        if ($this->has($key)) {
            $value = $this->get($key);
            if (\is_bool($value)) {
                return $value;
            }
            if (\is_scalar($value)) {
                $converted = \filter_var($value, \FILTER_VALIDATE_BOOLEAN, \FILTER_NULL_ON_FAILURE);
                if (\is_bool($converted)) {
                    return $converted;
                }
            }

            throw new TypeError(
                \sprintf(
                    'The value associated with key "%s" is not a boolean, but of type %s.',
                    $key,
                    \get_debug_type($value)
                )
            );
        }

        return $default;
    }

    /**
     * @inheritDoc
     */
    public function strictBool(string $key): bool
    {
        $value = $this->nullableBool($key);
        if ($value === null) {
            throw new OutOfBoundsException(
                \sprintf(
                    'The key "%s" does not exist in the array.',
                    $key
                )
            );
        }

        return $value;
    }

    /**
     * @inheritDoc
     */
    public function nullableBool(string $key): bool|null
    {
        if ($this->has($key)) {
            $value = $this->get($key);
            if (\is_bool($value)) {
                return $value;
            }

            throw new TypeError(
                \sprintf(
                    'The value associated with key "%s" is not a boolean, but of type %s.',
                    $key,
                    \get_debug_type($value)
                )
            );
        }

        return null;
    }

    /**
     * @inheritDoc
     */
    public function asInt(string $key, int $default = 0): int
    {
        if ($this->has($key)) {
            $value = $this->get($key);
            if (\is_int($value)) {
                return $value;
            }
            if (\is_scalar($value)) {
                return \intval($value);
            }

            throw new TypeError(
                \sprintf(
                    'The value associated with key "%s" is not an integer, but of type %s.',
                    $key,
                    \get_debug_type($value)
                )
            );
        }

        return $default;
    }

    /**
     * @inheritDoc
     */
    public function strictInt(string $key): int
    {
        $value = $this->nullableInt($key);
        if ($value === null) {
            throw new OutOfBoundsException(
                \sprintf(
                    'The key "%s" does not exist in the array.',
                    $key
                )
            );
        }

        return $value;
    }

    /**
     * @inheritDoc
     */
    public function nullableInt(string $key): int|null
    {
        if ($this->has($key)) {
            $value = $this->get($key);
            if (\is_int($value)) {
                return $value;
            }

            throw new TypeError(
                \sprintf(
                    'The value associated with key "%s" is not an integer, but of type %s.',
                    $key,
                    \get_debug_type($value)
                )
            );
        }

        return null;
    }

    /**
     * @inheritDoc
     */
    public function asFloat(string $key, float $default = 0.0): float
    {
        if ($this->has($key)) {
            $value = $this->get($key);
            if (\is_float($value)) {
                return $value;
            }
            if (\is_scalar($value)) {
                $converted = \filter_var($value, \FILTER_VALIDATE_FLOAT);
                if (\is_float($converted)) {
                    return $converted;
                }
            }

            throw new TypeError(
                \sprintf(
                    'The value associated with key "%s" is not a float, but of type %s.',
                    $key,
                    \get_debug_type($value)
                )
            );
        }

        return $default;
    }

    /**
     * @inheritDoc
     */
    public function strictFloat(string $key): float
    {
        $value = $this->nullableFloat($key);
        if ($value === null) {
            throw new OutOfBoundsException(
                \sprintf(
                    'The key "%s" does not exist in the array.',
                    $key
                )
            );
        }

        return $value;
    }

    /**
     * @inheritDoc
     */
    public function nullableFloat(string $key): float|null
    {
        if ($this->has($key)) {
            $value = $this->get($key);
            if (\is_float($value)) {
                return $value;
            }

            throw new TypeError(
                \sprintf(
                    'The value associated with key "%s" is not a float, but of type %s.',
                    $key,
                    \get_debug_type($value)
                )
            );
        }

        return null;
    }

    /**
     * @inheritDoc
     */
    public function asString(string $key, string $default = ''): string
    {
        if ($this->has($key)) {
            $value = $this->get($key);
            if (\is_string($value)) {
                return $value;
            }
            if (\is_scalar($value) || $value instanceof \Stringable) {
                return \strval($value);
            }

            throw new TypeError(
                \sprintf(
                    'The value associated with key "%s" is not a string, but of type %s.',
                    $key,
                    \get_debug_type($value)
                )
            );
        }

        return $default;
    }

    /**
     * @inheritDoc
     */
    public function strictString(string $key): string
    {
        $value = $this->nullableString($key);
        if ($value === null) {
            throw new OutOfBoundsException(
                \sprintf(
                    'The key "%s" does not exist in the array.',
                    $key
                )
            );
        }

        return $value;
    }

    /**
     * @inheritDoc
     */
    public function nullableString(string $key): string|null
    {
        if ($this->has($key)) {
            $value = $this->get($key);
            if (\is_string($value)) {
                return $value;
            }

            throw new TypeError(
                \sprintf(
                    'The value associated with key "%s" is not a string, but of type %s.',
                    $key,
                    \get_debug_type($value)
                )
            );
        }

        return null;
    }

    /**
     * @inheritDoc
     */
    public function strictObject(string $key, string $className): object
    {
        $value = $this->nullableObject($key, $className);
        if ($value === null) {
            throw new OutOfBoundsException(
                \sprintf(
                    'The key "%s" does not exist in the array.',
                    $key
                )
            );
        }

        return $value;
    }

    /**
     * @inheritDoc
     */
    public function nullableObject(string $key, string $className): object|null
    {
        if ($this->has($key)) {
            $value = $this->get($key);
            if ($value === null || \is_object($value) && $value instanceof $className) {
                return $value;
            }

            throw new TypeError(
                \sprintf(
                    'The value associated with key "%s" is not an instance of %s, but of type %s.',
                    $key,
                    $className,
                    \get_debug_type($value)
                )
            );
        }

        return null;
    }

    /**
     * @inheritDoc
     */
    public function callable(string $key): callable
    {
        $value = $this->nullableCallable($key);
        if ($value === null) {
            throw new OutOfBoundsException(
                \sprintf(
                    'The key "%s" does not exist in the array.',
                    $key
                )
            );
        }

        return $value;
    }

    /**
     * @inheritDoc
     */
    public function nullableCallable(string $key): callable|null
    {
        if ($this->has($key)) {
            $value = $this->get($key);
            if (\is_callable($value)) {
                return $value;
            }

            throw new TypeError(
                \sprintf(
                    'The value associated with key "%s" is not a callable, but of type %s.',
                    $key,
                    \get_debug_type($value)
                )
            );
        }

        return null;
    }

    /**
     * @inheritDoc
     */
    public function intList(string $key): array
    {
        if ($this->has($key)) {
            $value = $this->get($key);
            if (\is_array($value)) {
                // @phpstan-ignore return.type
                return $value;
            }

            throw new TypeError(
                \sprintf(
                    'The value associated with key "%s" is not an array, but of type %s.',
                    $key,
                    \get_debug_type($value)
                )
            );
        }

        return [];
    }

    /**
     * @inheritDoc
     */
    public function stringList(string $key): array
    {
        if ($this->has($key)) {
            $value = $this->get($key);
            if (\is_array($value)) {
                // @phpstan-ignore return.type
                return $value;
            }

            throw new TypeError(
                \sprintf(
                    'The value associated with key "%s" is not an array, but of type %s.',
                    $key,
                    \get_debug_type($value)
                )
            );
        }

        return [];
    }

    /**
     * @inheritDoc
     */
    public function objectList(string $key, string $className): array
    {
        if ($this->has($key)) {
            $value = $this->get($key);
            if (\is_array($value)) {
                // @phpstan-ignore return.type
                return $value;
            }

            throw new TypeError(
                \sprintf(
                    'The value associated with key "%s" is not an array, but of type %s.',
                    $key,
                    \get_debug_type($value)
                )
            );
        }

        return [];
    }

    #endregion implementation ArrayAccessorInterface
}
