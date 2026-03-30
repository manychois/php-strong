<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\Collections;

use Manychois\PhpStrong\Collections\Internal\AbstractBaseList;
use Manychois\PhpStrong\Collections\ListInterface as IList;
use OutOfBoundsException;
use Override;

/**
 * A mutable list implementation.
 *
 * @template T
 *
 * @extends AbstractBaseList<T>
 * @implements IList<T>
 */
class ArrayList extends AbstractBaseList implements IList
{
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
    public function set(int $index, mixed $item): void
    {
        $index = $this->normaliseIndex($index, true);
        // @phpstan-ignore assign.propertyType
        $this->source[$index] = $item;
    }

    #endregion implements IList
}
