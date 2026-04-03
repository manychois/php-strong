<?php

declare(strict_types=1);

namespace Manychois\PhpStrong;

use Manychois\PhpStrong\Collections\MapInterface as IMap;
use NoDiscard;
use OutOfBoundsException;
use UnexpectedValueException;

/**
 * Reads nested values from an array using dot-separated paths.
 */
interface ArrayReaderInterface
{
    /**
     * When this is the first dot-separated segment of a path, that segment is skipped and
     * traversal begins at the root (e.g. `$this` resolves like an empty path; `$this.foo` like `foo`).
     * In any other position it names a literal key.
     */
    public const string ROOT_KEY = '$this';

    /**
     * Gets the array value at the given path.
     *
     * @param string $path The path, e.g. `user.address.city`.
     *
     * @return array<mixed> The resolved value.
     *
     * @throws OutOfBoundsException If the path cannot be resolved.
     * @throws UnexpectedValueException If the value is not an array.
     */
    #[NoDiscard]
    public function array(string $path): array;

    /**
     * Gets the array value at the given path.
     * If the path cannot be resolved, or the value is not an array, returns `null`.
     *
     * @param string $path The path, e.g. `user.address.city`.
     *
     * @return array<mixed>|null The resolved value, or `null` when the path cannot be resolved or the value is not an
     * array.
     */
    #[NoDiscard]
    public function arrayOrNull(string $path): ?array;

    /**
     * Gets the boolean value at the given path.
     * If the value is not a boolean, various boolean conversion techniques are attempted.
     * If all attempts fail, the default value is returned.
     * The conversion techniques are implementation-dependent.
     *
     * @param string $path The path, e.g. `user.address.city`.
     * @param bool $default The default value to return if all attempts fail.
     *
     * @return bool The resolved value.
     */
    #[NoDiscard]
    public function asBool(string $path, bool $default = false): bool;

    /**
     * Gets the float value at the given path.
     * If the value is not a float, various float conversion techniques are attempted.
     * If all attempts fail, the default value is returned.
     * The conversion techniques are implementation-dependent.
     *
     * @param string $path The path, e.g. `user.address.city`.
     * @param float $default The default value to return if all attempts fail.
     *
     * @return float The resolved value.
     */
    #[NoDiscard]
    public function asFloat(string $path, float $default = 0.0): float;

    /**
     * Gets the integer value at the given path.
     * If the value is not an integer, various integer conversion techniques are attempted.
     * If all attempts fail, the default value is returned.
     * The conversion techniques are implementation-dependent.
     *
     * @param string $path The path, e.g. `user.address.city`.
     * @param int $default The default value to return if all attempts fail.
     *
     * @return int The resolved value.
     */
    #[NoDiscard]
    public function asInt(string $path, int $default = 0): int;

    /**
     * Gets the string value at the given path.
     * If the value is not a string, various string conversion techniques are attempted.
     * If all attempts fail, the default value is returned.
     * The conversion techniques are implementation-dependent.
     *
     * @param string $path The path, e.g. `user.address.city`.
     * @param string $default The default value to return if all attempts fail.
     *
     * @return string The resolved value.
     */
    #[NoDiscard]
    public function asString(string $path, string $default = ''): string;

    /**
     * Gets a new array reader at the given path.
     *
     * @param string $path The path, e.g. `user.address.city`.
     *
     * @return ArrayReaderInterface The new array reader.
     *
     * @throws OutOfBoundsException If the path cannot be resolved.
     * @throws UnexpectedValueException If the value is not an array or an object.
     */
    #[NoDiscard]
    public function at(string $path): ArrayReaderInterface;

    /**
     * Gets the boolean value at the given path.
     *
     * @param string $path The path, e.g. `user.address.city`.
     *
     * @return bool The resolved value.
     *
     * @throws OutOfBoundsException If the path cannot be resolved.
     * @throws UnexpectedValueException If the value is not a boolean.
     */
    #[NoDiscard]
    public function bool(string $path): bool;

    /**
     * Gets the boolean value at the given path.
     * If the path cannot be resolved, or the value is not a boolean, returns `null`.
     *
     * @param string $path The path, e.g. `user.address.city`.
     *
     * @return bool|null The resolved value, or `null` when the path cannot be resolved or the value is not a boolean.
     */
    #[NoDiscard]
    public function boolOrNull(string $path): ?bool;

    /**
     * Gets the callable value at the given path.
     *
     * @param string $path The path, e.g. `user.address.city`.
     *
     * @return callable The resolved value.
     *
     * @throws OutOfBoundsException If the path cannot be resolved.
     * @throws UnexpectedValueException If the value is not callable.
     */
    #[NoDiscard]
    public function callable(string $path): callable;

    /**
     * Gets the callable value at the given path.
     * If the path cannot be resolved, or the value is not callable, returns `null`.
     *
     * @param string $path The path, e.g. `user.address.city`.
     *
     * @return callable|null The resolved value, or `null` when the path cannot be resolved or the value is not
     * callable.
     */
    #[NoDiscard]
    public function callableOrNull(string $path): ?callable;

    /**
     * Gets the float value at the given path.
     *
     * @param string $path The path, e.g. `user.address.city`.
     *
     * @return float The resolved value.
     *
     * @throws OutOfBoundsException If the path cannot be resolved.
     * @throws UnexpectedValueException If the value is not a float.
     */
    #[NoDiscard]
    public function float(string $path): float;

    /**
     * Gets the float value at the given path.
     * If the path cannot be resolved, or the value is not a float, returns `null`.
     *
     * @param string $path The path, e.g. `user.address.city`.
     *
     * @return float|null The resolved value, or `null` when the path cannot be resolved or the value is not a float.
     */
    #[NoDiscard]
    public function floatOrNull(string $path): ?float;

    /**
     * Gets the value at the given dot-separated path.
     *
     * An empty path returns the root value unchanged. Keys in the path cannot contain a literal dot;
     * split segments use `.` only as a separator.
     *
     * @param string $path The path, e.g. `user.address.city`.
     *
     * @return mixed The resolved value.
     *
     * @throws OutOfBoundsException If the path cannot be resolved.
     */
    #[NoDiscard]
    public function get(string $path): mixed;

    /**
     * Gets the value at the given dot-separated path.
     * If the path cannot be resolved, returns `null`.
     *
     * @param string $path The path, e.g. `user.address.city`.
     *
     * @return mixed The resolved value, or `null` when the path cannot be resolved.
     */
    #[NoDiscard]
    public function getOrNull(string $path): mixed;

    /**
     * Checks if the value at the given path can be resolved, and the value is not null.
     *
     * @param string $path The path, e.g. `user.address.city`.
     *
     * @return bool True if the value can be resolved and is not null, false otherwise.
     */
    #[NoDiscard]
    public function has(string $path): bool;

    /**
     * Gets the object value at the given path and checks it is an instance of the given class.
     *
     * @template T of object
     *
     * @param string $path The path, e.g. `user.address.city`.
     * @param string $class The class to check against.
     *
     * @return T The resolved value.
     *
     * @throws OutOfBoundsException If the path cannot be resolved.
     * @throws UnexpectedValueException If the value is not an object or not an instance of `$class`.
     *
     * @phpstan-param class-string<T> $class
     */
    #[NoDiscard]
    public function instanceOf(string $path, string $class): object;

    /**
     * Gets the object value at the given path and checks it is an instance of the given class.
     * If the path cannot be resolved, or the value is not an instance of `$class`, returns `null`.
     *
     * @template T of object
     *
     * @param string $path The path, e.g. `user.address.city`.
     * @param string $class The class to check against.
     *
     * @return T|null The resolved value, or `null` when the path cannot be resolved or the value is not an instance of
     * `$class`.
     *
     * @phpstan-param class-string<T> $class
     */
    #[NoDiscard]
    public function instanceOfOrNull(string $path, string $class): ?object;

    /**
     * Gets the integer value at the given path.
     *
     * @param string $path The path, e.g. `user.address.city`.
     *
     * @return int The resolved value.
     *
     * @throws OutOfBoundsException If the path cannot be resolved.
     * @throws UnexpectedValueException If the value is not an integer.
     */
    #[NoDiscard]
    public function int(string $path): int;

    /**
     * Gets the integer value at the given path.
     * If the path cannot be resolved, or the value is not an integer, returns `null`.
     *
     * @param string $path The path, e.g. `user.address.city`.
     *
     * @return int|null The resolved value, or `null` when the path cannot be resolved or the value is not an integer.
     */
    #[NoDiscard]
    public function intOrNull(string $path): ?int;

    /**
     * Gets the object value at the given path.
     * If you know the class of the value, use {@see instanceOf} instead.
     *
     * @param string $path The path, e.g. `user.address.city`.
     *
     * @return object The resolved value.
     *
     * @throws OutOfBoundsException If the path cannot be resolved.
     * @throws UnexpectedValueException If the value is not an object.
     */
    #[NoDiscard]
    public function object(string $path): object;

    /**
     * Gets the object value at the given path.
     * If the path cannot be resolved, or the value is not an object, returns `null`.
     *
     * @param string $path The path, e.g. `user.address.city`.
     *
     * @return object|null The resolved value, or `null` when the path cannot be resolved or the value is not an
     * object.
     */
    #[NoDiscard]
    public function objectOrNull(string $path): ?object;

    /**
     * Gets the string value at the given path.
     *
     * @param string $path The path, e.g. `user.address.city`.
     *
     * @return string The resolved value.
     *
     * @throws OutOfBoundsException If the path cannot be resolved.
     * @throws UnexpectedValueException If the value is not a string.
     */
    #[NoDiscard]
    public function string(string $path): string;

    /**
     * Gets the string value at the given path.
     * If the path cannot be resolved, or the value is not a string, returns `null`.
     *
     * @param string $path The path, e.g. `user.address.city`.
     *
     * @return string|null The resolved value, or `null` when the path cannot be resolved or the value is not a string.
     */
    #[NoDiscard]
    public function stringOrNull(string $path): ?string;

    /**
     * Returns a new array reader with the given overrides.
     * Whether the overrides are applied to the root source or a new value is created depends on the implementation.
     *
     * @param array<string,mixed>|IMap<string,mixed> $overrides The overrides to apply.
     *
     * @return ArrayReaderInterface The new array reader.
     */
    #[NoDiscard]
    public function with(array|IMap $overrides): ArrayReaderInterface;
}
