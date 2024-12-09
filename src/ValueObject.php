<?php

declare(strict_types=1);

namespace Manychois\PhpStrong;

use Manychois\PhpStrong\Defaults\DefaultComparer;
use Manychois\PhpStrong\Defaults\DefaultEqualityComparer;
use Stringable;
use TypeError;

/**
 * Represents a value object.
 */
final class ValueObject implements EqualInterface, ComparableInterface
{
    /**
     * The raw value of the value object.
     *
     * @var mixed
     */
    public readonly mixed $raw;

    /**
     * Initializes a new instance of the ValueObject class.
     *
     * @param mixed $value The value of the value object.
     */
    public function __construct(mixed $value)
    {
        $this->raw = $value;
    }

    /**
     * Converts the value object to a boolean value, using the `filter_var()` function.
     * If the conversion fails, `null` will be returned.
     *
     * @return ?bool The boolean value, or `null` if the conversion fails.
     */
    public function asBool(): ?bool
    {
        return \filter_var($this->raw, \FILTER_VALIDATE_BOOLEAN, \FILTER_NULL_ON_FAILURE);
    }

    /**
     * Converts the value object to an integer value, using the `filter_var()` function.
     * If the conversion fails, `null` will be returned.
     *
     * @return ?int The integer value, or `null` if the conversion fails.
     */
    public function asInt(): ?int
    {
        return \filter_var($this->raw, \FILTER_VALIDATE_INT, \FILTER_NULL_ON_FAILURE);
    }

    /**
     * Converts the value object to a float value, using the `filter_var()` function.
     * If the conversion fails, `null` will be returned.
     *
     * @return ?float The float value, or `null` if the conversion fails.
     */
    public function asFloat(): ?float
    {
        return \filter_var($this->raw, \FILTER_VALIDATE_FLOAT, \FILTER_NULL_ON_FAILURE);
    }

    /**
     * Returns the raw value if it is an instance of the specified class, otherwise `null.
     *
     * @param class-string<TObject> $class The class name.
     *
     * @template TObject
     *
     * @return TObject|null The raw value as an instance of the specified class, otherwise `null`.
     */
    public function asObject(string $class): mixed
    {
        return $this->raw instanceof $class ? $this->raw : null;
    }

    /**
     * Returns the raw value only if it is a boolean value, otherwise throws a `TypeError`.
     *
     * @return bool The raw value as a boolean value.
     */
    public function asStrictBool(): bool
    {
        if (\is_bool($this->raw)) {
            return $this->raw;
        }

        throw new TypeError('The value is not a boolean value.');
    }

    /**
     * Returns the raw value only if it is an integer value, otherwise throws a `TypeError`.
     *
     * @return int The raw value as an integer value.
     */
    public function asStrictInt(): int
    {
        if (\is_int($this->raw)) {
            return $this->raw;
        }

        throw new TypeError('The value is not an integer value.');
    }

    /**
     * Returns the raw value only if it is a float or integer value, otherwise throws a `TypeError`.
     *
     * @return float The raw value as a float value.
     */
    public function asStrictFloat(): float
    {
        if (\is_float($this->raw) || \is_int($this->raw)) {
            return (float) $this->raw;
        }

        throw new TypeError('The value is not a float value.');
    }

    /**
     * Returns the raw value only if it is an instance of the specified class, otherwise throws a `TypeError`.
     *
     * @template TObject
     *
     * @param class-string<TObject> $class The class name.
     *
     * @return TObject The raw value as an instance of the specified class.
     */
    public function asStrictObject(string $class): mixed
    {
        if (\is_object($this->raw) && $this->raw instanceof $class) {
            return $this->raw;
        }

        throw new TypeError(\sprintf('The value is not an instance of %s.', $class));
    }

    /**
     * Returns the raw value only if it is a string value, otherwise throws a `TypeError`.
     *
     * @return string The raw value as a string value.
     */
    public function asStrictString(): string
    {
        if (\is_string($this->raw)) {
            return $this->raw;
        }

        throw new TypeError('The value is not a string value.');
    }

    /**
     * Converts the raw value to a string value.
     *
     * @return string|null The raw value as a string value, or `null` if the conversion fails.
     */
    public function asString(): ?string
    {
        if (\is_string($this->raw) || $this->raw === null) {
            return $this->raw;
        }

        if (\is_array($this->raw)) {
            return null;
        }

        if (\is_object($this->raw)) {
            if ($this->raw instanceof Stringable) {
                return $this->raw->__toString();
            }

            return null;
        }

        if (\is_bool($this->raw) || \is_int($this->raw) || \is_float($this->raw) || \is_resource($this->raw)) {
            return \strval($this->raw);
        }

        return null;
    }

    /**
     * Checks if the raw value is `null`.
     *
     * @return bool `true` if the raw value is `null`, otherwise `false`.
     */
    public function isNull(): bool
    {
        return $this->raw === null;
    }

    #region implements ComparableInterface

    /**
     * @inheritDoc
     */
    public function compareTo(mixed $other): int
    {
        if ($other === null) {
            throw new TypeError('The argument $other cannot be null.');
        }

        $cmp = new DefaultComparer();
        if ($other instanceof self) {
            return $cmp->compare($this->raw, $other->raw);
        }

        return $cmp->compare($this->raw, $other);
    }

    #endregion implements ComparableInterface

    #region implements EqualInterface

    /**
     * @inheritDoc
     */
    public function equals(mixed $other): bool
    {
        if ($other === null) {
            return false;
        }
        if ($other === $this) {
            return true;
        }
        $eq = new DefaultEqualityComparer();
        if ($other instanceof self) {
            return $eq->equals($this->raw, $other->raw);
        }

        return $eq->equals($this->raw, $other);
    }

    #endregion implements EqualInterface
}
