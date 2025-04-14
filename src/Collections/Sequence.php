<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\Collections;

use Manychois\PhpStrong\Collections\Internal\AbstractArray;
use Manychois\PhpStrong\ComparerInterface;
use Manychois\PhpStrong\EqualityComparerInterface;
use Manychois\PhpStrong\Registry;
use OutOfBoundsException;
use Traversable;
use TypeError;
use ValueError;

/**
 * Represents a sequence of values.
 *
 * @template T
 *
 * @template-extends AbstractArray<non-negative-int,T>
 */
final class Sequence extends AbstractArray
{
    /**
     * Creates a new sequence from the provided iterable.
     *
     * @param iterable<T> $initial The initial values for the sequence.
     */
    public function __construct(iterable $initial = [])
    {
        if (\is_array($initial)) {
            if (\array_is_list($initial)) {
                $this->source = $initial;
            } else {
                $this->source = \array_values($initial);
            }
        } else {
            $this->source = \iterator_to_array($initial, false);
        }
    }

    /**
     * Creates a new sequence filled with the specified value.
     *
     * @template TValue
     *
     * @param int    $length The number of values to fill.
     * @param TValue $value  The value to fill the sequence with.
     *
     * @return static<TValue> A new sequence filled with the specified value.
     */
    public static function fill(int $length, mixed $value): static
    {
        if ($length < 0) {
            throw new ValueError('Count must be non-negative.');
        }

        return new static(\array_fill(0, $length, $value));
    }

    /**
     * Initializes a new sequence of objects.
     *
     * @template TObject of object
     *
     * @param class-string<TObject>               $class   The class of the items in the sequence.
     * @param array<TObject>|Traversable<TObject> $initial The initial items of the sequence.
     *
     * @return static<TObject> The new instance.
     */
    public static function ofObject(string $class, array|Traversable $initial = []): static
    {
        return new static($initial);
    }

    /**
     * Initializes a new sequence of strings.
     *
     * @param array<string>|Traversable<string> $initial The initial items of the sequence.
     *
     * @return static<string> The new instance.
     */
    public static function ofString(array|Traversable $initial = []): static
    {
        return new static($initial);
    }

    /**
     * Initializes a new sequence of integers.
     *
     * @param array<int>|Traversable<int> $initial The initial items of the sequence.
     *
     * @return static<int> The new instance.
     */
    public static function ofInt(array|Traversable $initial = []): static
    {
        return new static($initial);
    }

    /**
     * Initializes a new sequence of floats.
     *
     * @param array<float>|Traversable<float> $initial The initial items of the sequence.
     *
     * @return static<float> The new instance.
     */
    public static function ofFloat(array|Traversable $initial = []): static
    {
        return new static($initial);
    }

    /**
     * Initializes a new sequence of booleans.
     *
     * @param array<bool>|Traversable<bool> $initial The initial items of the sequence.
     *
     * @return static<bool> The new instance.
     */
    public static function ofBool(array|Traversable $initial = []): static
    {
        return new static($initial);
    }

    /**
     * Returns the value at the specified offset.
     * If the offset is negative, it counts from the end of the sequence.
     *
     * @param int $offset The offset of the value to retrieve.
     *
     * @return T The value at the specified offset.
     */
    public function at(int $offset): mixed
    {
        $count = \count($this->source);
        $index = $offset < 0 ? $count + $offset : $offset;
        if ($index < 0 || $index >= $count) {
            throw new OutOfBoundsException(\sprintf('Offset %d is out of bounds.', $offset));
        }

        return $this->source[$offset];
    }

    /**
     * Splits the sequence into chunks of the specified length.
     * The last chunk may contain fewer values.
     *
     * @param int $length The length of each chunk.
     *
     * @return static<static<T>> A new sequence containing the chunks.
     */
    public function chunk(int $length): static
    {
        if ($length <= 0) {
            throw new ValueError('Chunk size must be greater than zero.');
        }
        $rawChunks = \array_chunk($this->source, $length);
        $chunks = \array_map(
            static fn (array $chunk): static => new static($chunk),
            $rawChunks
        );

        return new static($chunks);
    }

    /**
     * Returns a new sequence with distinct values.
     *
     * @param EqualityComparerInterface|null $eq The equality comparer to use for comparison.
     *
     * @return static<T> A new sequence with distinct values.
     */
    public function distinct(EqualityComparerInterface|null $eq = null): static
    {
        $distinct = [];
        $eq ??= $this->getDefaultEqualityComparer();
        $count = \count($this->source);
        for ($i = 0; $i < $count; $i++) {
            $value = $this->source[$i];
            for ($j = $i + 1; $j < $count; $j++) {
                if ($eq->equals($value, $this->source[$j])) {
                    continue 2;
                }
            }
            $distinct[] = $value;
        }

        return new static($distinct);
    }

    /**
     * Filters the sequence based on a predicate.
     *
     * @param callable $predicate The predicate function to test each value.
     *
     * @return static<T> A new sequence containing the values that satisfy the predicate.
     *
     * @phpstan-param callable(T,int):bool $predicate
     */
    public function filter(callable $predicate): static
    {
        $filtered = \array_filter($this->source, $predicate, \ARRAY_FILTER_USE_BOTH);

        return new static($filtered);
    }

    /**
     * Finds the index of the first value in the sequence that satisfies the given predicate.
     *
     * @param callable $predicate The predicate function to test each value.
     *
     * @return int The index of the first value that satisfies the predicate, or -1 if no such value is found.
     */
    public function findIndex(callable $predicate): int
    {
        return \array_find_key($this->source, $predicate) ?? -1;
    }

    /**
     * Finds the last value in the sequence that satisfies the given predicate.
     *
     * @param callable $predicate The predicate function to test each value.
     *
     * @return T|null The last value that satisfies the predicate, or `null` if no such value is found.
     *
     * @phpstan-param callable(T,int):bool $predicate
     */
    public function findLast(callable $predicate): mixed
    {
        $count = \count($this->source);
        for ($i = $count - 1; $i >= 0; $i--) {
            if ($predicate($this->source[$i], $i)) {
                return $this->source[$i];
            }
        }

        return null;
    }

    /**
     * Finds the index of the last value in the sequence that satisfies the given predicate.
     *
     * @param callable $predicate The predicate function to test each value.
     *
     * @return int The index of the last value that satisfies the predicate, or -1 if no such value is found.
     */
    public function findLastIndex(callable $predicate): int
    {
        $count = \count($this->source);
        for ($i = $count - 1; $i >= 0; $i--) {
            if ($predicate($this->source[$i], $i)) {
                return $i;
            }
        }

        return -1;
    }

    /**
     * Finds the index of the first occurrence of a value in the sequence.
     *
     * @param T                              $value The value to search for.
     * @param EqualityComparerInterface|null $eq    The equality comparer to use for comparison.
     *
     * @return int The index of the first occurrence of the value, or -1 if not found.
     */
    public function indexOf(mixed $value, EqualityComparerInterface|null $eq = null): int
    {
        $eq ??= $this->getDefaultEqualityComparer();
        $predicate = static fn ($v) => $eq->equals($v, $value);

        return \array_find_key($this->source, $predicate) ?? -1;
    }

    /**
     * Finds the index of the last occurrence of a value in the sequence.
     *
     * @param T                              $value The value to search for.
     * @param EqualityComparerInterface|null $eq    The equality comparer to use for comparison.
     *
     * @return int The index of the last occurrence of the value, or -1 if not found.
     */
    public function lastIndexOf(mixed $value, EqualityComparerInterface|null $eq = null): int
    {
        $eq ??= $this->getDefaultEqualityComparer();

        return $this->findLastIndex(
            static fn ($v) => $eq->equals($v, $value)
        );
    }

    /**
     * Maps each value in the sequence using the provided callback function.
     *
     * @template TNewValue
     *
     * @param callable $callback The callback function to apply to each value.
     *
     * @return static<TNewValue> A new sequence containing the transformed values.
     *
     * @phpstan-param callable(T,int):TNewValue $callback
     */
    public function map(callable $callback): static
    {
        $mapped = [];
        foreach ($this->source as $key => $value) {
            $mapped[$key] = $callback($value, $key);
        }

        return new static($mapped);
    }

    /**
     * Merges this sequence with another sequence.
     *
     * @param iterable<T> ...$others The other sequence to merge with.
     *
     * @return static<T> A new sequence containing the merged values.
     */
    public function merge(iterable ...$others): static
    {
        $result = $this->source;
        foreach ($others as $other) {
            foreach ($other as $o) {
                $result[] = $o;
            }
        }

        return new static($result);
    }

    /**
     * Pads the sequence to the specified length with the given value.
     * The specified value is added to the left side of the sequence.
     *
     * @param int $length The length to pad to.
     * @param T   $value  The value to pad with.
     *
     * @return static<T> A new sequence padded to the specified length.
     */
    public function padLeft(int $length, mixed $value): static
    {
        if ($length < 0) {
            throw new ValueError('Length must be non-negative.');
        }

        /**
         * @var array<T> $items
         */
        $items = \array_pad($this->source, -$length, $value);

        return new static($items);
    }

    /**
     * Pads the sequence to the specified length with the given value.
     * The specified value is added to the right side of the sequence.
     *
     * @param int $length The length to pad to.
     * @param T   $value  The value to pad with.
     *
     * @return static<T> A new sequence padded to the specified length.
     */
    public function padRight(int $length, mixed $value): static
    {
        if ($length < 0) {
            throw new ValueError('Length must be non-negative.');
        }

        /**
         * @var array<T> $items
         */
        $items = \array_pad($this->source, $length, $value);

        return new static($items);
    }

    /**
     * Pops the last value from the sequence and returns it.
     *
     * @return T The last value in the sequence.
     */
    public function pop(): mixed
    {
        if (\count($this->source) === 0) {
            throw new OutOfBoundsException('Cannot pop from an empty sequence.');
        }

        return \array_pop($this->source);
    }

    /**
     * Pushes one or more values onto the end of the sequence.
     *
     * @param T ...$values The values to push onto the sequence.
     */
    public function push(mixed ...$values): void
    {
        foreach ($values as $value) {
            $this->source[] = $value;
        }
    }

    /**
     * Returns a random value from the sequence.
     *
     * @param bool $secure Whether to use a cryptographically secure random generator.
     *
     * @return T A random value from the sequence.
     */
    public function random(bool $secure = false): mixed
    {
        if (\count($this->source) === 0) {
            throw new OutOfBoundsException('Cannot get a random value from an empty sequence.');
        }
        if ($secure) {
            $index = \random_int(0, \count($this->source) - 1);
        } else {
            $index = \array_rand($this->source);
        }

        return $this->source[$index];
    }

    /**
     * Removes the first occurrence of a specific value from the sequence.
     *
     * @param T                              $value The value to remove.
     * @param EqualityComparerInterface|null $eq    The equality comparer to use for comparison.
     *
     * @return bool `true` if the value was removed; otherwise, `false`.
     */
    public function remove(mixed $value, EqualityComparerInterface|null $eq = null): bool
    {
        $index = $this->indexOf($value, $eq);
        if ($index !== -1) {
            \array_splice($this->source, $index, 1);

            return true;
        }

        return false;
    }

    /**
     * Removes one or more values which match the specified predicate from the sequence.
     *
     * @param callable $predicate The predicate that defines the conditions of the values to remove.
     *
     * @return int The number of values removed.
     *
     * @phpstan-param callable(T,int):bool $predicate
     */
    public function removeAll(callable $predicate): int
    {
        $count = 0;
        foreach ($this->source as $key => $value) {
            if (!$predicate($value, $key)) {
                continue;
            }

            unset($this->source[$key]);
            $count++;
        }
        if ($count > 0) {
            $this->source = \array_values($this->source);
        }

        return $count;
    }

    /**
     * Returns a new sequence with the values in reverse order.
     *
     * @return static<T> A new sequence with the values in reverse order.
     */
    public function reverse(): static
    {
        return new static(\array_reverse($this->source));
    }

    /**
     * Shifts the first value from the sequence and returns it.
     *
     * @return T The first value in the sequence.
     */
    public function shift(): mixed
    {
        if (\count($this->source) === 0) {
            throw new OutOfBoundsException('Cannot shift from an empty sequence.');
        }

        return \array_shift($this->source);
    }

    /**
     * Returns a slice of the sequence starting from the specified offset.
     *
     * @param int      $offset The offset to start the slice from.
     *                         If negative, it will start from the
     *                         end of the sequence.
     * @param int|null $length The length of the slice. If null, the slice will include all values from the offset.
     *                         If negative, the requested length is calculated as the length of the sequence reduced by
     *                         that amount.
     *
     * @return static<T> A new sequence containing the sliced values.
     */
    public function slice(int $offset, int|null $length = null): static
    {
        return new static(\array_slice($this->source, $offset, $length));
    }

    /**
     * Removes a portion of the sequence and replaces it with the specified values.
     *
     * @param int                                  $offset      The offset to start the splice from.
     *                                                          If negative, it will start from the
     *                                                          end of the sequence.
     * @param int|null                             $length      The length of the portion to remove.
     *                                                          If null, all values from the offset
     *                                                          to the end of the sequence will be
     *                                                          removed. If negative, the requested
     *                                                          length is calculated as the length
     *                                                          of the sequence reduced by that
     *                                                          amount.
     * @param array<T>|AbstractArray<int|string,T> $replacement The values to insert in place of the removed portion.
     *
     * @return static<T> A sequence containing the removed values.
     */
    public function splice(int $offset, int|null $length = null, array|AbstractArray $replacement = []): static
    {
        if ($replacement instanceof AbstractArray) {
            $replacement = $replacement->source;
        }
        $spliced = \array_splice($this->source, $offset, $length, $replacement);

        return new static($spliced);
    }

    /**
     * Shuffles the values in the sequence.
     *
     * @param bool $secure Whether to use a cryptographically secure random generator.
     */
    public function shuffle(bool $secure = false): void
    {
        if ($secure) {
            $shuffled = [];
            $count = \count($this->source);
            for ($i = $count - 1; $i >= 0; $i--) {
                $index = \random_int(0, $i);
                $shuffled[] = $this->source[$index];
                \array_splice($this->source, $index, 1);
            }
            $this->source = $shuffled;
        } else {
            \shuffle($this->source);
        }
    }

    /**
     * Sorts the values in the sequence using the provided comparer.
     *
     * @param callable|ComparerInterface|null $comparer The comparer to use for sorting.
     *
     * @phpstan-param callable(T,T):int|ComparerInterface|null $comparer
     */
    public function sort(callable|ComparerInterface|null $comparer = null): void
    {
        $comparer ??= Registry::getComparer();
        if ($comparer instanceof ComparerInterface) {
            $comparer = static fn ($a, $b) => $comparer->compare($a, $b);
        }
        \usort($this->source, $comparer);
    }

    /**
     * Prepends one or more values to the beginning of the sequence.
     *
     * @param T ...$values The values to prepend.
     */
    public function unshift(mixed ...$values): void
    {
        \array_unshift($this->source, ...$values);
    }

    #region implements ArrayAccess

    /**
     * @inheritDoc
     */
    public function offsetExists(mixed $offset): bool
    {
        if (!\is_int($offset)) {
            throw new TypeError('Offset must be an integer.');
        }
        if ($offset < 0) {
            $offset += \count($this->source);
        }

        return \array_key_exists($offset, $this->source);
    }

    /**
     * @inheritDoc
     */
    public function offsetGet(mixed $offset): mixed
    {
        if (!\is_int($offset)) {
            throw new TypeError('Offset must be an integer.');
        }
        if ($offset < 0) {
            $offset += \count($this->source);
        }
        if (\array_key_exists($offset, $this->source)) {
            return $this->source[$offset];
        }

        throw new OutOfBoundsException(\sprintf('Offset %d is out of bounds.', $offset));
    }

    /**
     * @inheritDoc
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        if ($offset !== null && !\is_int($offset)) {
            throw new TypeError('Offset must be an integer or null.');
        }
        if ($offset === null) {
            $this->source[] = $value;
        } elseif (\is_int($offset)) {
            $count = \count($this->source);
            $originalOffset = $offset;
            if ($offset < 0) {
                $offset += $count;
            }
            if ($offset < 0 || $offset > $count) {
                throw new OutOfBoundsException(\sprintf('Offset %d is out of bounds.', $originalOffset));
            }
            $this->source[$offset] = $value;
        } else {
            throw new TypeError('Offset must be an integer or null.');
        }
    }

    /**
     * @inheritDoc
     */
    public function offsetUnset(mixed $offset): void
    {
        if (!\is_int($offset)) {
            throw new TypeError('Offset must be an integer.');
        }
        $count = \count($this->source);
        $originalOffset = $offset;
        if ($offset < 0) {
            $offset += $count;
        }
        if ($offset < 0 || $offset >= $count) {
            throw new OutOfBoundsException(\sprintf('Offset %d is out of bounds.', $originalOffset));
        }
        \array_splice($this->source, $offset, 1);
    }

    #endregion implements ArrayAccess

    #region implements EqualInterface

    /**
     * @inheritDoc
     */
    public function equals(mixed $other): bool
    {
        if (\is_array($other) && !\array_is_list($other)) {
            return false;
        }

        return parent::equals($other);
    }

    #endregion implements EqualInterface
}
