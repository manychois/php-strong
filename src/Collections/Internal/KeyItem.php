<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\Collections\Internal;

/**
 * Represents a key-item pair.
 *
 * @template TKey
 * @template TItem
 */
final class KeyItem
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
}
