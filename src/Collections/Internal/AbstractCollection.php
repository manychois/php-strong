<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\Collections\Internal;

use Iterator;
use Manychois\PhpStrong\Collections\CollectionInterface;
use Manychois\PhpStrong\Collections\DuplicateKeyPolicy;
use Manychois\PhpStrong\Collections\Internal\KeyItemCollection;
use Manychois\PhpStrong\Collections\Internal\NextOnlyIterator;
use Manychois\PhpStrong\Collections\Map;
use Manychois\PhpStrong\Collections\ReadonlyMap;
use Manychois\PhpStrong\Collections\Sequence;
use Manychois\PhpStrong\Collections\TraversableCollection;
use Manychois\PhpStrong\ComparerInterface;
use Manychois\PhpStrong\DefaultEqualityComparer;
use Manychois\PhpStrong\EqualityComparerInterface;
use OutOfBoundsException;

/**
 * Common implementation of CollectionInterface.
 *
 * @template TKey
 * @template TItem
 *
 * @template-implements CollectionInterface<TKey,TItem>
 */
abstract class AbstractCollection implements CollectionInterface
{
    #region implements CollectionInterface

    public function count(): int
    {
        return \iterator_count($this->getIterator());
    }

    /**
     * Returns an external iterator.
     *
     * @return Iterator<TKey,TItem> An external iterator.
     */
    abstract public function getIterator(): Iterator;

    public function all(callable $predicate): bool
    {
        foreach ($this->getIterator() as $key => $item) {
            if (!$predicate($item, $key)) {
                return false;
            }
        }

        return true;
    }

    public function chunks(int $size): CollectionInterface
    {
        if ($size <= 0) {
            throw new OutOfBoundsException('The size must be greater than zero.');
        }

        $generator = function () use ($size) {
            $i = 0;
            $from = 0;
            $to = $size;
            $iterator = $this->getIterator();
            $iterator->rewind();
            while ($iterator->valid()) {
                while ($i < $from) {
                    $iterator->next();
                    $i++;
                    if (!$iterator->valid()) {
                        break 2;
                    }
                }
                $nextOnly = new NextOnlyIterator($iterator, $size);
                yield $nextOnly;

                $i += $nextOnly->getCounter();
                $from = $to;
                $to += $size;
            }
        };

        return new TraversableCollection($generator());
    }

    public function combine(iterable ...$collections): CollectionInterface
    {
        $generator = static function () use ($collections) {
            foreach ($collections as $collection) {
                foreach ($collection as $key => $item) {
                    yield $key => $item;
                }
            }
        };

        return new TraversableCollection($generator());
    }

    public function contains(mixed $needle, ?EqualityComparerInterface $comparer = null): bool
    {
        $comparer ??= new DefaultEqualityComparer();
        foreach ($this->getIterator() as $item) {
            if ($comparer->equals($item, $needle)) {
                return true;
            }
        }

        return false;
    }

    public function diff(iterable $other, ?EqualityComparerInterface $comparer = null): CollectionInterface
    {
        $comparer ??= new DefaultEqualityComparer();
        $generator = function () use ($other, $comparer) {
            foreach ($this->getIterator() as $key => $item) {
                foreach ($other as $otherItem) {
                    if ($comparer->equals($item, $otherItem)) {
                        continue 2;
                    }
                }
                yield $key => $item;
            }
        };

        return new TraversableCollection($generator());
    }

    public function distinct(?EqualityComparerInterface $comparer = null): CollectionInterface
    {
        $comparer ??= new DefaultEqualityComparer();
        $generator = function () use ($comparer) {
            $set = [];
            foreach ($this->getIterator() as $key => $item) {
                foreach ($set as $uniqueItem) {
                    if ($comparer->equals($uniqueItem, $item)) {
                        continue 2;
                    }
                }
                yield $key => $item;
                $set[] = $item;
            }
        };

        return new TraversableCollection($generator());
    }

    public function each(callable $action): void
    {
        foreach ($this->getIterator() as $key => $item) {
            $return = $action($item, $key);
            if ($return === false) {
                break;
            }
        }
    }

    public function find(callable $predicate): mixed
    {
        foreach ($this->getIterator() as $key => $item) {
            if ($predicate($item, $key)) {
                return $item;
            }
        }

        return null;
    }

    public function findIndex(callable $predicate): int
    {
        $i = 0;
        foreach ($this->getIterator() as $key => $item) {
            if ($predicate($item, $key)) {
                return $i;
            }
            $i++;
        }

        return -1;
    }

    public function first(): mixed
    {
        foreach ($this->getIterator() as $item) {
            return $item;
        }

        throw new OutOfBoundsException('The collection is empty.');
    }

    public function firstOrDefault(mixed $default = null): mixed
    {
        foreach ($this->getIterator() as $item) {
            return $item;
        }

        return $default;
    }

    public function groupBy(callable $keySelector, ?EqualityComparerInterface $comparer = null): ReadonlyMap
    {
        $comparer ??= new DefaultEqualityComparer();
        /** @var Map<TKey,KeyItemCollection<TKey,TItem>> $map */
        $map = new Map([], DuplicateKeyPolicy::ThrowException, $comparer);
        foreach ($this->getIterator() as $key => $item) {
            $groupKey = $keySelector($item, $key);
            if ($groupKey === null) {
                throw new OutOfBoundsException('The group key cannot be null.');
            }
            $collection = $map->safeGet($groupKey);
            if ($collection === null) {
                $collection = new KeyItemCollection();
                $map->set($groupKey, $collection);
            }
            $collection->add(new KeyItem($key, $item));
        }

        foreach ($map as $collection) {
            $collection->freeze();
        }

        return $map->asReadonly(); // @phpstan-ignore-line
    }

    public function indexOf(mixed $needle, ?EqualityComparerInterface $comparer = null): int
    {
        $comparer ??= new DefaultEqualityComparer();
        $i = 0;
        foreach ($this->getIterator() as $item) {
            if ($comparer->equals($item, $needle)) {
                return $i;
            }
            $i++;
        }

        return -1;
    }

    public function intersect(iterable $other, ?EqualityComparerInterface $comparer = null): CollectionInterface
    {
        $comparer ??= new DefaultEqualityComparer();
        /** @var KeyItemCollection<TKey,TItem> $distinct */
        $distinct = new KeyItemCollection($this->distinct($comparer));
        $other = new KeyItemCollection($other);

        $generator = static function () use ($distinct, $other, $comparer) {
            foreach ($distinct as $key => $item) {
                if ($other->contains($item, $comparer)) {
                    yield $key => $item;
                }
            }
        };

        return new TraversableCollection($generator());
    }

    public function last(): mixed
    {
        $found = false;
        $last = null;
        foreach ($this->getIterator() as $item) {
            $found = true;
            $last = $item;
        }

        if (!$found) {
            throw new OutOfBoundsException('The collection is empty.');
        }

        return $last;
    }

    public function lastOrDefault(mixed $default = null): mixed
    {
        $found = false;
        $last = null;
        foreach ($this->getIterator() as $item) {
            $found = true;
            $last = $item;
        }

        return $found ? $last : $default;
    }

    public function map(callable $selector): CollectionInterface
    {
        $generator = function () use ($selector) {
            foreach ($this->getIterator() as $key => $item) {
                yield $key => $selector($item, $key);
            }
        };

        return new TraversableCollection($generator());
    }

    public function orderBy(ComparerInterface $comparer): CollectionInterface
    {
        /** @var array<KeyItem<TKey,TItem>> $keyItems */
        $keyItems = [];
        foreach ($this->getIterator() as $key => $item) {
            $keyItems[] = new KeyItem($key, $item);
        }

        \usort($keyItems, static function (KeyItem $a, KeyItem $b) use ($comparer) {
            return $comparer->compare($a->item, $b->item);
        });
        /** @var KeyItemCollection<TKey,TItem> $collection */
        $collection = new KeyItemCollection();
        $collection->reset($keyItems);
        $collection->freeze();

        return $collection;
    }

    public function reduce(callable $reducer, mixed $initial): mixed
    {
        $accumulator = $initial;
        foreach ($this->getIterator() as $key => $item) {
            $accumulator = $reducer($accumulator, $item, $key);
        }

        return $accumulator;
    }

    public function reverse(): CollectionInterface
    {
        $generator = function () {
            $keyItems = [];
            foreach ($this->getIterator() as $key => $item) {
                \array_unshift($keyItems, new KeyItem($key, $item));
            }
            foreach ($keyItems as $keyItem) {
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
            $i = 0;
            foreach ($this->getIterator() as $key => $item) {
                if ($i >= $skip) {
                    yield $key => $item;
                    $take--;
                    if ($take === 0) {
                        break;
                    }
                }
                $i++;
            }
        };

        return new TraversableCollection($generator());
    }

    public function some(callable $predicate): bool
    {
        foreach ($this->getIterator() as $key => $item) {
            if ($predicate($item, $key)) {
                return true;
            }
        }

        return false;
    }

    public function swapKeyItem(): CollectionInterface
    {
        $generator = function () {
            foreach ($this->getIterator() as $key => $item) {
                yield $item => $key;
            }
        };

        return new TraversableCollection($generator());
    }

    public function transformKey(callable $selector): CollectionInterface
    {
        $generator = function () use ($selector) {
            foreach ($this->getIterator() as $key => $item) {
                $newKey = $selector($item, $key);
                yield $newKey => $item;
            }
        };

        return new TraversableCollection($generator());
    }

    public function toMap(
        DuplicateKeyPolicy $policy = DuplicateKeyPolicy::ThrowException,
        EqualityComparerInterface $comparer = null
    ): Map {
        return new Map($this, $policy, $comparer);
    }

    public function toSequence(): Sequence
    {
        return new Sequence($this); // @phpstan-ignore-line
    }

    public function union(iterable $other, ?EqualityComparerInterface $comparer = null): CollectionInterface
    {
        $comparer ??= new DefaultEqualityComparer();
        /** @var KeyItemCollection<TKey,TItem> $distinct */
        $distinct = new KeyItemCollection($this->distinct($comparer));
        $generator = static function () use ($distinct, $other) {
            foreach ($distinct as $key => $item) {
                yield $key => $item;
            }
            foreach ($other as $key => $item) {
                if ($distinct->contains($item)) {
                    continue;
                }
                yield $key => $item;
            }
        };

        return new TraversableCollection($generator());
    }

    public function where(callable $predicate): CollectionInterface
    {
        $generator = function () use ($predicate) {
            foreach ($this->getIterator() as $key => $item) {
                if ($predicate($item, $key)) {
                    yield $key => $item;
                }
            }
        };

        return new TraversableCollection($generator());
    }

    #endregion implements CollectionInterface
}
