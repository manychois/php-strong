<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\Collections;

use Iterator;
use IteratorIterator;
use Manychois\PhpStrong\Collections\Internal\AbstractCollection;
use Traversable;

/**
 * Represents a collection of items.
 *
 * @template TKey
 * @template TItem
 *
 * @template-extends AbstractCollection<TKey,TItem>
 */
class TraversableCollection extends AbstractCollection
{
    /**
     * @var Traversable<TKey,TItem>
     */
    protected readonly Traversable $traversable;

    /**
     * Initializes a collection from a traversable object.
     *
     * @param Traversable<TKey,TItem> $traversable The traversable object.
     */
    public function __construct(Traversable $traversable)
    {
        $this->traversable = $traversable;
    }

    #region extends AbstractCollection

    public function getIterator(): Iterator
    {
        if ($this->traversable instanceof Iterator) {
            return $this->traversable;
        }

        return new IteratorIterator($this->traversable);
    }

    #endregion extends AbstractCollection
}
