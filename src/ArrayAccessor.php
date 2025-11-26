<?php

declare(strict_types=1);

namespace Manychois\PhpStrong;

use OutOfBoundsException;
use TypeError;

/**
 * Provides a way to access array values with type safety.
 */
class ArrayAccessor
{
    /**
     * @var array<string,mixed>
     */
    private array $inner;

    /**
     * Creates a new ArrayAccessor instance.
     *
     * @param array<string,mixed> $inner The array to be accessed.
     */
    public function __construct(array $inner = [])
    {
        $this->inner = $inner;
    }

    /**
     * Returns the array value associated with the given key as an ArrayAccessor.
     * If the key does not exist, an OutOfBoundsException is thrown.
     * If the value is not an array, a TypeError is thrown.
     *
     * @param string $key The key to look up.
     *
     * @return ArrayAccessor The array value wrapped in an ArrayAccessor.
     *
     * @throws OutOfBoundsException If the key does not exist.
     * @throws TypeError If the value associated with the given key is not an array.
     */
    public function accessor(string $key): self
    {
        if (\array_key_exists($key, $this->inner)) {
            $value = $this->inner[$key];
            if (\is_array($value)) {
                // @phpstan-ignore argument.type
                return new self($value);
            }
            if ($value instanceof self) {
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

    #region Boolean

    /**
     * Returns the boolean value associated with the given key.
     * If the key does not exist, the default value is returned.
     * If the value is not a boolean, `filter_var()` is used to convert the value to a boolean.
     *
     * @param string $key     The key to look up.
     * @param bool   $default The default value to return if the key does not exist.
     *
     * @return bool The boolean value associated with the given key.
     *
     * @throws TypeError If the value associated with the given key cannot be converted to a boolean.
     */
    public function asBool(string $key, bool $default = false): bool
    {
        if (\array_key_exists($key, $this->inner)) {
            $value = $this->inner[$key];
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
     * Returns the boolean value associated with the given key.
     * If the key does not exist, an OutOfBoundsException is thrown.
     * If the value is not a boolean, a TypeError is thrown.
     *
     * @param string $key The key to look up.
     *
     * @return bool The boolean value associated with the given key.
     *
     * @throws OutOfBoundsException If the key does not exist.
     * @throws TypeError If the value associated with the given key is not a boolean.
     */
    public function bool(string $key): bool
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
     * Returns the boolean value associated with the given key.
     * If the key does not exist, null is returned.
     * If the value is not a boolean, a TypeError is thrown.
     *
     * @param string $key The key to look up.
     *
     * @return ?bool The boolean value associated with the given key.
     *
     * @throws TypeError If the value associated with the given key is not a boolean.
     */
    public function nullableBool(string $key): bool|null
    {
        if (\array_key_exists($key, $this->inner)) {
            $value = $this->inner[$key];
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

    #endregion Boolean

    #region Integer

    /**
     * Returns the integer value associated with the given key.
     * If the key does not exist, the default value is returned.
     * If the value is not an integer, `intval()` is used to convert the value to an integer.
     *
     * @param string $key     The key to look up.
     * @param int    $default The default value to return if the key does not exist.
     *
     * @return int The integer value associated with the given key.
     *
     * @throws TypeError If the value associated with the given key cannot be converted to an integer.
     */
    public function asInt(string $key, int $default = 0): int
    {
        if (\array_key_exists($key, $this->inner)) {
            $value = $this->inner[$key];
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
     * Returns the integer value associated with the given key.
     * If the key does not exist, an OutOfBoundsException is thrown.
     * If the value is not an integer, a TypeError is thrown.
     *
     * @param string $key The key to look up.
     *
     * @return int The integer value associated with the given key.
     *
     * @throws OutOfBoundsException If the key does not exist.
     * @throws TypeError If the value associated with the given key is not an integer.
     */
    public function int(string $key): int
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
     * Returns the integer value associated with the given key.
     * If the key does not exist, null is returned.
     * If the value is not an integer, a TypeError is thrown.
     *
     * @param string $key The key to look up.
     *
     * @return ?int The integer value associated with the given key.
     *
     * @throws TypeError If the value associated with the given key is not an integer.
     */
    public function nullableInt(string $key): int|null
    {
        if (\array_key_exists($key, $this->inner)) {
            $value = $this->inner[$key];
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

    #endregion Integer

    #region Float

    /**
     * Returns the float value associated with the given key.
     * If the key does not exist, the default value is returned.
     * If the value is not a float, `filter_var()` is used to convert the value to a float.
     *
     * @param string $key     The key to look up.
     * @param float  $default The default value to return if the key does not exist.
     *
     * @return float The float value associated with the given key.
     *
     * @throws TypeError If the value associated with the given key cannot be converted to a float.
     */
    public function asFloat(string $key, float $default = 0.0): float
    {
        if (\array_key_exists($key, $this->inner)) {
            $value = $this->inner[$key];
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
     * Returns the float value associated with the given key.
     * If the key does not exist, an OutOfBoundsException is thrown.
     * If the value is not a float, a TypeError is thrown.
     *
     * @param string $key The key to look up.
     *
     * @return float The float value associated with the given key.
     *
     * @throws OutOfBoundsException If the key does not exist.
     * @throws TypeError If the value associated with the given key is not a float.
     */
    public function float(string $key): float
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
     * Returns the float value associated with the given key.
     * If the key does not exist, null is returned.
     * If the value is not a float, a TypeError is thrown.
     *
     * @param string $key The key to look up.
     *
     * @return ?float The float value associated with the given key.
     *
     * @throws TypeError If the value associated with the given key is not a float.
     */
    public function nullableFloat(string $key): float|null
    {
        if (\array_key_exists($key, $this->inner)) {
            $value = $this->inner[$key];
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

    #endregion Float

    #region String

    /**
     * Returns the string value associated with the given key.
     * If the key does not exist, the default value is returned.
     * If the value is not a string, `strval()` is used to convert the value to a string.
     *
     * @param string $key     The key to look up.
     * @param string $default The default value to return if the key does not exist.
     *
     * @return string The string value associated with the given key.
     *
     * @throws TypeError If the value associated with the given key cannot be converted to a string.
     */
    public function asString(string $key, string $default = ''): string
    {
        if (\array_key_exists($key, $this->inner)) {
            $value = $this->inner[$key];
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
     * Returns the string value associated with the given key.
     * If the key does not exist, an OutOfBoundsException is thrown.
     * If the value is not a string, a TypeError is thrown.
     *
     * @param string $key The key to look up.
     *
     * @return string The string value associated with the given key.
     *
     * @throws OutOfBoundsException If the key does not exist.
     * @throws TypeError If the value associated with the given key is not a string.
     */
    public function string(string $key): string
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
     * Returns the string value associated with the given key.
     * If the key does not exist, null is returned.
     * If the value is not a string, a TypeError is thrown.
     *
     * @param string $key The key to look up.
     *
     * @return ?string The string value associated with the given key.
     *
     * @throws TypeError If the value associated with the given key is not a string.
     */
    public function nullableString(string $key): string|null
    {
        if (\array_key_exists($key, $this->inner)) {
            $value = $this->inner[$key];
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

    #endregion String

    #region Object

    /**
     * Returns the object value associated with the given key.
     * If the key does not exist, an OutOfBoundsException is thrown.
     * If the value is not an object, a TypeError is thrown.
     *
     * @param string          $key       The key to look up.
     * @param class-string<T> $className The class name of the object.
     *
     * @return T The object value associated with the given key.
     *
     * @throws OutOfBoundsException If the key does not exist.
     * @throws TypeError If the value associated with the given key is not an object.
     *
     * @template T of object
     */
    public function object(string $key, string $className): object
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
     * Returns the object value associated with the given key.
     * If the key does not exist, null is returned.
     * If the value is not null, nor an object of the specified class, a TypeError is thrown.
     *
     * @param string          $key       The key to look up.
     * @param class-string<T> $className The class name of the object.
     *
     * @return T|null The object value associated with the given key.
     *
     * @throws TypeError If the value associated with the given key is not null, nor an object of the specified class.
     *
     * @template T of object
     */
    public function nullableObject(string $key, string $className): object|null
    {
        if (\array_key_exists($key, $this->inner)) {
            $value = $this->inner[$key];
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

    #endregion Object

    #region Callable

    /**
     * Returns the callable value associated with the given key.
     * If the key does not exist, an OutOfBoundsException is thrown.
     * If the value is not a callable, a TypeError is thrown.
     *
     * @param string $key The key to look up.
     *
     * @return callable The callable value associated with the given key.
     *
     * @throws OutOfBoundsException If the key does not exist.
     * @throws TypeError If the value associated with the given key is not a callable.
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
     * Returns the callable value associated with the given key.
     * If the key does not exist, null is returned.
     * If the value is not a callable, a TypeError is thrown.
     *
     * @param string $key The key to look up.
     *
     * @return ?callable The callable value associated with the given key.
     *
     * @throws TypeError If the value associated with the given key is not a callable.
     */
    public function nullableCallable(string $key): callable|null
    {
        if (\array_key_exists($key, $this->inner)) {
            $value = $this->inner[$key];
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

    #endregion Callable

    #region Array (as list)

    /**
     * Returns the array value associated with the given key.
     * If the key does not exist, an empty array is returned.
     * If the value is not an array, a TypeError is thrown.
     * Note that type check of the array elements is not performed.
     *
     * @param string $key The key to look up.
     *
     * @return array<int,int> The array value associated with the given key.
     *
     * @throws TypeError If the value associated with the given key is not an array.
     *
     * @phpstan-return list<int>
     */
    public function intList(string $key): array
    {
        if (\array_key_exists($key, $this->inner)) {
            $value = $this->inner[$key];
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
     * Returns the array value associated with the given key.
     * If the key does not exist, an empty array is returned.
     * If the value is not an array, a TypeError is thrown.
     * Note that type check of the array elements is not performed.
     *
     * @param string $key The key to look up.
     *
     * @return array<int,string> The array value associated with the given key.
     *
     * @throws TypeError If the value associated with the given key is not an array.
     *
     * @phpstan-return list<string>
     */
    public function stringList(string $key): array
    {
        if (\array_key_exists($key, $this->inner)) {
            $value = $this->inner[$key];
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
     * Returns the array value associated with the given key.
     * If the key does not exist, an empty array is returned.
     * If the value is not an array, a TypeError is thrown.
     * Note that type check of the array elements is not performed.
     *
     * @param string          $key       The key to look up.
     * @param class-string<T> $className The class name of the objects.
     *
     * @return array<int,T> The array value associated with the given key.
     *
     * @throws TypeError If the value associated with the given key is not an array.
     *
     * @template T of object
     *
     * @phpstan-return list<T>
     */
    public function objectList(string $key, string $className): array
    {
        if (\array_key_exists($key, $this->inner)) {
            $value = $this->inner[$key];
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

    #endregion Array (as list)
}
