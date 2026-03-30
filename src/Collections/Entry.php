<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\Collections;

/**
 * Represents an entry with a key and a value.
 *
 * @template TKey
 * @template TValue
 */
class Entry
{
    /**
     * The key of the entry.
     *
     * @var TKey
     */
    private readonly mixed $k;

    /**
     * The value of the entry.
     *
     * @var TValue
     */
    private readonly mixed $v;

    /**
     * Initializes a new entry with the specified key and value.
     *
     * @param TKey $key The key of the entry.
     * @param TValue $value The value of the entry.
     */
    public function __construct(mixed $key, mixed $value)
    {
        $this->k = $key;
        $this->v = $value;
    }

    public mixed $key { get => $this->k; }
    public mixed $value { get => $this->v; }
}
