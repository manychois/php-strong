<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\Collections;

use BadMethodCallException;
use Iterator;
use Manychois\PhpStrong\Collections\ComparerInterface as IComparer;
use Manychois\PhpStrong\Collections\ListInterface as IList;
use Manychois\PhpStrong\Collections\ReadonlyListInterface as IReadonlyList;
use Manychois\PhpStrong\Collections\SequenceInterface as ISequence;
use Override;

/**
 * A readonly list implementation.
 *
 * @template T
 *
 * @implements IReadonlyList<T>
 */
class ReadonlyList implements IReadonlyList
{
    /**
     * The inner ArrayList that holds the items of the readonly list.
     *
     * @var ArrayList<T>
     */
    private readonly ArrayList $inner;

    /**
     * Constructs a new ReadonlyList instance.
     *
     * @param iterable<T> $source The source of items to initialize the list with.
     */
    public function __construct(iterable $source)
    {
        $this->inner = new ArrayList($source);
    }

    #region implements IReadonlyList

    /**
     * @inheritDoc
     */
    #[Override]
    public function all(callable $predicate): bool
    {
        return $this->inner->all($predicate);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function any(callable $predicate): bool
    {
        return $this->inner->any($predicate);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function asArray(): array
    {
        return $this->inner->asArray();
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function asList(): IList
    {
        return $this->inner->asList();
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function at(int $index): mixed
    {
        return $this->inner->at($index);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function chunk(int $size): ISequence
    {
        return $this->inner->chunk($size);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function contains(mixed $value): bool
    {
        return $this->inner->contains($value);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function count(): int
    {
        return $this->inner->count();
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function distinct(): ISequence
    {
        return $this->inner->distinct();
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function except(iterable $sequence): ISequence
    {
        return $this->inner->except($sequence);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function filter(callable $predicate): ISequence
    {
        return $this->inner->filter($predicate);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function findIndex(callable $predicate, int $start = 0): int
    {
        return $this->inner->findIndex($predicate, $start);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function findLastIndex(callable $predicate, int $start = -1): int
    {
        return $this->inner->findLastIndex($predicate, $start);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function first(?callable $predicate = null): mixed
    {
        return $this->inner->first($predicate);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function firstOrNull(?callable $predicate = null): mixed
    {
        return $this->inner->firstOrNull($predicate);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function getIterator(): Iterator
    {
        return $this->inner->getIterator();
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function indexOf(mixed $item, int $start = 0): int
    {
        return $this->inner->indexOf($item, $start);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function intersect(iterable $sequence): ISequence
    {
        return $this->inner->intersect($sequence);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function isEmpty(): bool
    {
        return $this->inner->isEmpty();
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function last(?callable $predicate = null): mixed
    {
        return $this->inner->last($predicate);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function lastIndexOf(mixed $item, int $start = -1): int
    {
        return $this->inner->lastIndexOf($item, $start);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function lastOrNull(?callable $predicate = null): mixed
    {
        return $this->inner->lastOrNull($predicate);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function map(callable $callback): ISequence
    {
        return $this->inner->map($callback);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function offsetExists(mixed $offset): bool
    {
        return $this->inner->offsetExists($offset);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function offsetGet(mixed $offset): mixed
    {
        return $this->inner->offsetGet($offset);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function offsetSet(mixed $offset, mixed $value): void
    {
        throw new BadMethodCallException('Cannot modify a readonly list.');
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function offsetUnset(mixed $offset): void
    {
        throw new BadMethodCallException('Cannot modify a readonly list.');
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function orderBy(IComparer $comparer): ISequence
    {
        return $this->inner->orderBy($comparer);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function orderDescBy(IComparer $comparer): ISequence
    {
        return $this->inner->orderDescBy($comparer);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function precededBy(iterable ...$sequences): ISequence
    {
        return $this->inner->precededBy(...$sequences);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function reduce(callable $callback, mixed $initial): mixed
    {
        return $this->inner->reduce($callback, $initial);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function reverse(): ISequence
    {
        return $this->inner->reverse();
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function shuffle(): ISequence
    {
        return $this->inner->shuffle();
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function skip(int $count): ISequence
    {
        return $this->inner->skip($count);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function skipLast(int $count): ISequence
    {
        return $this->inner->skipLast($count);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function slice(int $index, int $length): ISequence
    {
        return $this->inner->slice($index, $length);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function take(int $count): ISequence
    {
        return $this->inner->take($count);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function takeLast(int $count): ISequence
    {
        return $this->inner->takeLast($count);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function then(iterable ...$sequences): ISequence
    {
        return $this->inner->then(...$sequences);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function union(iterable $sequence): ISequence
    {
        return $this->inner->union($sequence);
    }

    #endregion implements IReadonlyList
}
