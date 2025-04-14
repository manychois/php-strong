<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\Collections;

use ArrayIterator;
use Iterator;
use IteratorIterator;
use JsonSerializable;
use Manychois\PhpStrong\EqualityComparerInterface;
use Manychois\PhpStrong\KeyValuePair;
use Manychois\PhpStrong\Registry;
use OutOfBoundsException;
use Traversable;
use ValueError;

/**
 * Represents a collection of keys and values that are generated on demand from a given source.
 *
 * @template TKey
 * @template TValue
 *
 * @template-implements Iterator<TKey,TValue>
 */
class Collection implements Iterator, JsonSerializable
{
    /**
     * @var Iterator<TKey,TValue>
     */
    private readonly Iterator $source;

    /**
     * Converts an array of key-value pairs into a collection.
     *
     * @template TPairKey
     * @template TPairValue
     *
     * @param iterable<KeyValuePair<TPairKey,TPairValue>> $keyValuePairs The array of key-value pairs to convert.
     *
     * @return self<TPairKey,TPairValue> A collection of key-value pairs.
     */
    public static function fromKeyValuePairs(iterable $keyValuePairs): self
    {
        $generator = static function () use ($keyValuePairs) {
            foreach ($keyValuePairs as $keyValuePair) {
                yield $keyValuePair->key => $keyValuePair->value;
            }
        };

        return new self($generator());
    }

    /**
     * @param array<TValue>|Traversable<TKey,TValue> $source The source collection to enumerate.
     */
    public function __construct(array|Traversable $source)
    {
        if (\is_array($source)) {
            $this->source = new ArrayIterator($source);
        } else {
            $this->source = new IteratorIterator($source);
        }
    }

    /**
     * Checks if all items in the collection satisfy the given predicate.
     *
     * @param callable $predicate The predicate function to test each item.
     *
     * @return bool `true` if all items satisfy the predicate, `false` otherwise.
     *
     * @phpstan-param callable(TValue,TKey):bool $predicate
     */
    public function all(callable $predicate): bool
    {
        foreach ($this->source as $k => $v) {
            if (!$predicate($v, $k)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Checks if any item in the collection satisfies the given predicate.
     *
     * @param callable $predicate The predicate function to test each item.
     *
     * @return bool `true` if any item satisfies the predicate, `false` otherwise.
     *
     * @phpstan-param callable(TValue,TKey):bool $predicate
     */
    public function any(callable $predicate): bool
    {
        foreach ($this->source as $k => $v) {
            if ($predicate($v, $k)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Returns the first value that has the specified key.
     *
     * @param TKey $key The key to search for.
     *
     * @return TValue The value associated with the specified key.
     */
    public function at(mixed $key): mixed
    {
        foreach ($this->source as $k => $v) {
            if ($k === $key) {
                return $v;
            }
        }

        throw new OutOfBoundsException('Key not found in collection.');
    }

    /**
     * Splits the collection into chunks of the specified length.
     * The last chunk may contain fewer items.
     *
     * @param int $length The length of each chunk.
     *
     * @return self<int,self<TKey,TValue>> A collection of chunks, each containing a sub-collection of items.
     */
    public function chunk(int $length): self
    {
        $generator = function () use ($length) {
            $keyValuePairs = [];
            $count = 0;
            foreach ($this->source as $k => $v) {
                $keyValuePairs[] = new KeyValuePair($k, $v);
                $count++;
                if ($count !== $length) {
                    continue;
                }

                yield self::fromKeyValuePairs($keyValuePairs);

                $keyValuePairs = [];
                $count = 0;
            }
            if ($count <= 0) {
                return;
            }

            yield self::fromKeyValuePairs($keyValuePairs);
        };

        return new self($generator());
    }

    /**
     * Checks if the collection contains a specific value.
     *
     * @param TValue                         $value The value to search for.
     * @param EqualityComparerInterface|null $eq    The equality comparer to use for comparison.
     *
     * @return bool `true` if the value is found, `false` otherwise.
     */
    public function contains(mixed $value, ?EqualityComparerInterface $eq = null): bool
    {
        $eq ??= $this->getDefaultEqualityComparer();
        foreach ($this->source as $v) {
            if ($eq->equals($v, $value)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Returns a new collection with distinct values.
     *
     * @param EqualityComparerInterface|null $eq The equality comparer to use for comparison.
     *
     * @return self<TKey,TValue> A new collection with distinct values.
     */
    public function distinct(?EqualityComparerInterface $eq = null): self
    {
        $eq ??= $this->getDefaultEqualityComparer();
        $generator = function () use ($eq) {
            $seen = [];
            foreach ($this->source as $k => $v) {
                foreach ($seen as $s) {
                    if ($eq->equals($s, $v)) {
                        continue 2;
                    }
                }

                $seen[] = $v;

                yield $k => $v;
            }
        };

        return new self($generator());
    }

    /**
     * Executes a callback for each value in the collection.
     *
     * @param callable $callback The callback function to execute for each value.
     *                           Returns `true` to stop the iteration.
     *
     * @phpstan-param callable(TValue,TKey):mixed $callback
     */
    public function each(callable $callback): void
    {
        foreach ($this->source as $k => $v) {
            $result = $callback($v, $k);
            if ($result === true) {
                return;
            }
        }
    }

    /**
     * Filters the collection based on a predicate.
     *
     * @param callable $predicate The predicate function to filter values.
     *
     * @return self<TKey,TValue> A new collection containing the filtered values.
     *
     * @phpstan-param callable(TValue,TKey):bool $predicate
     */
    public function filter(callable $predicate): self
    {
        $generator = function () use ($predicate) {
            foreach ($this->source as $k => $v) {
                if (!$predicate($v, $k)) {
                    continue;
                }

                yield $k => $v;
            }
        };

        return new self($generator());
    }

    /**
     * Finds the first value in the collection that satisfies the given predicate.
     *
     * @param callable $predicate The predicate function to test each value.
     *
     * @return TValue|null The first value that satisfies the predicate, or `null` if none is found.
     *
     * @phpstan-param callable(TValue,TKey):bool $predicate
     */
    public function find(callable $predicate): mixed
    {
        return $this->findKeyValuePair($predicate)?->value;
    }

    /**
     * Finds the first key in the collection that satisfies the given predicate.
     *
     * @param callable $predicate The predicate function to test each value.
     *
     * @return TKey|null The first key that satisfies the predicate, or `null` if none is found.
     *
     * @phpstan-param callable(TValue,TKey):bool $predicate
     */
    public function findKey(callable $predicate): mixed
    {
        return $this->findKeyValuePair($predicate)?->key;
    }

    /**
     * Finds the first key-value pair in the collection that satisfies the given predicate.
     *
     * @param callable $predicate The predicate function to test each value.
     *
     * @return KeyValuePair<TKey,TValue>|null The first key-value pair that satisfies the predicate, or `null` if none
     * is found.
     *
     * @phpstan-param callable(TValue,TKey):bool $predicate
     */
    public function findKeyValuePair(callable $predicate): KeyValuePair|null
    {
        foreach ($this->source as $k => $v) {
            if ($predicate($v, $k)) {
                return new KeyValuePair($k, $v);
            }
        }

        return null;
    }

    /**
     * Returns the keys of the collection as a new collection.
     *
     * @return self<non-negative-int,TKey> A new collection containing the keys of the original collection.
     */
    public function keys(): self
    {
        $generator = function () {
            foreach ($this->source as $k => $v) {
                yield $k;
            }
        };

        return new self($generator());
    }

    /**
     * Returns the first `length` items in the collection.
     *
     * @param int $length The number of items to take.
     *
     * @return self<TKey,TValue> A new collection containing the first `length` items.
     */
    public function limit(int $length): self
    {
        $generator = function () use ($length) {
            $count = 0;
            foreach ($this->source as $k => $v) {
                if ($count >= $length) {
                    break;
                }

                yield $k => $v;

                $count++;
            }
        };

        return new self($generator());
    }

    /**
     * Maps each value in the collection to a new value using the provided callback.
     *
     * @template TNewValue
     *
     * @param callable $callback The callback function to transform each value.
     *
     * @return self<TKey,TNewValue> A new collection with the transformed values.
     *
     * @phpstan-param callable(TValue,TKey):TNewValue $callback
     */
    public function map(callable $callback): self
    {
        $generator = function () use ($callback) {
            foreach ($this->source as $k => $v) {
                yield $k => $callback($v, $k);
            }
        };

        return new self($generator());
    }

    /**
     * Maps each value in the collection to a new key-value pair using the provided callback.
     *
     * @template TNewKey
     * @template TNewValue
     *
     * @param callable $callback The callback function to transform each key and value into a new key-value pair.
     *
     * @return self<TNewKey,TNewValue> A new collection with the transformed key-value pairs.
     *
     * @phpstan-param callable(TValue,TKey):KeyValuePair<TNewKey,TNewValue> $callback
     */
    public function mapKeyValue(callable $callback): self
    {
        $generator = function () use ($callback) {
            foreach ($this->source as $k => $v) {
                $pair = $callback($v, $k);

                yield $pair->key => $pair->value;
            }
        };

        return new self($generator());
    }

    /**
     * Merges this collection with one or more collections.
     *
     * @param iterable<TKey,TValue> ...$others The other collections to merge with.
     *
     * @return self<TKey,TValue> A new collection containing the merged keys and values.
     */
    public function merge(iterable ...$others): self
    {
        $generator = function () use ($others) {
            foreach ($this->source as $k => $v) {
                yield $k => $v;
            }
            foreach ($others as $other) {
                foreach ($other as $k => $v) {
                    yield $k => $v;
                }
            }
        };

        return new self($generator());
    }

    /**
     * Skips the first `length` items in the collection.
     *
     * @param int $length The number of items to skip.
     *
     * @return self<TKey,TValue> A new collection containing the remaining items.
     */
    public function skip(int $length): self
    {
        $generator = function () use ($length) {
            $count = 0;
            foreach ($this->source as $k => $v) {
                if ($count < $length) {
                    $count++;

                    continue;
                }

                yield $k => $v;
            }
        };

        return new self($generator());
    }

    /**
     * Reduces the collection to a single value using the provided callback.
     *
     * @template TResult
     *
     * @param callable     $callback The callback function to reduce the collection.
     * @param TResult|null $initial  The initial value for the reduction.
     *
     * @return TResult The reduced value.
     *
     * @phpstan-param callable(TResult|null,TValue,TKey):TResult $callback
     */
    public function reduce(callable $callback, mixed $initial = null): mixed
    {
        $isEmpty = true;
        $accumulator = $initial;
        foreach ($this->source as $k => $v) {
            $isEmpty = false;
            $accumulator = $callback($accumulator, $v, $k);
        }

        if ($isEmpty) {
            throw new ValueError('Cannot reduce an empty sequence.');
        }

        \assert($accumulator !== null, 'The result of the reduction should not be null.');

        return $accumulator;
    }

    /**
     * Returns the collection as an array.
     *
     * @return array<TValue> The collection as an array.
     */
    public function toArray(): array
    {
        $result = [];
        $count = 0;
        foreach ($this->source as $key => $value) {
            if (\is_int($key) || \is_string($key)) {
                $result[$key] = $value;
            } else {
                $result[$count] = $value;
                $count++;
            }
        }

        return $result;
    }

    /**
     * Returns the collection as a sequence of key-value pairs.
     *
     * @return Sequence<KeyValuePair<TKey,TValue>> The collection as a sequence of key-value pairs.
     */
    public function toKeyValuePairs(): Sequence
    {
        $result = [];
        foreach ($this->source as $key => $value) {
            $result[] = new KeyValuePair($key, $value);
        }

        return new Sequence($result);
    }

    /**
     * Returns the default equality comparer for this collection.
     *
     * @return EqualityComparerInterface The default equality comparer.
     */
    protected function getDefaultEqualityComparer(): EqualityComparerInterface
    {
        return Registry::getEqualityComparer();
    }

    #region implements Iterator

    /**
     * @inheritDoc
     *
     * @return TValue
     */
    public function current(): mixed
    {
        return $this->source->current();
    }

    /**
     * @inheritDoc
     */
    public function next(): void
    {
        $this->source->next();
    }

    /**
     * @inheritDoc
     *
     * @return TKey
     */
    public function key(): mixed
    {
        return $this->source->key();
    }

    /**
     * @inheritDoc
     */
    public function valid(): bool
    {
        return $this->source->valid();
    }

    /**
     * @inheritDoc
     */
    public function rewind(): void
    {
        $this->source->rewind();
    }

    #endregion implements Iterator

    #region implements JsonSerializable

    public function jsonSerialize(): mixed
    {
        return $this->toArray();
    }

    #endregion implements JsonSerializable
}
