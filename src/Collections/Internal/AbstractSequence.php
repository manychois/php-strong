<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\Collections\Internal;

use Countable;
use Generator;
use IteratorAggregate;
use Manychois\PhpStrong\Collections\ReadonlySequence;
use Manychois\PhpStrong\Defaults\DefaultEqualityComparer;
use OutOfRangeException;
use Stringable;
use TypeError;

/**
 * Represents the base class for a sequence of items.
 *
 * @template T
 *
 * @template-implements IteratorAggregate<non-negative-int,T>
 */
abstract class AbstractSequence implements Countable, IteratorAggregate
{
    /**
     * @var array<non-negative-int,T>
     */
    protected array $items;

    /**
     * Initializes a new instance of the sequence class.
     *
     * @param iterable<T> $initial The initial items of the sequence.
     */
    public function __construct(iterable $initial = [])
    {
        if (\is_array($initial)) {
            if (\array_is_list($initial)) {
                $this->items = $initial;
            } else {
                $this->items = \array_values($initial);
            }
        } elseif ($initial instanceof self) {
            $this->items = $initial->items;
        } else {
            $this->items = \iterator_to_array($initial, false);
        }
    }

    /**
     * Returns a read-only version of the sequence.
     *
     * @return ReadonlySequence<T> The read-only version of the sequence.
     */
    public function asReadonly(): ReadonlySequence
    {
        if ($this instanceof ReadonlySequence) {
            return $this;
        }

        // @phpstan-ignore return.type
        return new ReadonlySequence($this->items);
    }

    /**
     * Determines whether the sequence contains a specific item.
     *
     * @param T $target The item to locate in the sequence.
     *
     * @return bool true if the sequence contains the item; otherwise, false.
     */
    final public function contains(mixed $target): bool
    {
        $found = \in_array($target, $this->items, true);
        if (!$found) {
            $eq = new DefaultEqualityComparer();
            foreach ($this->items as $value) {
                if ($eq->equals($value, $target)) {
                    $found = true;
                    break;
                }
            }
        }

        return $found;
    }

    /**
     * Gets the item at the specified index.
     * If the index is negative, it is counted from the end of the sequence.
     * If the index is out of range, an `OutOfRangeException` is thrown.
     *
     * @param int $index The zero-based index of the item to get.
     *
     * @return T The item at the specified index.
     */
    final public function get(int $index): mixed
    {
        if ($index < 0) {
            $index += \count($this->items);
        }
        if ($index < 0 || $index >= \count($this->items)) {
            throw new OutOfRangeException('The index is out of range.');
        }

        return $this->items[$index];
    }

    /**
     * Gets the item as an integer.
     * If the index is negative, it is counted from the end of the sequence.
     * If the index is out of range, an `OutOfRangeException` is thrown.
     * If the item is not an integer, or cannot be converted to an integer, a `TypeError` is thrown.
     *
     * @param int $index The zero-based index of the item to get.
     *
     * @return int The item at the specified index as an integer.
     */
    final public function getInt(int $index): int
    {
        $value = $this->get($index);
        if (\is_int($value)) {
            return $value;
        }

        if (\is_string($value) && !\is_numeric($value)) {
            throw new TypeError('Value is not a numeric string.');
        }
        if (\is_scalar($value) || $value === null) {
            return \intval($value);
        }

        throw new TypeError(\sprintf('Value type is %s.', \get_debug_type($value)));
    }

    /**
     * Gets the item as a string.
     * If the index is negative, it is counted from the end of the sequence.
     * If the index is out of range, an `OutOfRangeException` is thrown.
     * If the item is not a string, or cannot be converted to a string, a `TypeError` is thrown.
     *
     * @param int $index The zero-based index of the item to get.
     *
     * @return string The item at the specified index as a string.
     */
    final public function getString(int $index): string
    {
        $value = $this->get($index);
        if (\is_string($value)) {
            return $value;
        }
        if (\is_scalar($value) || $value instanceof Stringable) {
            return \strval($value);
        }
        if ($value === null) {
            return '';
        }

        throw new TypeError(\sprintf('Value type is %s.', \get_debug_type($value)));
    }

    /**
     * Gets the item as the specified class object.
     * If the index is negative, it is counted from the end of the sequence.
     * If the index is out of range, an `OutOfRangeException` is thrown.
     * If the item is not an instance of the specified class, a `TypeError` is thrown.
     *
     * @template TObject of object
     *
     * @param int                   $index The zero-based index of the item to get.
     * @param class-string<TObject> $class The class name of the object.
     *
     * @return TObject The item at the specified index as an object.
     */
    final public function getObject(int $index, string $class): object
    {
        $value = $this->get($index);
        if ($value instanceof $class) {
            return $value;
        }

        throw new TypeError(\sprintf('Value type is %s.', \get_debug_type($value)));
    }

    #region implements Countable

    /**
     * Returns the number of items in the sequence.
     *
     * @return non-negative-int The number of items in the sequence.
     */
    final public function count(): int
    {
        return \count($this->items);
    }

    #endregion implements Countable

    #region implements IteratorAggregate

    /**
     * Returns an iterator that iterates through the sequence.
     *
     * @return Generator<non-negative-int,T> An iterator that iterates through the sequence.
     */
    final public function getIterator(): Generator
    {
        foreach ($this->items as $index => $item) {
            yield $index => $item;
        }
    }

    #endregion implements IteratorAggregate
}
