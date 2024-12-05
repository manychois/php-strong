<?php

declare(strict_types=1);

namespace Manychois\PhpStrong;

use Manychois\PhpStrong\Defaults\DefaultEqualityComparer;

/**
 * Represents a key-value pair.
 *
 * @template TKey
 * @template TValue
 */
final class KeyValuePair implements EqualInterface
{
    /**
     * @var TKey
     */
    public readonly mixed $key;
    /**
     * @var TValue
     */
    public readonly mixed $item;

    /**
     * Initializes a new instance of the KeyValue class.
     *
     * @param TKey   $key   The key of the key-value pair.
     * @param TValue $value The value of the key-value pair.
     */
    public function __construct(mixed $key, mixed $value)
    {
        $this->key = $key;
        $this->item = $value;
    }

    #region implements EqualInterface

    /**
     * @inheritDoc
     */
    public function equals(mixed $other): bool
    {
        if (!($other instanceof self)) {
            return false;
        }

        $eq = new DefaultEqualityComparer();

        return $eq->equals($this->key, $other->key) && $eq->equals($this->item, $other->item);
    }

    #endregion implements EqualInterface
}
