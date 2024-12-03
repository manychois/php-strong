<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\Collections;

use Manychois\PhpStrong\Collections\Internal\AbstractSequence;

/**
 * Represents a read-only sequence of items.
 *
 * @template T
 *
 * @template-extends AbstractSequence<T>
 */
class ReadonlySequence extends AbstractSequence
{
    /**
     * Initializes a new instance of the ReadonlySequence class.
     *
     * @template TObject of object
     *
     * @param class-string<TObject> $class   The class of the items in the sequence.
     * @param iterable<TObject>     $initial The initial items of the sequence.
     *
     * @return self<TObject> The new instance.
     */
    public static function ofObject(string $class, iterable $initial = []): self
    {
        // @phpstan-ignore return.type
        return new self($initial);
    }

    /**
     * Initializes a new instance of the ReadonlySequence class.
     *
     * @param iterable<string> $initial The initial items of the sequence.
     *
     * @return self<string> The new instance.
     */
    public static function ofString(iterable $initial = []): self
    {
        // @phpstan-ignore return.type
        return new self($initial);
    }
}
