<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\Collections;

use BadMethodCallException;
use Manychois\PhpStrong\Collections\Internal\AbstractBaseList;
use Manychois\PhpStrong\Collections\ReadonlyListInterface as IReadonlyList;
use Override;

/**
 * A readonly list implementation.
 *
 * @template T
 *
 * @extends AbstractBaseList<T>
 */
class ReadonlyList extends AbstractBaseList
{
    /**
     * Initializes a new readonly list with the specified source.
     *
     * @param iterable<T> $source The source iterable for the list.
     */
    final public function __construct(iterable $source = [])
    {
        parent::__construct($source);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    protected function createReadonlyList(iterable $source): IReadonlyList
    {
        return new static($source);
    }

    #region extends AbstractBaseList

    /**
     * @inheritDoc
     */
    #[Override]
    public function offsetSet(mixed $offset, mixed $value): void
    {
        throw new BadMethodCallException('Cannot modify a readonly list');
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function offsetUnset(mixed $offset): void
    {
        throw new BadMethodCallException('Cannot modify a readonly list');
    }

    #endregion extends AbstractBaseList
}
