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
final class KeyValue implements EqualInterface
{
    /**
     * @var TKey
     */
    public readonly mixed $key;
    /**
     * @var TValue
     */
    public readonly mixed $item;

    public function __construct(mixed $key, mixed $item)
    {
        $this->key = $key;
        $this->item = $item;
    }

    #region implements EqualInterface

    public function equals(mixed $other): bool
    {
        if (!($other instanceof self)) {
            return false;
        }

        $c = new DefaultEqualityComparer();

        return $c->equals($this->key, $other->key) && $c->equals($this->item, $other->item);
    }

    #endregion implements EqualInterface
}
