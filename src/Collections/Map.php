<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\Collections;

use Manychois\PhpStrong\EqualityComparerInterface;

/**
 * Represents a mapping of keys to items.
 *
 * @template TKey
 * @template TItem
 *
 * @template-extends ReadonlyMap<TKey,TItem>
 */
class Map extends ReadonlyMap
{
    /**
     * Initializes a map from an iterable object.
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
        parent::__construct($collection, $policy, $comparer);
        $this->freezed = false;
    }

    /**
     * Returns a readonly map that wraps this map.
     *
     * @return ReadonlyMap<TKey,TItem> The readonly version of the map.
     */
    public function asReadonly(): ReadonlyMap
    {
        /** @var ReadonlyMap<TKey,TItem> $readonlyMap */
        $readonlyMap = new ReadonlyMap();
        // bypassing the one-by-one setting steps
        $readonlyMap->keyItems = $this->keyItems;
        $readonlyMap->lookup = $this->lookup;

        return $readonlyMap;
    }

    /**
     * Removes all items from the map.
     */
    public function clear(): void
    {
        $this->keyItems = [];
        $this->lookup = [];
    }

    /**
     * Sets the item associated with the specified key.
     *
     * @param TKey  $key
     * @param TItem $item
     *
     * @return TItem|null The replaced item, if any.
     */
    public function set(mixed $key, mixed $item): mixed
    {
        $previous = $this->internalSet($key, $item);

        return $previous?->item;
    }

    /**
     * Removes the item associated with the specified key.
     *
     * @param TKey $key The key of the item to remove.
     *
     * @return TItem|null The removed item, if any.
     */
    public function remove(mixed $key): mixed
    {
        $hash = $this->equalityComparer->hash($key);
        $indices = $this->lookup[$hash] ?? [];
        $count = \count($indices);

        if ($count === 0) {
            return null;
        }

        $found = null;
        $foundIndex = -1;
        foreach ($indices as $index) {
            $keyItem = $this->keyItems[$index];
            if ($this->equalityComparer->equals($keyItem->key, $key)) {
                $found = $keyItem->item;
                $foundIndex = $index;
                break;
            }
        }

        if ($found === null) {
            return null;
        }

        \array_splice($this->keyItems, $foundIndex, 1);
        $lookupKeys = \array_keys($this->lookup);
        foreach ($lookupKeys as $lookupKey) {
            $indices = $this->lookup[$lookupKey];
            $refreshedIndices = [];
            foreach ($indices as $index) {
                if ($index > $foundIndex) {
                    $refreshedIndices[] = $index - 1;
                } elseif ($index < $foundIndex) {
                    $refreshedIndices[] = $index;
                }
            }
            if (\count($refreshedIndices) > 0) {
                $this->lookup[$lookupKey] = $refreshedIndices;
            } else {
                unset($this->lookup[$lookupKey]);
            }
        }

        return $found;
    }
}
