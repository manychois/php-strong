<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\Collections;

use InvalidArgumentException;
use Iterator;
use Manychois\PhpStrong\Collections\ComparerInterface as IComparer;
use Manychois\PhpStrong\Collections\ListInterface as IList;
use Manychois\PhpStrong\Collections\SequenceInterface as ISequence;
use OutOfBoundsException;
use Override;

/**
 * A mutable list implementation.
 *
 * @template T
 *
 * @implements IList<T>
 */
class ArrayList implements IList
{
    /**
     * @var list<T>
     */
    private array $source;

    /**
     * Initializes a new list with the specified source.
     *
     * @param iterable<T> $source The source iterable for the list.
     */
    public function __construct(iterable $source = [])
    {
        if (is_array($source)) {
            if (array_is_list($source)) {
                $this->source = $source;
            } else {
                $this->source = array_values($source);
            }
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

    #region implements IList

    /**
     * @inheritDoc
     */
    #[Override]
    public function add(mixed ...$items): void
    {
        foreach ($items as $item) {
            $this->source[] = $item;
        }
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function addRange(iterable ...$ranges): void
    {
        foreach ($ranges as $range) {
            foreach ($range as $item) {
                $this->source[] = $item;
            }
        }
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function all(callable $predicate): bool
    {
        return array_all($this->source, $predicate);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function any(callable $predicate): bool
    {
        return array_any($this->source, $predicate);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function asArray(): array
    {
        return $this->source;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function asList(): ListInterface
    {
        return new self($this->source);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function asReadonly(): ReadonlyListInterface
    {
        return new ReadonlyList($this->source);
    }

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
    public function chunk(int $size): ISequence
    {
        return new LazySequence($this->source)->chunk($size);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function clear(): void
    {
        $this->source = [];
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function contains(mixed $value): bool
    {
        return in_array($value, $this->source, true);
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
    public function distinct(): ISequence
    {
        return new LazySequence($this->source)->distinct();
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function except(iterable $sequence): ISequence
    {
        return new LazySequence($this->source)->except($sequence);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function filter(callable $predicate): ISequence
    {
        return new LazySequence($this->source)->filter($predicate);
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
    public function first(?callable $predicate = null): mixed
    {
        return new LazySequence($this->source)->first($predicate);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function firstOrNull(?callable $predicate = null): mixed
    {
        return new LazySequence($this->source)->firstOrNull($predicate);
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

    /**
     * @inheritDoc
     */
    #[Override]
    public function indexOf(mixed $item, int $start = 0): int
    {
        $start = $this->normaliseIndex($start, false, 'Start index');
        $count = count($this->source);
        for ($i = $start; $i < $count; $i++) {
            if ($this->source[$i] === $item) {
                return $i;
            }
        }
        return -1;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function insert(int $index, mixed ...$items): void
    {
        $index = $this->normaliseIndex($index, true);
        array_splice($this->source, $index, 0, $items);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function insertRange(int $index, iterable ...$ranges): void
    {
        $index = $this->normaliseIndex($index, true);
        $merged = [];
        foreach ($ranges as $range) {
            foreach ($range as $item) {
                $merged[] = $item;
            }
        }
        array_splice($this->source, $index, 0, $merged);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function intersect(iterable $sequence): ISequence
    {
        return new LazySequence($this->source)->intersect($sequence);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function isEmpty(): bool
    {
        return count($this->source) === 0;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function last(?callable $predicate = null): mixed
    {
        return new LazySequence($this->source)->last($predicate);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function lastIndexOf(mixed $item, int $start = -1): int
    {
        $start = $this->normaliseIndex($start, false, 'Start index');
        for ($i = $start; $i >= 0; $i--) {
            if ($this->source[$i] === $item) {
                return $i;
            }
        }
        return -1;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function lastOrNull(?callable $predicate = null): mixed
    {
        return new LazySequence($this->source)->lastOrNull($predicate);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function map(callable $callback): ISequence
    {
        return new LazySequence($this->source)->map($callback);
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

    /**
     * @inheritDoc
     */
    #[Override]
    public function offsetSet(mixed $offset, mixed $value): void
    {
        if ($offset === null) {
            $this->add($value);
            return;
        }
        if (!is_int($offset)) {
            throw new \InvalidArgumentException('Offset must be an integer');
        }
        $count = $this->count();
        $newIndex = $offset;
        if ($newIndex < 0) {
            $newIndex += $count;
            if ($newIndex === -1) {
                array_unshift($this->source, $value);
            } elseif ($newIndex < 0) {
                throw new OutOfBoundsException(sprintf('Offset out of bounds: %d', $offset));
            }
            // @phpstan-ignore assign.propertyType
            $this->source[$newIndex] = $value;
        } elseif ($newIndex === $count) {
            $this->source[] = $value;
        } elseif ($newIndex < $count) {
            // @phpstan-ignore assign.propertyType
            $this->source[$newIndex] = $value;
        } else {
            throw new OutOfBoundsException(sprintf('Offset out of bounds: %d', $offset));
        }
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function offsetUnset(mixed $offset): void
    {
        $count = $this->count();
        if ($offset < 0) {
            $offset += $count;
        }
        if ($offset < 0 || $offset >= $count) {
            return;
        }
        array_splice($this->source, $offset, 1);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function orderBy(IComparer $comparer): ISequence
    {
        return new LazySequence($this->source)->orderBy($comparer);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function orderDescBy(IComparer $comparer): ISequence
    {
        return new LazySequence($this->source)->orderDescBy($comparer);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function precededBy(iterable ...$sequences): ISequence
    {
        return new LazySequence($this->source)->precededBy(...$sequences);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function reduce(callable $callback, mixed $initial): mixed
    {
        return new LazySequence($this->source)->reduce($callback, $initial);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function remove(mixed $item): bool
    {
        $i = array_search($item, $this->source, true);
        if (is_int($i)) {
            array_splice($this->source, $i, 1);
            return true;
        }
        return false;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function removeAll(callable $predicate): ListInterface
    {
        $removed = [];
        $count = count($this->source);
        $i = 0;
        while ($i < $count) {
            if ($predicate($this->source[$i], $i)) {
                $removed[] = $this->source[$i];
                array_splice($this->source, $i, 1);
                $count--;
            } else {
                $i++;
            }
        }

        return new self($removed);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function removeAt(int ...$indices): int
    {
        $normalizedIndices = [];
        foreach ($indices as $index) {
            $normalizedIndices[] = $this->normaliseIndex($index);
        }
        $normalizedIndices = array_unique($normalizedIndices);
        rsort($normalizedIndices, \SORT_NUMERIC);
        foreach ($normalizedIndices as $index) {
            array_splice($this->source, $index, 1);
        }

        return count($normalizedIndices);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function reverse(): ISequence
    {
        $reversed = array_reverse($this->source);
        return new self($reversed);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function set(int $index, mixed $item): void
    {
        $index = $this->normaliseIndex($index, true);
        // @phpstan-ignore assign.propertyType
        $this->source[$index] = $item;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function shuffle(): ISequence
    {
        $shuffled = $this->source;
        shuffle($shuffled);
        return new self($shuffled);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function slice(int $index, int $length): ISequence
    {
        return new LazySequence($this->source)->slice($index, $length);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function skip(int $count): ISequence
    {
        return new LazySequence($this->source)->skip($count);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function skipLast(int $count): ISequence
    {
        return new LazySequence($this->source)->skipLast($count);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function take(int $count): ISequence
    {
        return new LazySequence($this->source)->take($count);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function takeLast(int $count): ISequence
    {
        return new LazySequence($this->source)->takeLast($count);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function then(iterable ...$sequences): ISequence
    {
        return new LazySequence($this->source)->then(...$sequences);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function union(iterable $sequence): ISequence
    {
        return new LazySequence($this->source)->union($sequence);
    }

    #endregion implements IList
}
