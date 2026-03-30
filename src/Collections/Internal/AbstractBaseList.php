<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\Collections\Internal;

use ArrayIterator;
use InvalidArgumentException;
use Iterator;
use Manychois\PhpStrong\Collections\ComparerInterface as IComparer;
use Manychois\PhpStrong\Collections\EqualityComparerInterface as IEqualityComparer;
use Manychois\PhpStrong\Collections\LazySequence;
use Manychois\PhpStrong\Collections\ReadonlyListInterface as IReadonlyList;
use Manychois\PhpStrong\Collections\SequenceInterface as ISequence;
use OutOfBoundsException;
use Override;

/**
 * Provides common implementations for list-like collections.
 *
 * @internal
 *
 * @template T
 *
 * @extends AbstractBaseSequence<T>
 *
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
    protected function createLazySequence(iterable $source): ISequence
    {
        return new LazySequence($source);
    }

    /**
     * Creates a new readonly list from the specified iterable.
     *
     * @param iterable<T> $source The source iterable.
     *
     * @return IReadonlyList<T> A new readonly list.
     */
    abstract protected function createReadonlyList(iterable $source): IReadonlyList;

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
    #[Override]
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
    #[Override]
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
    #[Override]
    public function at(int $index): mixed
    {
        $index = $this->normaliseIndex($index);
        return $this->source[$index];
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function findIndex(callable $predicate, int $start = 0): int
    {
        $start = $this->normaliseIndex($start, false, 'Start index');
        $count = count($this->source);
        for ($i = $start; $i < $count; $i++) {
            if ($predicate($this->source[$i], $i)) {
                return $i;
            }
        }

        return -1;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function findLastIndex(callable $predicate, int $start = -1): int
    {
        $start = $this->normaliseIndex($start, false, 'Start index');
        for ($i = $start; $i >= 0; $i--) {
            if ($predicate($this->source[$i], $i)) {
                return $i;
            }
        }

        return -1;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function indexOf(mixed $item, int $start = 0, ?IEqualityComparer $eq = null): int
    {
        $eq = $this->getEqualityComparer($eq);
        $start = $this->normaliseIndex($start, false, 'Start index');
        $count = count($this->source);
        for ($i = $start; $i < $count; $i++) {
            if ($eq->equals($this->source[$i], $item)) {
                return $i;
            }
        }
        return -1;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function lastIndexOf(mixed $item, int $start = -1, ?IEqualityComparer $eq = null): int
    {
        $eq = $this->getEqualityComparer($eq);
        $start = $this->normaliseIndex($start, false, 'Start index');
        for ($i = $start; $i >= 0; $i--) {
            if ($eq->equals($this->source[$i], $item)) {
                return $i;
            }
        }
        return -1;
    }

    #endregion implements IReadonlyList

    #region implements ISequence optimizations

    /**
     * @inheritDoc
     */
    #[Override]
    public function isEmpty(): bool
    {
        return $this->source === [];
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function contains(mixed $value, ?IEqualityComparer $eq = null): bool
    {
        $eq = $this->getEqualityComparer($eq);
        foreach ($this->source as $item) {
            if ($eq->equals($item, $value)) {
                return true;
            }
        }
        return false;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function first(?callable $predicate = null): mixed
    {
        if ($predicate === null) {
            if ($this->source === []) {
                throw new \UnderflowException('The sequence is empty');
            }
            return $this->source[0];
        }

        foreach ($this->source as $i => $item) {
            if ($predicate($item, $i)) {
                return $item;
            }
        }
        throw new \RuntimeException('No item satisfies the predicate');
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function firstOrNull(?callable $predicate = null): mixed
    {
        if ($predicate === null) {
            return $this->source[0] ?? null;
        }

        foreach ($this->source as $i => $item) {
            if ($predicate($item, $i)) {
                return $item;
            }
        }
        return null;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function last(?callable $predicate = null): mixed
    {
        if ($predicate === null) {
            if ($this->source === []) {
                throw new \UnderflowException('The sequence is empty');
            }
            return $this->source[array_key_last($this->source)];
        }

        $count = count($this->source);
        for ($i = $count - 1; $i >= 0; $i--) {
            if ($predicate($this->source[$i], $i)) {
                return $this->source[$i];
            }
        }
        throw new \RuntimeException('No item satisfies the predicate');
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function lastOrNull(?callable $predicate = null): mixed
    {
        if ($predicate === null) {
            $count = count($this->source);
            return $count > 0 ? $this->source[$count - 1] : null;
        }

        $count = count($this->source);
        for ($i = $count - 1; $i >= 0; $i--) {
            if ($predicate($this->source[$i], $i)) {
                return $this->source[$i];
            }
        }
        return null;
    }

    /**
     * @inheritDoc
     *
     * @phpstan-return IReadonlyList<T>
     */
    #[Override]
    public function slice(int $index, int $length): IReadonlyList
    {
        if ($index < 0) {
            throw new \InvalidArgumentException('Index must be greater than or equal to 0');
        }
        if ($length < 0) {
            throw new \InvalidArgumentException('Length must be greater than or equal to 0');
        }
        if ($length === 0) {
            return $this->createReadonlyList([]);
        }
        return $this->createReadonlyList(array_slice($this->source, $index, $length));
    }

    /**
     * @inheritDoc
     *
     * @phpstan-return IReadonlyList<T>
     */
    #[Override]
    public function skip(int $count): IReadonlyList
    {
        if ($count < 0) {
            throw new \InvalidArgumentException('Count must be greater than or equal to 0');
        }
        if ($count === 0) {
            return $this->createReadonlyList($this->source);
        }
        return $this->createReadonlyList(array_slice($this->source, $count));
    }

    /**
     * @inheritDoc
     *
     * @phpstan-return IReadonlyList<T>
     */
    #[Override]
    public function take(int $count): IReadonlyList
    {
        if ($count < 0) {
            throw new \InvalidArgumentException('Count must be greater than or equal to 0');
        }
        if ($count === 0) {
            return $this->createReadonlyList([]);
        }
        return $this->createReadonlyList(array_slice($this->source, 0, $count));
    }

    /**
     * @inheritDoc
     *
     * @phpstan-return IReadonlyList<T>
     */
    #[Override]
    public function orderBy(IComparer $comparer): IReadonlyList
    {
        $list = $this->source;
        usort($list, function ($a, $b) use ($comparer) {
            return $comparer->compare($a, $b);
        });
        return $this->createReadonlyList($list);
    }

    /**
     * @inheritDoc
     *
     * @phpstan-return IReadonlyList<T>
     */
    #[Override]
    public function orderDescBy(IComparer $comparer): IReadonlyList
    {
        $list = $this->source;
        usort($list, function ($a, $b) use ($comparer) {
            return -$comparer->compare($a, $b);
        });
        return $this->createReadonlyList($list);
    }

    /**
     * @inheritDoc
     *
     * @phpstan-return IReadonlyList<T>
     */
    #[Override]
    public function reverse(): IReadonlyList
    {
        return $this->createReadonlyList(array_reverse($this->source));
    }

    /**
     * @inheritDoc
     *
     * @phpstan-return IReadonlyList<T>
     */
    #[Override]
    public function shuffle(): IReadonlyList
    {
        $list = $this->source;
        shuffle($list);
        return $this->createReadonlyList($list);
    }

    #endregion implements ISequence optimizations
}
