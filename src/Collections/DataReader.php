<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\Collections;

use ArrayAccess;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Exception;
use InvalidArgumentException;
use Manychois\PhpStrong\Collections\DataReaderInterface as IDataReader;
use OutOfBoundsException;
use Override;
use Stringable;

/**
 * Provides strongly typed access to the values of a string-keyed array or an object.
 *
 * A key may be written in dot notation to reach a value nested inside the source, e.g. `user.address.city`.
 *
 * An object is read through its offsets when it implements `ArrayAccess`, then through its public properties, and
 * finally through `__isset()` and `__get()` when it defines both. A non-public or uninitialized property counts as
 * absent, which keeps a value object such as a date a leaf rather than a node to descend into.
 */
final class DataReader implements IDataReader
{
    /**
     * @var array<string,mixed>|object
     */
    private readonly array|object $source;

    /**
     * Initializes a new instance of the DataReader class.
     *
     * @param array|object $source The array or object to read from. All keys of an array must be strings.
     *
     * @throws InvalidArgumentException if any key of an array is not a string.
     *
     * @phpstan-param array<array-key,mixed>|object $source
     */
    public function __construct(array|object $source)
    {
        $this->source = is_array($source) ? self::stringKeyed($source) : $source;
    }

    #region implements IDataReader

    /**
     * @inheritDoc
     */
    #[Override]
    public function array(string $key): array
    {
        $value = $this->get($key);
        if (!is_array($value)) {
            throw $this->typeError($key, 'an array', $value);
        }

        return $value;
    }

    /**
     * @inheritDoc
     *
     * The value is recognised as the `FILTER_VALIDATE_BOOL` filter does: `1`, `true`, `on` and `yes` are true, and
     * `0`, `false`, `off`, `no` and the empty string are false, in any letter case and ignoring surrounding
     * whitespace.
     */
    #[Override]
    public function asBool(string $key): bool
    {
        $value = $this->get($key);
        $converted = $this->convertToBool($value);
        if ($converted === null) {
            throw $this->typeError($key, 'convertible to a boolean', $value);
        }

        return $converted;
    }

    /**
     * @inheritDoc
     *
     * A string is parsed with the standard date and time formats, and one which carries no time zone is read as UTC.
     * An integer is read as a Unix timestamp, which is UTC by definition. A value which is already a date and time
     * keeps its own time zone, and an immutable one is returned unchanged.
     */
    #[Override]
    public function asDateTime(string $key): DateTimeImmutable
    {
        $value = $this->get($key);
        if ($value instanceof DateTimeImmutable) {
            return $value;
        }
        if ($value instanceof DateTimeInterface) {
            return DateTimeImmutable::createFromInterface($value);
        }

        $utc = new DateTimeZone('UTC');
        if (is_int($value)) {
            return new DateTimeImmutable('@' . $value, $utc);
        }
        if (is_string($value)) {
            try {
                return new DateTimeImmutable($value, $utc);
            } catch (Exception) {
                throw $this->typeError($key, 'a parsable date and time', $value);
            }
        }

        throw $this->typeError($key, 'convertible to a date and time', $value);
    }

    /**
     * @inheritDoc
     *
     * Integers, booleans and numeric strings are converted; booleans become `1.0` and `0.0`.
     */
    #[Override]
    public function asFloat(string $key): float
    {
        $value = $this->get($key);
        $converted = $this->convertToFloat($value);
        if ($converted === null) {
            throw $this->typeError($key, 'convertible to a float', $value);
        }

        return $converted;
    }

    /**
     * @inheritDoc
     *
     * Booleans, floats and numeric strings are converted; booleans become `1` and `0`, and a fractional part is
     * discarded, so `3.7` becomes `3` and `-3.7` becomes `-3`. A float outside the integer range is rejected.
     */
    #[Override]
    public function asInt(string $key): int
    {
        $value = $this->get($key);
        $converted = $this->convertToInt($value);
        if ($converted === null) {
            throw $this->typeError($key, 'convertible to an integer', $value);
        }

        return $converted;
    }

    /**
     * @inheritDoc
     *
     * The output resembles JSON: a boolean becomes `'true'` or `'false'`, and `null` becomes the empty string.
     * Integers, floats and stringable objects are converted as PHP renders them.
     */
    #[Override]
    public function asString(string $key): string
    {
        $value = $this->get($key);
        if (is_string($value)) {
            return $value;
        }
        if ($value === null) {
            return '';
        }
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if (is_int($value) || is_float($value) || $value instanceof Stringable) {
            return (string) $value;
        }

        throw $this->typeError($key, 'convertible to a string', $value);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function bool(string $key): bool
    {
        $value = $this->get($key);
        if (!is_bool($value)) {
            throw $this->typeError($key, 'a boolean', $value);
        }

        return $value;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function count(): int
    {
        return count($this->entries());
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function dateTime(string $key): DateTimeImmutable
    {
        $value = $this->get($key);
        if (!($value instanceof DateTimeImmutable)) {
            throw $this->typeError($key, 'an immutable date and time', $value);
        }

        return $value;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function entries(): array
    {
        if (is_array($this->source)) {
            return $this->source;
        }

        return self::stringKeyed(get_object_vars($this->source));
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function enum(string $key, string $enumClass): object
    {
        $value = $this->get($key);
        if (!($value instanceof $enumClass)) {
            throw $this->typeError($key, 'a case of ' . $enumClass, $value);
        }

        return $value;
    }

    /**
     * @inheritDoc
     *
     * `null`, `false`, `0`, `0.0`, `''`, `'0'` and the empty array are falsy; every other value, an object included,
     * is truthy.
     */
    #[Override]
    public function falsy(string $key): bool
    {
        $value = $this->getOrNull($key);

        return $value === null
            || $value === false
            || $value === 0
            || $value === 0.0
            || $value === ''
            || $value === '0'
            || $value === [];
    }

    /**
     * @inheritDoc
     *
     * An integer is accepted and widened to a float.
     */
    #[Override]
    public function float(string $key): float
    {
        $value = $this->get($key);
        if (is_int($value)) {
            return (float) $value;
        }
        if (!is_float($value)) {
            throw $this->typeError($key, 'a float', $value);
        }

        return $value;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function get(string $key): mixed
    {
        $found = false;
        $value = $this->locate($key, $found);
        if (!$found) {
            throw new OutOfBoundsException(sprintf('Key "%s" does not exist.', $key));
        }

        return $value;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function has(string $key): bool
    {
        $found = false;
        $this->locate($key, $found);

        return $found;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function int(string $key): int
    {
        $value = $this->get($key);
        if (!is_int($value)) {
            throw $this->typeError($key, 'an integer', $value);
        }

        return $value;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function keys(): array
    {
        return array_keys($this->entries());
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function nullArray(string $key): ?array
    {
        $value = $this->getOrNull($key);

        return is_array($value) ? $value : null;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function nullBool(string $key): ?bool
    {
        $value = $this->getOrNull($key);

        return is_bool($value) ? $value : null;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function nullDateTime(string $key): ?DateTimeImmutable
    {
        $value = $this->getOrNull($key);

        return $value instanceof DateTimeImmutable ? $value : null;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function nullEnum(string $key, string $enumClass): ?object
    {
        $value = $this->getOrNull($key);

        return $value instanceof $enumClass ? $value : null;
    }

    /**
     * @inheritDoc
     *
     * An integer is accepted and widened to a float.
     */
    #[Override]
    public function nullFloat(string $key): ?float
    {
        $value = $this->getOrNull($key);
        if (is_int($value)) {
            return (float) $value;
        }

        return is_float($value) ? $value : null;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function nullInt(string $key): ?int
    {
        $value = $this->getOrNull($key);

        return is_int($value) ? $value : null;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function nullObject(string $key, string $className): ?object
    {
        $value = $this->getOrNull($key);

        return $value instanceof $className ? $value : null;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function nullReader(string $key): ?static
    {
        $value = $this->getOrNull($key);
        if (is_object($value)) {
            return new self($value);
        }
        if (!is_array($value)) {
            return null;
        }
        foreach (array_keys($value) as $nestedKey) {
            if (!is_string($nestedKey)) {
                return null;
            }
        }

        return new self($value);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function nullString(string $key): ?string
    {
        $value = $this->getOrNull($key);

        return is_string($value) ? $value : null;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function object(string $key, string $className): object
    {
        $value = $this->get($key);
        if (!($value instanceof $className)) {
            throw $this->typeError($key, 'an instance of ' . $className, $value);
        }

        return $value;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function reader(string $key): static
    {
        $value = $this->get($key);
        if (!is_array($value) && !is_object($value)) {
            throw $this->typeError($key, 'an array or an object', $value);
        }

        return new self($value);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function string(string $key): string
    {
        $value = $this->get($key);
        if (!is_string($value)) {
            throw $this->typeError($key, 'a string', $value);
        }

        return $value;
    }

    #endregion implements IDataReader

    /**
     * Returns the entries whose keys are all strings.
     *
     * @param array $source The entries to check.
     *
     * @return array The same entries, with their key type narrowed to string.
     *
     * @throws InvalidArgumentException if any key is not a string.
     *
     * @phpstan-param array<array-key,mixed> $source
     *
     * @phpstan-return array<string,mixed>
     */
    private static function stringKeyed(array $source): array
    {
        $entries = [];
        foreach ($source as $key => $value) {
            if (!is_string($key)) {
                throw new InvalidArgumentException(sprintf('Key %d is not a string.', $key));
            }

            $entries[$key] = $value;
        }

        return $entries;
    }

    /**
     * Converts a value to a boolean with the `FILTER_VALIDATE_BOOL` filter.
     *
     * @param mixed $value The value to convert.
     *
     * @return ?bool The converted value, or `null` if the value is not recognised.
     */
    private function convertToBool(mixed $value): ?bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if (!is_int($value) && !is_float($value) && !is_string($value)) {
            return null;
        }

        return filter_var($value, \FILTER_VALIDATE_BOOL, \FILTER_NULL_ON_FAILURE);
    }

    /**
     * Converts a value to a float.
     *
     * @param mixed $value The value to convert.
     *
     * @return ?float The converted value, or `null` if the value is not convertible.
     */
    private function convertToFloat(mixed $value): ?float
    {
        if (is_float($value)) {
            return $value;
        }
        if (is_int($value)) {
            return (float) $value;
        }
        if (is_bool($value)) {
            return $value ? 1.0 : 0.0;
        }
        if (is_string($value) && is_numeric($value)) {
            return (float) $value;
        }

        return null;
    }

    /**
     * Converts a value to an integer, discarding any fractional part and rejecting anything out of the integer range.
     *
     * @param mixed $value The value to convert.
     *
     * @return ?int The converted value, or `null` if the value is not convertible.
     */
    private function convertToInt(mixed $value): ?int
    {
        if (is_bool($value)) {
            return $value ? 1 : 0;
        }
        if (is_string($value)) {
            if (!is_numeric($value)) {
                return null;
            }
            $value = $value + 0;
        }
        if (is_int($value)) {
            return $value;
        }
        if (is_float($value)) {
            if (!is_finite($value)) {
                return null;
            }
            if ($value < (float) \PHP_INT_MIN || $value > (float) \PHP_INT_MAX) {
                return null;
            }

            return (int) $value;
        }

        return null;
    }

    /**
     * Returns the value stored under a key, or `null` when the key is absent.
     *
     * @param string $key The key, in dot notation for a nested value.
     *
     * @return mixed The value, or `null` if the key is absent.
     */
    private function getOrNull(string $key): mixed
    {
        $found = false;

        return $this->locate($key, $found);
    }

    /**
     * Looks a key up, following dot notation when no entry matches the key as a whole.
     *
     * @param string $key The key, in dot notation for a nested value.
     * @param bool $found Set to true when the key resolves to a value, and to false otherwise.
     *
     * @return mixed The value, or `null` if the key is absent.
     */
    private function locate(string $key, bool &$found): mixed
    {
        $value = $this->lookup($this->source, $key, $found);
        if ($found || !str_contains($key, '.')) {
            return $value;
        }

        $value = $this->source;
        foreach (explode('.', $key) as $segment) {
            $value = $this->lookup($value, $segment, $found);
            if (!$found) {
                return null;
            }
        }

        return $value;
    }

    /**
     * Reads one segment out of a node of the source.
     *
     * An array is read by key. An object is read by offset when it implements `ArrayAccess`, then by public property,
     * and finally through `__isset()` and `__get()` when the object defines both. Any other node holds no segment.
     *
     * @param mixed $node The node to read from.
     * @param string $segment The key, offset or property name to read.
     * @param bool $found Set to true when the segment resolves to a value, and to false otherwise.
     *
     * @return mixed The value, or `null` if the segment is absent.
     */
    private function lookup(mixed $node, string $segment, bool &$found): mixed
    {
        $found = true;
        if (is_array($node)) {
            if (array_key_exists($segment, $node)) {
                return $node[$segment];
            }
        } elseif (is_object($node)) {
            if ($node instanceof ArrayAccess && $node->offsetExists($segment)) {
                return $node->offsetGet($segment);
            }

            $properties = get_object_vars($node);
            if (array_key_exists($segment, $properties)) {
                return $properties[$segment];
            }
            if (method_exists($node, '__isset') && method_exists($node, '__get') && $node->__isset($segment)) {
                return $node->__get($segment);
            }
        }

        $found = false;

        return null;
    }

    /**
     * Creates the exception thrown when a value has an unexpected type.
     *
     * @param string $key The key which was read.
     * @param string $expected The description of the expected type.
     * @param mixed $value The value found.
     *
     * @return InvalidArgumentException The exception to throw.
     */
    private function typeError(string $key, string $expected, mixed $value): InvalidArgumentException
    {
        return new InvalidArgumentException(
            sprintf('Value of key "%s" is expected to be %s, %s given.', $key, $expected, get_debug_type($value))
        );
    }
}
