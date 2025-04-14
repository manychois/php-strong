<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\Collections\Internal;

use ArrayAccess;
use ArrayIterator;
use Countable;
use IteratorAggregate;
use JsonSerializable;
use Manychois\PhpStrong\EqualInterface;
use Manychois\PhpStrong\EqualityComparerInterface;
use Manychois\PhpStrong\Registry;
use Traversable;
use ValueError;

/**
 * Represents a collection of keys and values powered by a native PHP array.
 *
 * @template TKey of int|string
 * @template TValue
 *
 * @template-implements ArrayAccess<TKey,TValue>
 * @template-implements IteratorAggregate<TKey,TValue>
 */
abstract class AbstractArray implements ArrayAccess, Countable, EqualInterface, IteratorAggregate, JsonSerializable
{
    /**
     * @var array<TKey,TValue>
     */
    protected array $source = [];

    /**
     * Checks if all values in the collection satisfy the given predicate.
     *
     * @param callable $predicate The predicate function to test each value.
     *
     * @return bool `true` if all values satisfy the predicate, `false` otherwise.
     *
     * @phpstan-param callable(TValue,TKey):bool $predicate
     */
    public function all(callable $predicate): bool
    {
        return \array_all($this->source, $predicate);
    }

    /**
     * Checks if any value in the collection satisfies the given predicate.
     *
     * @param callable $predicate The predicate function to test each value.
     *
     * @return bool `true` if any value satisfies the predicate, `false` otherwise.
     *
     * @phpstan-param callable(TValue,TKey):bool $predicate
     */
    public function any(callable $predicate): bool
    {
        return \array_any($this->source, $predicate);
    }

    /**
     * Clears the collection, removing all keys and values.
     */
    public function clear(): void
    {
        $this->source = [];
    }

    /**
     * Checks if the collection contains a specific value.
     *
     * @param TValue                         $value The value to search for.
     * @param EqualityComparerInterface|null $eq    The equality comparer to use for comparison.
     *
     * @return bool `true` if the value is found, `false` otherwise.
     */
    public function contains(mixed $value, EqualityComparerInterface|null $eq = null): bool
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
     * Executes a callback function for each value in the sequence.
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
     * Finds the first value in the collection that satisfies the given predicate.
     *
     * @param callable $predicate The predicate function to test each value.
     *
     * @return TValue|null The first value that satisfies the predicate, or `null` if no such value is found.
     *
     * @phpstan-param callable(TValue,TKey):bool $predicate
     */
    public function find(callable $predicate): mixed
    {
        return \array_find($this->source, $predicate);
    }

    /**
     * Reduces the collection to a single value using the provided callback function.
     *
     * @template TResult
     *
     * @param callable     $callback The reducer function to apply to each value.
     * @param TResult|null $initial  The initial value to start the reduction with.
     *
     * @return TResult The reduced value.
     *
     * @phpstan-param callable(TResult|null,TValue):TResult $callback
     */
    public function reduce(callable $callback, mixed $initial = null): mixed
    {
        if (\count($this->source) === 0) {
            throw new ValueError('Cannot reduce an empty collection.');
        }

        $accumulator = \array_reduce($this->source, $callback, $initial);
        \assert($accumulator !== null, 'The result of the reduction should not be null.');

        return $accumulator;
    }

    /**
     * Returns the collection as an array.
     *
     * @return array<TKey,TValue> The collection as an array.
     */
    public function toArray(): array
    {
        return $this->source;
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

    #region implements Countable

    /**
     * @inheritDoc
     */
    public function count(): int
    {
        return \count($this->source);
    }

    #endregion implements Countable

    #region implements EqualInterface

    /**
     * @inheritDoc
     */
    public function equals(mixed $other): bool
    {
        $eq = $this->getDefaultEqualityComparer();
        if (\is_array($other)) {
            if ($this->count() !== \count($other)) {
                return false;
            }

            foreach ($this->source as $k => $v) {
                if (!\array_key_exists($k, $other)) {
                    return false;
                }
                if (!$eq->equals($v, $other[$k])) {
                    return false;
                }
            }

            return true;
        }

        if ($other instanceof self) {
            if ($this->count() !== \count($other)) {
                return false;
            }

            foreach ($this->source as $k => $v) {
                if (!\array_key_exists($k, $other->source)) {
                    return false;
                }
                if (!$eq->equals($v, $other->source[$k])) {
                    return false;
                }
            }

            return true;
        }

        return false;
    }

    #endregion implements EqualInterface

    #region implements IteratorAggregate

    /**
     * @inheritDoc
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->source);
    }

    #endregion implements IteratorAggregate

    #region implements JsonSerializable

    /**
     * @inheritDoc
     */
    public function jsonSerialize(): mixed
    {
        return $this->source;
    }

    #endregion implements JsonSerializable
}
