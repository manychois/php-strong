<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\Collections;

use Generator;
use Manychois\PhpStrong\Collections\Internal\AbstractArraySequence;
use Manychois\PhpStrong\Collections\Internal\SequenceFactoryTrait;
use Manychois\PhpStrong\ComparerInterface;
use Manychois\PhpStrong\Defaults\DefaultEqualityComparer;
use Manychois\PhpStrong\EqualityComparerInterface;
use OutOfRangeException;
use Traversable;

/**
 * Represents a read-only sequence based on either an array or a traversable.
 *
 * @template T
 *
 * @template-extends AbstractArraySequence<T>
 */
class ReadonlySequence extends AbstractArraySequence
{
    use SequenceFactoryTrait;

    /**
     * @var Traversable<T>|null The traversable of the sequence.
     *                         If null, the sequence is based on an array.
     */
    protected ?Traversable $traversable;

    /**
     * Initializes a new instance of the ReadonlySequence class.
     *
     * @param array<T>|Traversable<T> $initial The initial items of the sequence.
     */
    public function __construct(array|Traversable $initial)
    {
        if (\is_array($initial) || $initial instanceof AbstractArraySequence && !($initial instanceof self)) {
            parent::__construct($initial);

            $this->traversable = null;
        } else {
            parent::__construct([]);

            $this->traversable = $initial;
        }
    }

    /**
     * If the sequence is based on a traversable, stores its items such that
     * further operations will not iterate the traversable again.
     *
     * @return self<T> This instance.
     */
    public function freeze(): self
    {
        if ($this->traversable !== null) {
            $this->items = \iterator_to_array($this->traversable, false);
            $this->traversable = null;
        }

        return $this;
    }


    #region overrides ArraySequence

    /**
     * @inheritDoc
     */
    public function asArray(): array
    {
        if ($this->traversable === null) {
            return parent::asArray();
        }

        return \iterator_to_array($this->traversable, false);
    }

    /**
     * @inheritDoc
     */
    public function binarySearch(mixed $target, ?ComparerInterface $c = null): int
    {
        if ($this->traversable === null) {
            return parent::binarySearch($target, $c);
        }

        $seq = new Sequence($this->traversable);

        return $seq->binarySearch($target, $c);
    }

    /**
     * @inheritDoc
     */
    public function count(): int
    {
        if ($this->traversable === null) {
            return parent::count();
        }

        return \iterator_count($this->traversable);
    }

    /**
     * @inheritDoc
     */
    public function find(callable $predicate, int $from = 0): mixed
    {
        if ($this->traversable === null) {
            return parent::find($predicate, $from);
        }

        if ($from >= 0) {
            $i = 0;
            foreach ($this->traversable as $item) {
                if ($i >= $from && $predicate($item, $i)) {
                    return $item;
                }
                $i++;
            }
        } else {
            $size = -$from;
            $subset = [];
            $i = 0;
            foreach ($this->traversable as $item) {
                $subset[$i] = $item;
                $i++;
                if (\count($subset) <= $size) {
                    continue;
                }

                \array_shift($subset);
            }
            foreach ($subset as $index => $item) {
                if ($predicate($item, $index)) {
                    return $item;
                }
            }
        }

        return null;
    }

    /**
     * @inheritDoc
     */
    public function findIndex(callable $predicate, int $from = 0): int
    {
        if ($this->traversable === null) {
            return parent::findIndex($predicate, $from);
        }

        if ($from >= 0) {
            $i = 0;
            foreach ($this->traversable as $item) {
                if ($i >= $from && $predicate($item, $i)) {
                    return $i;
                }
                $i++;
            }
        } else {
            $size = -$from;
            $subset = [];
            $i = 0;
            foreach ($this->traversable as $item) {
                $subset[$i] = $item;
                $i++;
                if (\count($subset) <= $size) {
                    continue;
                }

                \array_shift($subset);
            }
            foreach ($subset as $index => $item) {
                if ($predicate($item, $index)) {
                    return $i;
                }
            }
        }

        return -1;
    }

    /**
     * @inheritDoc
     */
    public function findLast(callable $predicate, int $from = -1): mixed
    {
        if ($this->traversable === null) {
            return parent::findLast($predicate, $from);
        }

        /**
         * @var Sequence<T> $seq
         */
        $seq = new Sequence($this->traversable);

        return $seq->findLast($predicate, $from);
    }

    /**
     * @inheritDoc
     */
    public function findLastIndex(callable $predicate, int $from = -1): int
    {
        if ($this->traversable === null) {
            return parent::findLastIndex($predicate, $from);
        }

        /**
         * @var Sequence<T> $seq
         */
        $seq = new Sequence($this->traversable);

        return $seq->findLastIndex($predicate, $from);
    }

    /**
     * @inheritDoc
     */
    public function get(int $index): mixed
    {
        if ($this->traversable === null) {
            return parent::get($index);
        }

        if ($index < 0) {
            $array = \iterator_to_array($this->traversable, false);
            $count = \count($array);
            $index += $count;
            if ($index < 0) {
                throw new OutOfRangeException('The index is out of range.');
            }
        }

        $i = 0;
        foreach ($this->traversable as $item) {
            if ($i === $index) {
                return $item;
            }
            $i++;
        }

        throw new OutOfRangeException('The index is out of range.');
    }

    /**
     * @inheritDoc
     */
    public function getIterator(): Generator
    {
        if ($this->traversable === null) {
            return parent::getIterator();
        }

        $i = 0;
        foreach ($this->traversable as $item) {
            yield $i => $item;

            $i++;
        }
    }

    /**
     * @inheritDoc
     */
    public function indexOf(mixed $target, int $from = 0, ?EqualityComparerInterface $eq = null): int
    {
        if ($this->traversable === null) {
            return parent::indexOf($target, $from, $eq);
        }

        $eq ??= new DefaultEqualityComparer();
        if ($from >= 0) {
            $i = 0;
            foreach ($this->traversable as $item) {
                if ($i >= $from && $eq->equals($item, $target)) {
                    return $i;
                }
                $i++;
            }
        } else {
            $size = -$from;
            $subset = [];
            $i = 0;
            foreach ($this->traversable as $item) {
                $subset[$i] = $item;
                $i++;
                if (\count($subset) <= $size) {
                    continue;
                }

                \array_shift($subset);
            }
            foreach ($subset as $index => $item) {
                if ($eq->equals($item, $target)) {
                    return $index;
                }
            }
        }

        return -1;
    }

    /**
     * @inheritDoc
     */
    public function lastIndexOf(mixed $target, int $from = -1, ?EqualityComparerInterface $eq = null): int
    {
        if ($this->traversable === null) {
            return parent::lastIndexOf($target, $from, $eq);
        }

        /**
         * @var Sequence<T> $seq
         */
        $seq = new Sequence($this->traversable);

        return $seq->lastIndexOf($target, $from, $eq);
    }

    /**
     * @inheritDoc
     */
    public function reverse(): self
    {
        if ($this->traversable === null) {
            return parent::reverse();
        }

        /**
         * @var Sequence<T> $seq
         */
        $seq = new Sequence($this->traversable);

        return $seq->reverse();
    }

    #endregion overrides ArraySequence
}
