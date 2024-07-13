<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\Collections\Internal;

use Manychois\PhpStrong\DefaultEqualityComparer;
use Manychois\PhpStrong\EqualInterface;

/**
 * Represents a key-item pair.
 *
 * @template TKey
 * @template TItem
 */
final class KeyItem implements EqualInterface
{
    /**
     * @var TKey
     */
    public readonly mixed $key;
    /**
     * @var TItem
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
        if ($other instanceof self) {
            $comparer = new DefaultEqualityComparer();

            return $comparer->equals($this->key, $other->key) && $comparer->equals($this->item, $other->item);
        }

        return false;
    }

    #endregion implements EqualInterface
}
