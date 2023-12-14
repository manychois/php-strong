<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\Collections;

use ArrayAccess;
use Countable;
use InvalidArgumentException;
use IteratorAggregate;
use Traversable;

/**
 * Represents a strongly-typed collection that can be individually accessed by index.
 *
 * @template TItem
 *
 * @implements ArrayAccess<int, TItem>
 * @implements IteratorAggregate<int, TItem>
 */
class StrongList implements ArrayAccess, Countable, IteratorAggregate
{
    public readonly string $typeConstraint;
    /** @var array<int, TItem> */
    private array $items = [];

    #region factory methods

    /**
     * Creates a new list of boolean values.
     *
     * @return self<bool> The new list of boolean values.
     */
    public static function ofBool(): self
    {
        return new self('bool');
    }

    /**
     * Creates a new list of float values.
     *
     * @return self<float> The new list of float values.
     */
    public static function ofFloat(): self
    {
        return new self('float');
    }

    /**
     * Creates a new list of integer values.
     *
     * @return self<int> The new list of integer values.
     */
    public static function ofInt(): self
    {
        return new self('int');
    }

    /**
     * Creates a new list of objects.
     *
     * @template T of object
     *
     * @param class-string<T> $class The class name of the items.
     *
     * @return self<T> The new list of objects.
     */
    public static function ofObject(string $class): self
    {
        return new self($class);
    }

    /**
     * Creates a new list of string values.
     *
     * @return self<string> The new list of string values.
     */
    public static function ofString(): self
    {
        return new self('string');
    }

    #endregion factory methods

    /**
     * Creates a strongly-typed list.
     *
     * @param string $typeConstraint The type constraint of the items.
     */
    private function __construct(string $typeConstraint)
    {
        $this->typeConstraint = $typeConstraint;
    }

    #region implements ArrayAccess

    /**
     * @inheritDoc
     */
    public function offsetExists(mixed $offset): bool
    {
        if (!\is_int($offset)) {
            return false;
        }

        return isset($this->items[$offset]);
    }

    /**
     * @inheritDoc
     */
    public function offsetGet(mixed $offset): mixed
    {
        if (!\is_int($offset)) {
            throw new InvalidArgumentException('The offset must be an integer.');
        }
        if ($offset < 0) {
            throw new InvalidArgumentException('The offset must be greater than or equal to 0.');
        }
        $count = \count($this->items);
        if ($offset >= $count) {
            throw new InvalidArgumentException(\sprintf('The offset cannot be greater than or equal to %d.', $count));
        }

        return $this->items[$offset];
    }

    /**
     * @inheritDoc
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        if (!$this->matchConstraint($value)) {
            throw new InvalidArgumentException(\sprintf('The value must be of type %s.', $this->typeConstraint));
        }
        if ($offset === null) {
            $this->items[] = $value;
        } else {
            if (!\is_int($offset)) {
                throw new InvalidArgumentException('The offset must be an integer.');
            }
            if ($offset < 0) {
                throw new InvalidArgumentException('The offset must be greater than or equal to 0.');
            }
            $count = \count($this->items);
            if ($offset > $count) {
                throw new InvalidArgumentException(\sprintf('The offset cannot be greater than %d.', $count));
            }
            $this->items[$offset] = $value;
        }
    }

    /**
     * @inheritDoc
     */
    public function offsetUnset(mixed $offset): void
    {
        if (\is_int($offset) && isset($this->items[$offset])) {
            \array_splice($this->items, $offset, 1);
        }
    }

    #endregion implements ArrayAccess

    #region implements Countable

    /**
     * @inheritDoc
     */
    public function count(): int
    {
        return \count($this->items);
    }

    #endregion implements Countable

    #region implements IteratorAggregate

    /**
     * @inheritDoc
     *
     * @return Traversable<int, TItem>
     */
    public function getIterator(): Traversable
    {
        foreach ($this->items as $item) {
            yield $item;
        }
    }

    #endregion implements IteratorAggregate

    /**
     * Adds an item to the list.
     *
     * @param TItem $item The item to add.
     */
    public function add(mixed $item): void
    {
        if (!$this->matchConstraint($item)) {
            throw new InvalidArgumentException(\sprintf('The item must be of type %s.', $this->typeConstraint));
        }
        $this->items[] = $item;
    }

    /**
     * Removes all items from the list.
     */
    public function clear(): void
    {
        $this->items = [];
    }

    /**
     * Determines whether the list contains a specific item.
     *
     * @param TItem $item The item to locate in the list.
     *
     * @return bool true if item is found in the list; otherwise, false.
     */
    public function contains(mixed $item): bool
    {
        if (!$this->matchConstraint($item)) {
            throw new InvalidArgumentException(\sprintf('The item must be of type %s.', $this->typeConstraint));
        }

        return \in_array($item, $this->items, true);
    }

    /**
     * Determines the index of a specific item in the list.
     *
     * @param TItem $item The item to locate in the list.
     *
     * @return int The index of the item if found in the list; otherwise, -1.
     */
    public function indexOf(mixed $item): int
    {
        if (!$this->matchConstraint($item)) {
            throw new InvalidArgumentException(\sprintf('The item must be of type %s.', $this->typeConstraint));
        }
        $i = \array_search($item, $this->items, true);
        if ($i === false) {
            return -1;
        }

        return $i;
    }

    /**
     * Inserts an item to the list at the specified index.
     *
     * @param int   $index The zero-based index at which item should be inserted.
     * @param TItem $item  The item to insert into the list.
     */
    public function insert(int $index, mixed $item): void
    {
        if ($index < 0) {
            throw new InvalidArgumentException('The index must be greater than or equal to zero.');
        }
        if (!$this->matchConstraint($item)) {
            throw new InvalidArgumentException(\sprintf('The item must be of type %s.', $this->typeConstraint));
        }
        $count = \count($this->items);
        if ($index > $count) {
            throw new InvalidArgumentException(\sprintf('The index cannot be greater than %d.', $count));
        }
        \array_splice($this->items, $index, 0, $item);
    }

    /**
     * Gets the item at the specified index.
     *
     * @param int $index The zero-based index of the item to get.
     *
     * @return TItem The item at the specified index.
     */
    public function item(int $index): mixed
    {
        if ($index < 0) {
            throw new InvalidArgumentException('The index must be greater than or equal to zero.');
        }
        $count = \count($this->items);
        if ($index >= $count) {
            throw new InvalidArgumentException(\sprintf('The index cannot be greater than or equal to %d.', $count));
        }

        return $this->items[$index];
    }

    /**
     * Determines whether the specified value matches the type constraint.
     *
     * @param mixed $value The value to check.
     *
     * @return bool true if the value matches the type constraint; otherwise, false.
     */
    public function matchConstraint(mixed $value): bool
    {
        return match ($this->typeConstraint) {
            'bool' => \is_bool($value),
            'float' => \is_float($value),
            'int' => \is_int($value),
            'string' => \is_string($value),
            default => $value instanceof $this->typeConstraint,
        };
    }

    /**
     * Removes the first occurrence of a specific item from the list.
     *
     * @param TItem $item The item to remove from the list.
     *
     * @return bool true if item was successfully removed from the list; otherwise, false.
     */
    public function remove(mixed $item): bool
    {
        if (!$this->matchConstraint($item)) {
            throw new InvalidArgumentException(\sprintf('The item must be of type %s.', $this->typeConstraint));
        }
        $i = \array_search($item, $this->items, true);
        if ($i === false) {
            return false;
        }
        \array_splice($this->items, $i, 1);

        return true;
    }

    /**
     * Removes the list item at the specified index.
     *
     * @param int $index The zero-based index of the item to remove.
     *
     * @return TItem The item that was removed from the list.
     */
    public function removeAt(int $index): mixed
    {
        if ($index < 0) {
            throw new InvalidArgumentException('The index must be greater than or equal to zero.');
        }
        $count = \count($this->items);
        if ($index >= $count) {
            throw new InvalidArgumentException(\sprintf('The index cannot be greater than or equal to %d.', $count));
        }
        list($item) = \array_splice($this->items, $index, 1);

        return $item;
    }

    /**
     * Converts the list to an array.
     *
     * @return array<int, TItem> The array representation of the list.
     */
    public function toArray(): array
    {
        return $this->items;
    }
}
