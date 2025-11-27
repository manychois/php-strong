<?php

declare(strict_types=1);

namespace Manychois\PhpStrong;

/**
 * Provides a way to access array values with type safety.
 */
interface ArrayAccessorInterface
{
    /**
     * Returns the array value associated with the given key as an ArrayAccessor.
     * If the key does not exist, an OutOfBoundsException is thrown.
     * If the value is not an array, a TypeError is thrown.
     *
     * @param string $key The key to look up.
     *
     * @return self The array value wrapped in an ArrayAccessor.
     *
     * @throws \OutOfBoundsException If the key does not exist.
     * @throws \TypeError If the value associated with the given key is not an array.
     */
    public function accessor(string $key): self;

    /**
     * Gets the value associated with the given key.
     * If the key is not found, null is returned.
     *
     * @param string $key The key to look up.
     *
     * @return mixed The value associated with the given key, or null if the key is not found.
     */
    public function get(string $key): mixed;

    /**
     * Sets the value associated with the given key.
     *
     * @param string $key   The key to set.
     * @param mixed  $value The value to set.
     */
    public function set(string $key, mixed $value): void;

    /**
     * Checks if a key exists in the array.
     *
     * @param string $key The key to check.
     *
     * @return bool True if the key exists, false otherwise.
     */
    public function has(string $key): bool;

    /**
     * Deletes a key from the array.
     *
     * @param string $key The key to delete.
     */
    public function delete(string $key): void;

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
     * @throws \TypeError If the value associated with the given key cannot be converted to a boolean.
     */
    public function asBool(string $key, bool $default = false): bool;

    /**
     * Returns the boolean value associated with the given key.
     * If the key does not exist, an OutOfBoundsException is thrown.
     * If the value is not a boolean, a TypeError is thrown.
     *
     * @param string $key The key to look up.
     *
     * @return bool The boolean value associated with the given key.
     *
     * @throws \OutOfBoundsException If the key does not exist.
     * @throws \TypeError If the value associated with the given key is not a boolean.
     */
    public function strictBool(string $key): bool;

    /**
     * Returns the boolean value associated with the given key.
     * If the key does not exist, null is returned.
     * If the value is not a boolean, a TypeError is thrown.
     *
     * @param string $key The key to look up.
     *
     * @return ?bool The boolean value associated with the given key.
     *
     * @throws \TypeError If the value associated with the given key is not a boolean.
     */
    public function nullableBool(string $key): bool|null;

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
     * @throws \TypeError If the value associated with the given key cannot be converted to an integer.
     */
    public function asInt(string $key, int $default = 0): int;

    /**
     * Returns the integer value associated with the given key.
     * If the key does not exist, an OutOfBoundsException is thrown.
     * If the value is not an integer, a TypeError is thrown.
     *
     * @param string $key The key to look up.
     *
     * @return int The integer value associated with the given key.
     *
     * @throws \OutOfBoundsException If the key does not exist.
     * @throws \TypeError If the value associated with the given key is not an integer.
     */
    public function strictInt(string $key): int;

    /**
     * Returns the integer value associated with the given key.
     * If the key does not exist, null is returned.
     * If the value is not an integer, a TypeError is thrown.
     *
     * @param string $key The key to look up.
     *
     * @return ?int The integer value associated with the given key.
     *
     * @throws \TypeError If the value associated with the given key is not an integer.
     */
    public function nullableInt(string $key): int|null;

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
     * @throws \TypeError If the value associated with the given key cannot be converted to a float.
     */
    public function asFloat(string $key, float $default = 0.0): float;

    /**
     * Returns the float value associated with the given key.
     * If the key does not exist, an OutOfBoundsException is thrown.
     * If the value is not a float, a TypeError is thrown.
     *
     * @param string $key The key to look up.
     *
     * @return float The float value associated with the given key.
     *
     * @throws \OutOfBoundsException If the key does not exist.
     * @throws \TypeError If the value associated with the given key is not a float.
     */
    public function strictFloat(string $key): float;

    /**
     * Returns the float value associated with the given key.
     * If the key does not exist, null is returned.
     * If the value is not a float, a TypeError is thrown.
     *
     * @param string $key The key to look up.
     *
     * @return ?float The float value associated with the given key.
     *
     * @throws \TypeError If the value associated with the given key is not a float.
     */
    public function nullableFloat(string $key): float|null;

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
     * @throws \TypeError If the value associated with the given key cannot be converted to a string.
     */
    public function asString(string $key, string $default = ''): string;

    /**
     * Returns the string value associated with the given key.
     * If the key does not exist, an OutOfBoundsException is thrown.
     * If the value is not a string, a TypeError is thrown.
     *
     * @param string $key The key to look up.
     *
     * @return string The string value associated with the given key.
     *
     * @throws \OutOfBoundsException If the key does not exist.
     * @throws \TypeError If the value associated with the given key is not a string.
     */
    public function strictString(string $key): string;

    /**
     * Returns the string value associated with the given key.
     * If the key does not exist, null is returned.
     * If the value is not a string, a TypeError is thrown.
     *
     * @param string $key The key to look up.
     *
     * @return ?string The string value associated with the given key.
     *
     * @throws \TypeError If the value associated with the given key is not a string.
     */
    public function nullableString(string $key): string|null;

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
     * @throws \OutOfBoundsException If the key does not exist.
     * @throws \TypeError If the value associated with the given key is not an object.
     *
     * @template T of object
     */
    public function object(string $key, string $className): object;

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
     * @throws \TypeError If the value associated with the given key is not null, nor an object of the specified class.
     *
     * @template T of object
     */
    public function nullableObject(string $key, string $className): object|null;

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
     * @throws \OutOfBoundsException If the key does not exist.
     * @throws \TypeError If the value associated with the given key is not a callable.
     */
    public function callable(string $key): callable;

    /**
     * Returns the callable value associated with the given key.
     * If the key does not exist, null is returned.
     * If the value is not a callable, a TypeError is thrown.
     *
     * @param string $key The key to look up.
     *
     * @return ?callable The callable value associated with the given key.
     *
     * @throws \TypeError If the value associated with the given key is not a callable.
     */
    public function nullableCallable(string $key): callable|null;

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
     * @throws \TypeError If the value associated with the given key is not an array.
     *
     * @phpstan-return list<int>
     */
    public function intList(string $key): array;

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
     * @throws \TypeError If the value associated with the given key is not an array.
     *
     * @phpstan-return list<string>
     */
    public function stringList(string $key): array;

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
     * @throws \TypeError If the value associated with the given key is not an array.
     *
     * @template T of object
     *
     * @phpstan-return list<T>
     */
    public function objectList(string $key, string $className): array;

    #endregion Array (as list)
}
