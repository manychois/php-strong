<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\Collections;

use Iterator;
use LogicException;
use Manychois\PhpStrong\Collections\Internal\AbstractCollection;
use Manychois\PhpStrong\Collections\Internal\KeyItem;
use Manychois\PhpStrong\DefaultEqualityComparer;
use Manychois\PhpStrong\EqualityComparerInterface;
use OutOfBoundsException;

/**
 * Represents a read-only mapping of keys to items.
 *
 * @template TKey
 * @template TItem
 *
 * @template-extends AbstractCollection<TKey,TItem>
 */
class ReadonlyMap extends AbstractCollection
{
    public readonly DuplicateKeyPolicy $duplicateKeyPolicy;
    public readonly EqualityComparerInterface $equalityComparer;
    protected bool $freezed = false;
    /**
     * @var array<int,KeyItem<TKey,TItem>> The key-item pairs in insertion order.
     */
    protected array $keyItems = [];
    /**
     * @var array<array<int>> Lookup key to indices of matching key-item pairs.
     */
    protected array $lookup = [];

    /**
     * Initializes a readonly map from an iterable object.
     *
     * @param iterable<TKey,TItem>           $collection The iterable object.
     * @param DuplicateKeyPolicy             $policy     What to do when a duplicate key is found.
     * @param EqualityComparerInterface|null $comparer   The equality comparer to use for comparing keys.
     */
    public function __construct(
        iterable $collection = [],
        DuplicateKeyPolicy $policy = DuplicateKeyPolicy::ThrowException,
        EqualityComparerInterface $comparer = null
    ) {
        $this->duplicateKeyPolicy = $policy;
        $this->equalityComparer = $comparer ?? new DefaultEqualityComparer();
        foreach ($collection as $key => $item) {
            $this->internalSet($key, $item);
        }
        $this->freezed = true;
    }

    /**
     * Gets the item associated with the specified key.
     *
     * @param TKey $key The key of the item to get.
     *
     * @return TItem The item associated with the specified key.
     */
    public function get(mixed $key): mixed
    {
        $hash = $this->equalityComparer->hash($key);
        $indices = $this->lookup[$hash] ?? [];
        $count = \count($indices);

        if ($count === 0) {
            throw new OutOfBoundsException('The key is not found.');
        }

        foreach ($indices as $index) {
            $keyItem = $this->keyItems[$index];
            if ($this->equalityComparer->equals($keyItem->key, $key)) {
                return $keyItem->item;
            }
        }

        throw new OutOfBoundsException('The key is not found.');
    }

    /**
     * Gets the item associated with the specified key, or null if the key is not found.
     *
     * @param TKey $key The key of the item to get.
     *
     * @return TItem|null The item associated with the specified key, or null if the key is not found.
     */
    public function safeGet(mixed $key): mixed
    {
        $hash = $this->equalityComparer->hash($key);
        $indices = $this->lookup[$hash] ?? [];
        $count = \count($indices);

        if ($count === 0) {
            return null;
        }
        if ($count === 1) {
            return $this->keyItems[$indices[0]]->item;
        }

        foreach ($indices as $index) {
            $keyItem = $this->keyItems[$index];
            if ($this->equalityComparer->equals($keyItem->key, $key)) {
                return $keyItem->item;
            }
        }

        return null;
    }

    #region extends AbstractCollection

    public function count(): int
    {
        return \count($this->keyItems);
    }

    public function getIterator(): Iterator
    {
        foreach ($this->keyItems as $keyItem) {
            yield $keyItem->key => $keyItem->item;
        }
    }

    public function first(): mixed
    {
        if (\count($this->keyItems) === 0) {
            throw new OutOfBoundsException('The map is empty.');
        }

        return $this->keyItems[0]->item;
    }

    public function firstOrDefault(mixed $default = null): mixed
    {
        return \count($this->keyItems) > 0 ? $this->keyItems[0]->item : $default;
    }

    public function last(): mixed
    {
        $count = \count($this->keyItems);
        if ($count === 0) {
            throw new OutOfBoundsException('The map is empty.');
        }

        return $this->keyItems[$count - 1]->item;
    }

    public function lastOrDefault(mixed $default = null): mixed
    {
        $count = \count($this->keyItems);

        return $count > 0 ? $this->keyItems[$count - 1]->item : $default;
    }

    public function reverse(): CollectionInterface
    {
        $generator = function () {
            for ($i = \count($this->keyItems) - 1; $i >= 0; --$i) {
                $keyItem = $this->keyItems[$i];
                yield $keyItem->key => $keyItem->item;
            }
        };

        return new TraversableCollection($generator());
    }

    public function slice(int $skip, int $take): CollectionInterface
    {
        if ($skip < 0) {
            throw new OutOfBoundsException('The skip must be greater than or equal to zero.');
        }
        if ($take < 0) {
            throw new OutOfBoundsException('The take must be greater than or equal to zero.');
        }

        $generator = function () use ($skip, $take) {
            $count = \count($this->keyItems);
            $end = \min($skip + $take, $count);
            for ($i = $skip; $i < $end; ++$i) {
                $keyItem = $this->keyItems[$i];
                yield $keyItem->key => $keyItem->item;
            }
        };

        return new TraversableCollection($generator());
    }

    #endregion extends AbstractCollection

    /**
     * Sets the item associated with the specified key.
     *
     * @param TKey  $key  The key of the item to set.
     * @param TItem $item The item to set.
     *
     * @return KeyItem<TKey,TItem>|null The old key-item pair, if any.
     */
    protected function internalSet(mixed $key, mixed $item): ?KeyItem
    {
        if ($this->freezed) {
            throw new LogicException('The map is readonly.');
        }

        $hash = $this->equalityComparer->hash($key);
        $indices = $this->lookup[$hash] ?? [];
        $keyItem = new KeyItem($key, $item);
        if (\count($indices) > 0) {
            foreach ($indices as $index) {
                $iKeyItem = $this->keyItems[$index];
                if ($this->equalityComparer->equals($iKeyItem->key, $key)) {
                    if ($this->duplicateKeyPolicy === DuplicateKeyPolicy::ThrowException) {
                        throw new OutOfBoundsException('The key already exists.');
                    }
                    if ($this->duplicateKeyPolicy === DuplicateKeyPolicy::Ignore) {
                        return null;
                    }

                    $this->keyItems[$index] = $keyItem;

                    return $iKeyItem;
                }
            }
        }

        $index = \count($this->keyItems);
        $indices[] = $index;
        $this->keyItems[] = $keyItem;
        $this->lookup[$hash] = $indices;

        return null;
    }
}
