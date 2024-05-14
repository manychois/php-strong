<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\Collections;

use BadMethodCallException;

/**
 * Represents a read-only list of objects that can be individually accessed by zero-based index.
 *
 * @template T The type of the items in the list.
 *
 * @phpstan-extends ArrayList<T>
 */
class ReadonlyArrayList extends ArrayList
{
    /**
     * Initializes a new instance of the `ReadonlyArrayList` class that contains items copied from the specified
     * iterable.
     *
     * @param string            $className The class name of the items in the list.
     * @param iterable<TObject> $source    The iterable whose items are copied to the new list.
     *
     * @return self<TObject> The new list.
     *
     * @template TObject The type of the items in the list.
     *
     * @phpstan-param class-string<TObject> $className
     */
    public static function ofType(string $className, iterable $source = []): self
    {
        /** @var self<TObject> $result */
        $result = new self($source);

        return $result;
    }

    #region extends ArrayList

    public function add(mixed ...$items): void
    {
        throw new BadMethodCallException('This list is read-only.');
    }

    public function clear(): void
    {
        throw new BadMethodCallException('This list is read-only.');
    }

    public function insert(int $index, mixed ...$items): void
    {
        throw new BadMethodCallException('This list is read-only.');
    }

    public function pop(): mixed
    {
        throw new BadMethodCallException('This list is read-only.');
    }

    public function remove(mixed $item): bool
    {
        throw new BadMethodCallException('This list is read-only.');
    }

    public function removeAt(int $index): mixed
    {
        throw new BadMethodCallException('This list is read-only.');
    }

    public function removeRange(int $index, ?int $length = null): ArrayList
    {
        throw new BadMethodCallException('This list is read-only.');
    }

    public function shift(): mixed
    {
        throw new BadMethodCallException('This list is read-only.');
    }

    public function sort(ComparerInterface $comparer = null): void
    {
        throw new BadMethodCallException('This list is read-only.');
    }

    #endregion extends ArrayList
}
