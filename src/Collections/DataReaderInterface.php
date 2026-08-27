<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\Collections;

use Countable;
use DateTimeImmutable;
use InvalidArgumentException;
use OutOfBoundsException;
use UnitEnum;

/**
 * Provides strongly typed access to the values of a string-keyed array or an object.
 *
 * Every value accessor comes in up to three variants. The bare variant (e.g. `string()`) is strict: the value must
 * already have the requested type. The `as` variant (e.g. `asString()`) converts the value when a sensible conversion
 * exists. The `null` variant (e.g. `nullString()`) is strict as well, but returns `null` instead of throwing when the
 * key is absent, the value is `null`, or the value has another type.
 *
 * A key may be written in dot notation to reach a value nested inside the source, e.g. `user.address.city`, where each
 * segment after the first indexes one level deeper, through arrays and objects alike. A segment matches an integer key
 * as well, so `items.0.name` reads the first element of a sequential array. An entry whose key matches the whole string
 * wins over dot notation, which keeps keys containing a dot readable. `count()`, `keys()` and `entries()` always
 * describe the top level only.
 */
interface DataReaderInterface extends Countable
{
    /**
     * Returns the array value stored under a key.
     *
     * @param string $key The key to read, in dot notation for a nested value.
     *
     * @return array The array value, in whatever shape it is stored.
     *
     * @throws OutOfBoundsException if the key is absent.
     * @throws InvalidArgumentException if the value is not an array.
     *
     * @phpstan-return array<array-key,mixed>
     */
    public function array(string $key): array;

    /**
     * Converts the value stored under a key to a boolean.
     *
     * Which representations are recognised is up to the implementation.
     *
     * @param string $key The key to read, in dot notation for a nested value. An absent key is read as `null`.
     *
     * @return bool The converted value.
     *
     * @throws InvalidArgumentException if the value is not a recognised boolean representation.
     */
    public function asBool(string $key): bool;

    /**
     * Converts the value stored under a key to a date and time.
     *
     * Which representations are recognised is up to the implementation.
     *
     * @param string $key The key to read, in dot notation for a nested value. An absent key is read as `null`.
     *
     * @return DateTimeImmutable The converted value.
     *
     * @throws InvalidArgumentException if the value is not convertible to a date and time.
     */
    public function asDateTime(string $key): DateTimeImmutable;

    /**
     * Converts the value stored under a key to a float.
     *
     * Which representations are recognised is up to the implementation.
     *
     * @param string $key The key to read, in dot notation for a nested value. An absent key is read as `null`.
     *
     * @return float The converted value.
     *
     * @throws InvalidArgumentException if the value is not convertible to a float.
     */
    public function asFloat(string $key): float;

    /**
     * Converts the value stored under a key to an integer.
     *
     * Which representations are recognised is up to the implementation.
     *
     * @param string $key The key to read, in dot notation for a nested value. An absent key is read as `null`.
     *
     * @return int The converted value.
     *
     * @throws InvalidArgumentException if the value is not convertible to an integer.
     */
    public function asInt(string $key): int;

    /**
     * Converts the value stored under a key to a string.
     *
     * Which representations are recognised is up to the implementation.
     *
     * @param string $key The key to read, in dot notation for a nested value. An absent key is read as `null`.
     *
     * @return string The converted value.
     *
     * @throws InvalidArgumentException if the value has no sensible string representation.
     */
    public function asString(string $key): string;

    /**
     * Converts the value stored under a key to a string and trims it, falling back to a default when the result is
     * empty.
     *
     * Which representations are recognised is up to the implementation.
     *
     * @param string $key The key to read, in dot notation for a nested value. An absent key is read as `null`.
     * @param string $default The value to return when the trimmed string is empty.
     *
     * @return string The trimmed value, or the default.
     *
     * @throws InvalidArgumentException if the value has no sensible string representation.
     */
    public function asTrimmedString(string $key, string $default = ''): string;

    /**
     * Returns the boolean value stored under a key.
     *
     * @param string $key The key to read, in dot notation for a nested value.
     *
     * @return bool The boolean value.
     *
     * @throws OutOfBoundsException if the key is absent.
     * @throws InvalidArgumentException if the value is not a boolean.
     */
    public function bool(string $key): bool;

    /**
     * Returns the immutable date and time value stored under a key.
     *
     * @param string $key The key to read, in dot notation for a nested value.
     *
     * @return DateTimeImmutable The date and time value.
     *
     * @throws OutOfBoundsException if the key is absent.
     * @throws InvalidArgumentException if the value is not an immutable date and time.
     */
    public function dateTime(string $key): DateTimeImmutable;

    /**
     * Returns the entries of the source, i.e. the elements of the array or the public properties of the object.
     *
     * @return array The entries of the source.
     *
     * @throws InvalidArgumentException if a property name of the source object is not a string.
     *
     * @phpstan-return array<string,mixed>
     */
    public function entries(): array;

    /**
     * Returns the enum case stored under a key.
     *
     * @param string $key The key to read, in dot notation for a nested value.
     * @param string $enumClass The enum the value must belong to.
     *
     * @return object The enum case.
     *
     * @throws OutOfBoundsException if the key is absent.
     * @throws InvalidArgumentException if the value is not a case of the enum.
     *
     * @template TEnum of UnitEnum
     *
     * @phpstan-param class-string<TEnum> $enumClass
     *
     * @phpstan-return TEnum
     */
    public function enum(string $key, string $enumClass): object;

    /**
     * Checks whether the value stored under a key is falsy.
     *
     * An absent key counts as falsy. Which values count as falsy is up to the implementation.
     *
     * @param string $key The key to read, in dot notation for a nested value.
     *
     * @return bool True if the value is falsy or the key is absent; false otherwise.
     */
    public function falsy(string $key): bool;

    /**
     * Returns the float value stored under a key.
     *
     * @param string $key The key to read, in dot notation for a nested value.
     *
     * @return float The float value.
     *
     * @throws OutOfBoundsException if the key is absent.
     * @throws InvalidArgumentException if the value is neither a float nor an integer.
     */
    public function float(string $key): float;

    /**
     * Returns the value stored under a key, whatever its type.
     *
     * @param string $key The key to read, in dot notation for a nested value.
     *
     * @return mixed The value.
     *
     * @throws OutOfBoundsException if the key is absent.
     */
    public function get(string $key): mixed;

    /**
     * Checks whether a key exists, even when its value is `null`.
     *
     * @param string $key The key to look for, in dot notation for a nested value.
     *
     * @return bool True if the key exists; false otherwise.
     */
    public function has(string $key): bool;

    /**
     * Returns the integer value stored under a key.
     *
     * @param string $key The key to read, in dot notation for a nested value.
     *
     * @return int The integer value.
     *
     * @throws OutOfBoundsException if the key is absent.
     * @throws InvalidArgumentException if the value is not an integer.
     */
    public function int(string $key): int;

    /**
     * Returns the value stored under a key, or `null` when the key is absent.
     *
     * @param string $key The key to read, in dot notation for a nested value.
     *
     * @return mixed The value, or `null` if the key is absent.
     */
    public function nullGet(string $key): mixed;

    /**
     * Returns all keys, in the order they appear in the source, i.e. the array keys or the public property names.
     *
     * @return array The keys.
     *
     * @throws InvalidArgumentException if a property name of the source object is not a string.
     *
     * @phpstan-return list<string>
     */
    public function keys(): array;

    /**
     * Returns the array value stored under a key, or `null` when it is unavailable.
     *
     * @param string $key The key to read, in dot notation for a nested value.
     *
     * @return ?array The array value, or `null` if the key is absent or the value is not an array.
     *
     * @phpstan-return ?array<array-key,mixed>
     */
    public function nullArray(string $key): ?array;

    /**
     * Returns the boolean value stored under a key, or `null` when it is unavailable.
     *
     * @param string $key The key to read, in dot notation for a nested value.
     *
     * @return ?bool The boolean value, or `null` if the key is absent or the value is not a boolean.
     */
    public function nullBool(string $key): ?bool;

    /**
     * Returns the immutable date and time value stored under a key, or `null` when it is unavailable.
     *
     * @param string $key The key to read, in dot notation for a nested value.
     *
     * @return ?DateTimeImmutable The date and time value, or `null` if the key is absent or the value is not an
     * immutable date and time.
     */
    public function nullDateTime(string $key): ?DateTimeImmutable;

    /**
     * Returns the enum case stored under a key, or `null` when it is unavailable.
     *
     * @param string $key The key to read, in dot notation for a nested value.
     * @param string $enumClass The enum the value must belong to.
     *
     * @return ?object The enum case, or `null` if the key is absent or the value is not a case of the enum.
     *
     * @template TEnum of UnitEnum
     *
     * @phpstan-param class-string<TEnum> $enumClass
     *
     * @phpstan-return ?TEnum
     */
    public function nullEnum(string $key, string $enumClass): ?object;

    /**
     * Returns the float value stored under a key, or `null` when it is unavailable.
     *
     * @param string $key The key to read, in dot notation for a nested value.
     *
     * @return ?float The float value, or `null` if the key is absent or the value is not a number.
     */
    public function nullFloat(string $key): ?float;

    /**
     * Returns the integer value stored under a key, or `null` when it is unavailable.
     *
     * @param string $key The key to read, in dot notation for a nested value.
     *
     * @return ?int The integer value, or `null` if the key is absent or the value is not an integer.
     */
    public function nullInt(string $key): ?int;

    /**
     * Returns the object stored under a key, or `null` when it is unavailable.
     *
     * @param string $key The key to read, in dot notation for a nested value.
     * @param string $className The class or interface the value must be an instance of.
     *
     * @return ?object The object, or `null` if the key is absent or the value is not an instance of the given class.
     *
     * @template TObject of object
     *
     * @phpstan-param class-string<TObject> $className
     *
     * @phpstan-return ?TObject
     */
    public function nullObject(string $key, string $className): ?object;

    /**
     * Wraps the nested array or object stored under a key in a reader, or returns `null` when it is unavailable.
     *
     * @param string $key The key to read, in dot notation for a nested value.
     *
     * @return ?DataReaderInterface The reader over the nested value, or `null` if the key is absent, the value is
     * neither an array nor an object, or the array has a non-string key.
     */
    public function nullReader(string $key): ?DataReaderInterface;

    /**
     * Returns the string value stored under a key, or `null` when it is unavailable.
     *
     * @param string $key The key to read, in dot notation for a nested value.
     *
     * @return ?string The string value, or `null` if the key is absent or the value is not a string.
     */
    public function nullString(string $key): ?string;

    /**
     * Returns the object stored under a key.
     *
     * @param string $key The key to read, in dot notation for a nested value.
     * @param string $className The class or interface the value must be an instance of.
     *
     * @return object The object.
     *
     * @throws OutOfBoundsException if the key is absent.
     * @throws InvalidArgumentException if the value is not an instance of the given class.
     *
     * @template TObject of object
     *
     * @phpstan-param class-string<TObject> $className
     *
     * @phpstan-return TObject
     */
    public function object(string $key, string $className): object;

    /**
     * Wraps the nested array or object stored under a key in a reader.
     *
     * @param string $key The key to read, in dot notation for a nested value.
     *
     * @return DataReaderInterface The reader over the nested value.
     *
     * @throws OutOfBoundsException if the key is absent.
     * @throws InvalidArgumentException if the value is neither an array nor an object, or the array has a non-string
     * key.
     */
    public function reader(string $key): DataReaderInterface;

    /**
     * Returns the string value stored under a key.
     *
     * @param string $key The key to read, in dot notation for a nested value.
     *
     * @return string The string value.
     *
     * @throws OutOfBoundsException if the key is absent.
     * @throws InvalidArgumentException if the value is not a string.
     */
    public function string(string $key): string;
}
