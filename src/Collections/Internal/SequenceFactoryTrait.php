<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\Collections\Internal;

use Traversable;

/**
 * A trait that provides factory methods for creating instances of the sequence.
 */
trait SequenceFactoryTrait
{
    /**
     * Initializes a new sequence of objects.
     *
     * @template TObject of object
     *
     * @param class-string<TObject>               $class   The class of the items in the sequence.
     * @param array<TObject>|Traversable<TObject> $initial The initial items of the sequence.
     *
     * @return self<TObject> The new instance.
     */
    public static function ofObject(string $class, array|Traversable $initial = []): self
    {
        // @phpstan-ignore return.type
        return new self($initial);
    }

    /**
     * Initializes a new sequence of strings.
     *
     * @param array<string>|Traversable<string> $initial The initial items of the sequence.
     *
     * @return self<string> The new instance.
     */
    public static function ofString(array|Traversable $initial = []): self
    {
        // @phpstan-ignore return.type
        return new self($initial);
    }
}
