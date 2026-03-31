<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\Collections;

use BadMethodCallException;
use Iterator;
use Manychois\PhpStrong\Collections\ReadonlyMapInterface as IReadonlyMap;
use Manychois\PhpStrong\Collections\SequenceInterface as ISequence;
use Override;

/**
 * A readonly map implementation.
 *
 * @template TKey
 * @template TValue
 *
 * @implements IReadonlyMap<TKey, TValue>
 */
class ReadonlyMap implements IReadonlyMap
{
    /**
     * The inner map that backs this readonly view.
     *
     * @var IReadonlyMap<TKey, TValue>
     */
    private readonly IReadonlyMap $inner;

    /**
     * Constructs a new ReadonlyMap instance.
     *
     * @param IReadonlyMap<TKey, TValue> $source The map to wrap.
     */
    public function __construct(IReadonlyMap $source)
    {
        $this->inner = $source;
    }

    #region implements IReadonlyMap

    /**
     * @inheritDoc
     */
    #[Override]
    public DuplicationPolicy $duplicationPolicy { get => $this->inner->duplicationPolicy; }

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
    public function count(): int
    {
        return $this->inner->count();
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function entries(): ISequence
    {
        return $this->inner->entries();
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function flip(): Iterator
    {
        return $this->inner->flip();
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function get(mixed $key): mixed
    {
        return $this->inner->get($key);
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
    public function has(mixed $key): bool
    {
        return $this->inner->has($key);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function keys(): ISequence
    {
        return $this->inner->keys();
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function nullGet(mixed $key): mixed
    {
        return $this->inner->nullGet($key);
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
        throw new BadMethodCallException('Cannot modify a readonly map.');
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function offsetUnset(mixed $offset): void
    {
        throw new BadMethodCallException('Cannot modify a readonly map.');
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function values(): ISequence
    {
        return $this->inner->values();
    }

    #endregion implements IReadonlyMap
}
