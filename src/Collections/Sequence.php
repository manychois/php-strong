<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\Collections;

use Iterator;
use Manychois\PhpStrong\Collections\Internal\AbstractBaseSequence;
use Override;

/**
 * A lazy sequence backed by an iterable source.
 *
 * @template T
 *
 * @extends AbstractBaseSequence<T>
 */
class Sequence extends AbstractBaseSequence
{
    /**
     * @var iterable<T>
     */
    protected iterable $source;

    /**
     * @param iterable<T> $source iterable for the sequence.
     */
    public function __construct(iterable $source)
    {
        $this->source = $source;
    }

    #region extends AbstractSequence

    /**
     * @inheritDoc
     */
    #[Override]
    protected function createLazySequence(iterable $source): SequenceInterface
    {
        return new Sequence($source);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function count(): int
    {
        if (is_array($this->source)) {
            return count($this->source);
        }
        return \iterator_count($this->source);
    }

    /**
    * @inheritDoc
    */
    #[Override]
    public function getIterator(): Iterator
    {
        $i = 0;
        foreach ($this->source as $item) {
            yield $i => $item;
            $i++;
        }
    }

    #endregion extends AbstractSequence
}
