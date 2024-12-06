<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\Collections;

use Manychois\PhpStrong\Collections\Internal\SequenceFactoryTrait;
use Manychois\PhpStrong\Defaults\DefaultEqualityComparer;
use Manychois\PhpStrong\EqualityComparerInterface;
use OutOfRangeException;
use Traversable;

/**
 * Represents a set of unique items.
 *
 * @template T
 *
 * @template-extends Sequence<T>
 */
class Set extends Sequence
{
    use SequenceFactoryTrait;

    public readonly EqualityComparerInterface $equalityComparer;

    /**
     * Initializes a new instance of the Set class.
     *
     * @param array<T>|Traversable<T>        $initials         The initial items of the set.
     * @param EqualityComparerInterface|null $equalityComparer The default equality comparer to use.
     */
    public function __construct(array|Traversable $initials = [], ?EqualityComparerInterface $equalityComparer = null)
    {
        parent::__construct([]);

        $this->equalityComparer = $equalityComparer ?? new DefaultEqualityComparer();
        if ($initials instanceof self) {
            $this->items = $initials->items;
        } else {
            foreach ($initials as $item) {
                $this->add($item);
            }
        }
    }

    /**
     * Adds an item only if it is not already in the set.
     *
     * @param T $item The item to add.
     *
     * @return bool `true` if the item was added; otherwise, `false`.
     */
    public function add(mixed $item): bool
    {
        if ($this->contains($item)) {
            return false;
        }
        $this->items[] = $item;

        return true;
    }

    #region extends Sequence

    /**
     * @inheritDoc
     */
    public function appendRange(array|Traversable $items): void
    {
        foreach ($items as $item) {
            $this->add($item);
        }
    }

    /**
     * @inheritDoc
     */
    public function insertRange(int $index, array|Traversable $items): void
    {
        $count = \count($this->items);
        if ($index < 0) {
            $index += $count;
        }
        if ($index < 0 || $index > $count) {
            throw new OutOfRangeException('The index is out of range.');
        }

        foreach ($items as $item) {
            if ($this->contains($item)) {
                continue;
            }

            \array_splice($this->items, $index, 0, [$item]);
            $index++;
        }
    }

    /**
     * @inheritDoc
     */
    protected function getDefaultEqualityComparer(): EqualityComparerInterface
    {
        return $this->equalityComparer;
    }

    #endregion extends Sequence
}
