<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\Collections;

use Manychois\PhpStrong\Collections\EqualityComparerInterface as IEqualityComparer;
use Manychois\PhpStrong\Collections\Internal\AbstractBaseList;
use Manychois\PhpStrong\Collections\ListInterface as IList;
use Manychois\PhpStrong\Collections\ReadonlyListInterface as IReadonlyList;
use OutOfBoundsException;
use Override;

/**
 * A mutable list implementation.
 *
 * @template T
 *
 * @extends AbstractBaseList<T>
 *
 * @implements IList<T>
 */
class ArrayList extends AbstractBaseList implements IList
{
    /**
     * Initializes a new list with the specified source.
     *
     * @param iterable<T> $source The source iterable for the list.
     */
    final public function __construct(iterable $source = [])
    {
        parent::__construct($source);
    }

    #region extends AbstractBaseList

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
    protected function createReadonlyList(iterable $source): IReadonlyList
    {
        return new static($source);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    protected function createList(iterable $source): IList
    {
        return new static($source);
    }

    #endregion extends AbstractBaseList

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
     *
     * @phpstan-return ReadonlyList<T>
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
    public function clear(): void
    {
        $this->source = [];
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
        $items = [];
        foreach ($ranges as $range) {
            foreach ($range as $item) {
                $items[] = $item;
            }
        }
        array_splice($this->source, $index, 0, $items);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function remove(mixed $item, ?IEqualityComparer $eq = null): bool
    {
        $index = $this->indexOf($item, 0, $eq);
        if ($index === -1) {
            return false;
        }
        array_splice($this->source, $index, 1);
        return true;
    }

    /**
     * @inheritDoc
     *
     * @return static<T> A new list containing the removed items.
     */
    #[Override]
    public function removeAll(callable $predicate): static
    {
        $removed = new static();
        $count = count($this->source);
        $i = 0;
        while ($i < $count) {
            if ($predicate($this->source[$i], $i)) {
                $removed->add($this->source[$i]);
                array_splice($this->source, $i, 1);
                $count--;
            } else {
                $i++;
            }
        }

        return $removed;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function removeAt(int ...$indices): void
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

    #endregion implements IList
}
