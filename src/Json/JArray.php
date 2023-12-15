<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\Json;

use Countable;
use InvalidArgumentException;
use IteratorAggregate;
use Traversable;

/**
 * Represents a JSON array.
 *
 * @implements IteratorAggregate<int, mixed>
 */
class JArray implements Countable, IteratorAggregate
{
    /**
     * @var array<int, mixed>
     */
    private array $data;

    #region implements Countable

    public function count(): int
    {
        return \count($this->data);
    }

    #endregion implements Countable

    #region implements IteratorAggregate

    /**
     * @return Traversable<int, mixed>
     */
    public function getIterator(): Traversable
    {
        foreach ($this->data as $value) {
            yield $value;
        }
    }

    #endregion implements IteratorAggregate

    /**
     * Adds an item to the end of the array.
     *
     * @param mixed $item The item to add.
     */
    public function add(mixed $item): void
    {
        self::checkItem($item);
        $this->data[] = $item;
    }

    /**
     * Resets the array and applies the given data.
     *
     * @param array<int, mixed> $data The JSON data as a list-type array.
     */
    public function apply(array $data): void
    {
        if (!\array_is_list($data)) {
            throw new InvalidArgumentException('JArray::apply() requires a list-type array.');
        }

        $this->data = [];
        foreach ($data as $i => $value) {
            if (\is_array($value)) {
                if (\array_is_list($value)) {
                    $this->data[] = new self();
                } else {
                    $this->data[] = new JObject();
                }
                $this->data[$i]->apply($value);
            } else {
                $this->data[] = $value;
            }
        }
    }

    /**
     * Inserts an item at the given index.
     *
     * @param int   $index The index to insert the item.
     * @param mixed $item  The item to insert.
     */
    public function insertAt(int $index, mixed $item): void
    {
        self::checkItem($item);
        if ($index < 0 || $index > \count($this->data)) {
            throw new InvalidArgumentException('Index is out of range.');
        }

        \array_splice($this->data, $index, 0, [$item]);
    }

    /**
     * Checks if the value of the given index is null.
     *
     * @param int $index The index of the item.
     *
     * @return bool True if the value is null, false otherwise.
     */
    public function isNull(int $index): bool
    {
        return $this->item($index) === null;
    }

    /**
     * Gets the value of the given index.
     *
     * @param int $index The index of the item.
     *
     * @return mixed The value of the item.
     */
    public function item(int $index): mixed
    {
        if ($index < 0 || $index >= \count($this->data)) {
            throw new InvalidArgumentException("Index is out of range.");
        }

        return $this->data[$index];
    }

    /**
     * Gets the value of the given index as an array.
     *
     * @param int $index The index of the item.
     *
     * @return JArray The value of the item.
     */
    public function itemAsArray(int $index): JArray
    {
        $value = $this->item($index);
        if (!($value instanceof JArray)) {
            throw new InvalidArgumentException("Item at index $index is not an array.");
        }

        return $value;
    }

    /**
     * Gets the value of the given index as a boolean.
     *
     * @param int $index The index of the item.
     *
     * @return bool The value of the item.
     */
    public function itemAsBool(int $index): bool
    {
        $value = $this->item($index);
        if (!\is_bool($value)) {
            throw new InvalidArgumentException("Item at index $index is not a boolean.");
        }

        return $value;
    }

    /**
     * Gets the value of the given index as a float.
     *
     * @param int $index The index of the item.
     *
     * @return float The value of the item.
     */
    public function itemAsFloat(int $index): float
    {
        $value = $this->item($index);
        if (!\is_float($value)) {
            throw new InvalidArgumentException("Item at index $index is not a float.");
        }

        return $value;
    }

    /**
     * Gets the value of the given index as an integer.
     *
     * @param int $index The index of the item.
     *
     * @return int The value of the item.
     */
    public function itemAsInt(int $index): int
    {
        $value = $this->item($index);
        if (!\is_int($value)) {
            throw new InvalidArgumentException("Item at index $index is not an integer.");
        }

        return $value;
    }

    /**
     * Gets the value of the given index as an object.
     *
     * @param int $index The index of the item.
     *
     * @return JObject The value of the item.
     */
    public function itemAsObject(int $index): JObject
    {
        $value = $this->item($index);
        if (!($value instanceof JObject)) {
            throw new InvalidArgumentException("Item at index $index is not an object.");
        }

        return $value;
    }

    /**
     * Gets the value of the given index as a string.
     *
     * @param int $index The index of the item.
     *
     * @return string The value of the item.
     */
    public function itemAsString(int $index): string
    {
        $value = $this->item($index);
        if (!\is_string($value)) {
            throw new InvalidArgumentException("Item at index $index is not a string.");
        }

        return $value;
    }

    /**
     * Removes the item at the given index.
     *
     * @param int $index The index of the item.
     *
     * @return mixed The removed item.
     */
    public function removeAt(int $index): mixed
    {
        if ($index < 0 || $index >= \count($this->data)) {
            throw new InvalidArgumentException('Index is out of range.');
        }

        $removed = \array_splice($this->data, $index, 1);

        return $removed[0];
    }

    /**
     * Converts the JSON array into a primitive PHP array.
     *
     * @return array<int, mixed> The PHP array.
     */
    public function toArray(): array
    {
        $result = $this->data;
        foreach ($result as $i => $value) {
            if ($value instanceof JObject) {
                $result[$i] = $value->toArray();
            } elseif ($value instanceof self) {
                $result[$i] = $value->toArray();
            }
        }

        return $result;
    }

    /**
     * Returns the JSON representation of the array.
     *
     * @param bool $pretty True to format the JSON string with indentation and line breaks.
     *
     * @return string The JSON representation of the array.
     */
    public function toJson(bool $pretty = false): string
    {
        $flags = \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR;
        if ($pretty) {
            $flags |= \JSON_PRETTY_PRINT;
        }

        return \json_encode($this->toArray(), $flags);
    }

    /**
     * Checks if the given item is valid.
     *
     * @param mixed $item The item to check.
     */
    private static function checkItem(mixed $item): void
    {
        if (
            \is_null($item) ||
            \is_bool($item) ||
            \is_int($item) ||
            \is_float($item) ||
            \is_string($item) ||
            $item instanceof JObject ||
            $item instanceof self
        ) {
            return;
        }

        throw new InvalidArgumentException('Item must be null, a scalar, a JArray, or a JObject.');
    }
}
