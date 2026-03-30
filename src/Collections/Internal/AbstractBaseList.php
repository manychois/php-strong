<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\Collections\Internal;

use ArrayIterator;
use InvalidArgumentException;
use Iterator;
use Manychois\PhpStrong\Collections\ReadonlyListInterface as IReadonlyList;
use Manychois\PhpStrong\Collections\Sequence;
use Manychois\PhpStrong\Collections\SequenceInterface;
use OutOfBoundsException;
use Override;

/**
 * Provides common implementations for list-like collections.
 *
 * @template T
 *
 * @extends AbstractBaseSequence<T>
 * @implements IReadonlyList<T>
 */
abstract class AbstractBaseList extends AbstractBaseSequence implements IReadonlyList
{
    /** @var list<T> The source array for the list. */
    protected array $source;

    /**
     * Initializes a new sequence with the specified source.
     *
     * @param iterable<T> $source The source iterable for the sequence.
     */
    public function __construct(iterable $source)
    {
        if (is_array($source)) {
            $this->source = array_is_list($source) ? $source : array_values($source);
        } else {
            $this->source = iterator_to_array($source, false);
        }
    }

    /**
     * @param int $index The index to normalise.
     * @param bool $allowEnd Whether to allow index at the end position.
     * @param string $argName The name of the argument for error messages.
     *
     * @return int The normalised index.
     *
     * @throws OutOfBoundsException If the index is out of bounds.
     */
    protected function normaliseIndex(int $index, bool $allowEnd = false, string $argName = 'Index'): int
    {
        $count = $this->count();
        $newIndex = $index;
        if ($newIndex < 0) {
            $newIndex += $count + ($allowEnd ? 1 : 0);
        }
        if ($newIndex < 0 || $newIndex >= $count) {
            throw new OutOfBoundsException(sprintf('%s out of bounds: %d', $argName, $index));
        }
        return $newIndex;
    }

    #region extends AbstractBaseSequence

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
    public function getIterator(): Iterator
    {
        /** @var ArrayIterator<non-negative-int,T> $array */
        $array = new ArrayIterator($this->source);
        return $array;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function count(): int
    {
        return count($this->source);
    }

    /**
     * @inheritDoc
     */
    public function offsetExists(mixed $offset): bool
    {
        if (!is_int($offset)) {
            throw new InvalidArgumentException('Offset must be an integer');
        }
        $count = $this->count();
        return -$count <= $offset && $offset < $count;
    }

    /**
     * @inheritDoc
     */
    public function offsetGet(mixed $offset): mixed
    {
        if (!is_int($offset)) {
            throw new InvalidArgumentException('Offset must be an integer');
        }
        $index = $this->normaliseIndex($offset, false, 'Offset');
        return $this->source[$index];
    }

    #endregion extends AbstractBaseSequence

    #region implements IReadonlyList

    /**
     * @inheritDoc
     */
    public function at(int $index): mixed
    {
        $index = $this->normaliseIndex($index);
        return $this->source[$index];
    }

    /**
     * @inheritDoc
     */
    public function findIndex(callable $predicate, int $start = 0): int
    {
        $start = $this->normaliseIndex($start, true, 'Start index');
        $count = count($this->source);
        for ($i = $start; $i < $count; $i++) {
            if ($predicate($this->source[$i], $i)) {
                return $i;
            }
        }

        return -1;
    }

    #endregion implements IReadonlyList
}
