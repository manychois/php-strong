<?php

declare(strict_types=1);

namespace Manychois\PhpStrong;

use Manychois\PhpStrong\Defaults\DefaultEqualityComparer;

/**
 * Represents a value object.
 */
final class ValueObject implements EqualInterface
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
