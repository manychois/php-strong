<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\Collections;

use InvalidArgumentException;
use Iterator;
use Manychois\PhpStrong\Collections\ArrayList;
use Manychois\PhpStrong\Collections\CacheIterator;
use Manychois\PhpStrong\Collections\ComparerInterface as IComparer;
use Manychois\PhpStrong\Collections\ListInterface as IList;
use Manychois\PhpStrong\Collections\NoRewindLimitIterator;
use Manychois\PhpStrong\Collections\SequenceInterface as ISequence;
use Override;
use RuntimeException;
use UnderflowException;

/**
 * A lazy sequence backed by an iterable source.
 *
 * @template T
 *
 * @implements ISequence<T>
 */
class LazySequence implements ISequence
{
    /**
     * The source iterable for the lazy sequence.
     *
     * @var iterable<T>
     */
    private readonly iterable $source;

    /**
     * Creates a new LazySequence from an iterable source.
     *
     * @param iterable<T> $source The source iterable.
     */
    public function __construct(iterable $source)
    {
        $this->source = $source;
    }

    /**
     * Converts an iterable to an iterator.
     *
     * @param iterable<T> $iterable The iterable to convert.
     *
     * @return Iterator<int, T> The iterator.
     *
     * @phpstan-return Iterator<non-negative-int, T>
     */
    protected static function iterableToIterator(iterable $iterable): Iterator
    {
        $i = 0;
        foreach ($iterable as $item) {
            yield $i => $item;
            $i++;
        }
    }

    #region implements ISequence

    /**
     * @inheritDoc
     */
    #[Override]
    public function all(callable $predicate): bool
    {
        $i = 0;
        foreach ($this->source as $item) {
            if (!$predicate($item, $i)) {
                return false;
            }
            $i++;
        }
        return true;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function any(callable $predicate): bool
    {
        $i = 0;
        foreach ($this->source as $item) {
            if ($predicate($item, $i)) {
                return true;
            }
            $i++;
        }
        return false;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function asArray(): array
    {
        if (is_array($this->source)) {
            return \array_is_list($this->source) ? $this->source : array_values($this->source);
        }
        $result = iterator_to_array($this->source, false);
        assert(\array_is_list($result));
        return $result;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function asList(): IList
    {
        return new ArrayList($this->source);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function chunk(int $size): ISequence
    {
        if ($size <= 0) {
            throw new InvalidArgumentException('Size must be greater than 0');
        }

        $generator = function () use ($size) {
            $master = self::iterableToIterator($this->source);
            while ($master->valid()) {
                $limiter = new NoRewindLimitIterator($master, $size);
                yield new self($limiter);
                $limiter->exhaust();
            }
        };
        return new self($generator());
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function contains(mixed $value): bool
    {
        if (is_array($this->source)) {
            return in_array($value, $this->source, true);
        }

        foreach ($this->source as $item) {
            if ($item === $value) {
                return true;
            }
        }
        return false;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function count(): int
    {
        if (is_array($this->source)) {
            return count($this->source);
        }
        return iterator_count($this->source);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function distinct(): ISequence
    {
        $generator = function () {
            $set = [];
            foreach ($this->source as $item) {
                if (in_array($item, $set, true)) {
                    continue;
                }
                $set[] = $item;
                yield $item;
            }
        };

        return new self($generator());
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function except(iterable $sequence): ISequence
    {
        $generator = function () use ($sequence) {
            if (is_array($sequence)) {
                foreach ($this->source as $item) {
                    if (in_array($item, $sequence, true)) {
                        continue;
                    }
                    yield $item;
                }
            } else {
                $cache = new CacheIterator(self::iterableToIterator($sequence));
                foreach ($this->source as $item) {
                    foreach ($cache as $compareTo) {
                        if ($item === $compareTo) {
                            continue 2;
                        }
                    }
                    yield $item;
                }
            }
        };
        return new self($generator());
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function filter(callable $predicate): ISequence
    {
        $generator = function () use ($predicate) {
            $i = 0;
            foreach ($this->source as $item) {
                if ($predicate($item, $i)) {
                    yield $item;
                }
                $i++;
            }
        };
        return new self($generator());
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function first(?callable $predicate = null): mixed
    {
        if ($predicate === null && is_array($this->source)) {
            $count = count($this->source);
            if ($count === 0) {
                throw new UnderflowException('The sequence is empty');
            }
            $firstKey = \array_key_first($this->source);
            return $this->source[$firstKey];
        }

        $isEmpty = true;
        $i = 0;
        foreach ($this->source as $item) {
            $isEmpty = false;
            if ($predicate === null || $predicate($item, $i)) {
                return $item;
            }
            $i++;
        }
        if ($isEmpty) {
            throw new UnderflowException('The sequence is empty');
        }
        throw new RuntimeException('No item satisfies the predicate');
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function firstOrNull(?callable $predicate = null): mixed
    {
        if ($predicate === null && is_array($this->source)) {
            $count = count($this->source);
            if ($count === 0) {
                return null;
            }
            $firstKey = \array_key_first($this->source);
            return $this->source[$firstKey];
        }

        $i = 0;
        foreach ($this->source as $item) {
            if ($predicate === null || $predicate($item, $i)) {
                return $item;
            }
            $i++;
        }
        return null;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function getIterator(): Iterator
    {
        $i = 0;
        foreach ($this->source as $item) {
            yield $i => $item;
            $i++;
        }
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function intersect(iterable $sequence): ISequence
    {
        $generator = function () use ($sequence) {
            if (is_array($sequence)) {
                foreach ($this->source as $item) {
                    if (in_array($item, $sequence, true)) {
                        yield $item;
                    }
                }
            } else {
                $cache = new CacheIterator(self::iterableToIterator($sequence));
                foreach ($this->source as $item) {
                    foreach ($cache as $compareTo) {
                        if ($item === $compareTo) {
                            yield $item;
                        }
                    }
                }
            }
        };

        return new self($generator());
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function isEmpty(): bool
    {
        foreach ($this->source as $item) {
            return false;
        }
        return true;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function last(?callable $predicate = null): mixed
    {
        if ($predicate === null && is_array($this->source)) {
            $count = count($this->source);
            if ($count === 0) {
                throw new UnderflowException('The sequence is empty');
            }
            $lastKey = \array_key_last($this->source);
            return $this->source[$lastKey];
        }

        $isEmpty = true;
        $i = 0;
        $temp = new \stdClass();
        $last = $temp;
        foreach ($this->source as $item) {
            $isEmpty = false;
            if ($predicate === null || $predicate($item, $i)) {
                $last = $item;
            }
            $i++;
        }
        if ($isEmpty) {
            throw new UnderflowException('The sequence is empty');
        }
        if ($last === $temp) {
            throw new RuntimeException('No item satisfies the predicate');
        }
        assert(!($last instanceof \stdClass));
        return $last;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function lastOrNull(?callable $predicate = null): mixed
    {
        if ($predicate === null && is_array($this->source)) {
            $count = count($this->source);
            if ($count === 0) {
                return null;
            }
            $lastKey = \array_key_last($this->source);
            return $this->source[$lastKey];
        }

        $i = 0;
        $last = null;
        foreach ($this->source as $item) {
            if ($predicate === null || $predicate($item, $i)) {
                $last = $item;
            }
            $i++;
        }
        return $last;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function map(callable $callback): ISequence
    {
        $generator = function () use ($callback) {
            $i = 0;
            foreach ($this->source as $item) {
                yield $callback($item, $i);
                $i++;
            }
        };
        return new self($generator());
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function orderBy(IComparer $comparer): ISequence
    {
        $list = [];
        foreach ($this->source as $item) {
            $list[] = $item;
        }
        usort($list, function ($a, $b) use ($comparer) {
            return $comparer->compare($a, $b);
        });
        return new self($list);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function orderDescBy(IComparer $comparer): ISequence
    {
        $list = [];
        foreach ($this->source as $item) {
            $list[] = $item;
        }
        usort($list, function ($a, $b) use ($comparer) {
            return -$comparer->compare($a, $b);
        });
        return new self($list);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function precededBy(iterable ...$sequences): ISequence
    {
        $generator = function () use ($sequences) {
            foreach ($sequences as $seq) {
                foreach ($seq as $item) {
                    yield $item;
                }
            }
            foreach ($this->source as $item) {
                yield $item;
            }
        };
        return new self($generator());
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function reduce(callable $callback, mixed $initial): mixed
    {
        $result = $initial;
        $i = 0;
        foreach ($this->source as $item) {
            $result = $callback($result, $item, $i);
            $i++;
        }
        return $result;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function reverse(): ISequence
    {
        if (is_array($this->source)) {
            return new self(array_reverse($this->source));
        }

        $list = \iterator_to_array($this->source, false);
        $list = array_reverse($list);

        return new self($list);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function shuffle(): ISequence
    {
        $list = is_array($this->source) ? $this->source : \iterator_to_array($this->source, false);
        shuffle($list);
        return new self($list);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function slice(int $index, int $length): ISequence
    {
        if ($length < 0) {
            throw new InvalidArgumentException('Length must be greater than or equal to 0');
        }
        if ($index < 0) {
            throw new InvalidArgumentException('Index must be greater than or equal to 0');
        }
        if ($length === 0) {
            return new self([]);
        }

        $generator = function () use ($index, $length) {
            $i = 0;
            $end = $index + $length;
            foreach ($this->source as $item) {
                if ($i >= $index && $i < $end) {
                    yield $item;
                }
                if ($i >= $end) {
                    break;
                }
                $i++;
            }
        };
        return new self($generator());
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function skip(int $count): ISequence
    {
        if ($count < 0) {
            throw new InvalidArgumentException('Count must be greater than or equal to 0');
        }

        $generator = function () use ($count) {
            $i = 0;
            foreach ($this->source as $item) {
                if ($i >= $count) {
                    yield $item;
                }
                $i++;
            }
        };
        return new self($generator());
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function skipLast(int $count): ISequence
    {
        if ($count < 0) {
            throw new InvalidArgumentException('Count must be greater than or equal to 0');
        }

        $generator = function () use ($count) {
            $buffer = [];
            foreach ($this->source as $item) {
                $buffer[] = $item;
                if (count($buffer) > $count) {
                    $shifted = array_shift($buffer);
                    yield $shifted;
                }
            }
        };
        return new self($generator());
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function take(int $count): ISequence
    {
        if ($count < 0) {
            throw new InvalidArgumentException('Count must be greater than or equal to 0');
        }
        if ($count === 0) {
            return new self([]);
        }

        $generator = function () use ($count) {
            $i = 0;
            foreach ($this->source as $item) {
                if ($i >= $count) {
                    break;
                }
                yield $item;
                $i++;
            }
        };
        return new self($generator());
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function takeLast(int $count): ISequence
    {
        if ($count < 0) {
            throw new InvalidArgumentException('Count must be greater than or equal to 0');
        }
        if ($count === 0) {
            return new self([]);
        }

        $generator = function () use ($count) {
            $buffer = [];
            foreach ($this->source as $item) {
                $buffer[] = $item;
                if (count($buffer) > $count) {
                    array_shift($buffer);
                }
            }
            foreach ($buffer as $item) {
                yield $item;
            }
        };
        return new self($generator());
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function then(iterable ...$sequences): ISequence
    {
        $generator = function () use ($sequences) {
            foreach ($this->source as $item) {
                yield $item;
            }
            foreach ($sequences as $seq) {
                foreach ($seq as $item) {
                    yield $item;
                }
            }
        };

        return new self($generator());
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function union(iterable $sequence): ISequence
    {
        $generator = function () use ($sequence) {
            $set = [];
            foreach ($this->source as $item) {
                if (in_array($item, $set, true)) {
                    continue;
                }
                $set[] = $item;
                yield $item;
            }
            foreach ($sequence as $item) {
                if (in_array($item, $set, true)) {
                    continue;
                }
                $set[] = $item;
                yield $item;
            }
        };
        return new self($generator());
    }

    #endregion implements ISequence
}
