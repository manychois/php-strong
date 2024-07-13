<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\Collections\Internal;

use Iterator;
use LogicException;

/**
 * Represents an add-only collection of key-item pairs.
 *
 * @internal
 *
 * @template TKey
 *
 * @template TItem
 *
 * @template-extends AbstractCollection<TKey,TItem>
 */
class KeyItemCollection extends AbstractCollection
{
    private bool $freezed = false;
    /**
     * @var array<KeyItem<TKey,TItem>>
     */
    private array $keyItems = [];

    /**
     * Initializes a collection of key-item pairs.
     *
     * @param iterable<TKey,TItem> $collection The key-item pairs to initialize the collection.
     */
    public function __construct(iterable $collection = [])
    {
        foreach ($collection as $key => $item) {
            $this->keyItems[] = new KeyItem($key, $item);
        }
    }

    /**
     * Adds a key-item pair to the collection.
     *
     * @param KeyItem<TKey,TItem> $keyItem The key-item pair to add.
     */
    public function add(KeyItem $keyItem): void
    {
        if ($this->freezed) {
            throw new LogicException('The collection is readonly.');
        }
        $this->keyItems[] = $keyItem;
    }

    /**
     * Prevents further modification of the collection.
     */
    public function freeze(): void
    {
        $this->freezed = true;
    }

    /**
     * Resets the collection with the specified key-item pairs.
     *
     * @param array<KeyItem<TKey,TItem>> $keyItems The key-item pairs in the collection.
     */
    public function reset(array $keyItems): void
    {
        if ($this->freezed) {
            throw new LogicException('The collection is readonly.');
        }
        $this->keyItems = $keyItems;
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

    #endregion extends AbstractCollection
}
