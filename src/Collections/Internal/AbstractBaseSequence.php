<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\Collections\Internal;

use InvalidArgumentException;
use Iterator;
use Manychois\PhpStrong\Collections\ComparerInterface as IComparer;
use Manychois\PhpStrong\Collections\Defaults\CacheIterator;
use Manychois\PhpStrong\Collections\Defaults\DefaultEqualityComparer;
use Manychois\PhpStrong\Collections\Defaults\NoRewindLimitIterator;
use Manychois\PhpStrong\Collections\EqualityComparerInterface as IEqualityComparer;
use Manychois\PhpStrong\Collections\SequenceInterface as ISequence;
use Override;
use RuntimeException;
use UnderflowException;

/**
 * Provides common implementations for sequence operations.
 *
 * @template T
 *
 * @implements ISequence<T>
 */
abstract class AbstractBaseSequence implements ISequence
{
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
        $index = 0;
        foreach ($iterable as $item) {
            yield $index++ => $item;
        }
    }

    /**
     * Creates a new lazy sequence from the specified iterable.
     *
     * @template TItem
     *
     * @param iterable<TItem> $source The source iterable for the new sequence.
     *
     * @return ISequence<TItem> A new lazy sequence.
     */
    abstract protected function createLazySequence(iterable $source): ISequence;

    /**
     * Gets the equality comparer to use.
     *
     * @param ?IEqualityComparer $eq The equality comparer to use.
     *
     * @return IEqualityComparer The equality comparer.
     */
    protected function getEqualityComparer(?IEqualityComparer $eq = null): IEqualityComparer
    {
        if ($eq !== null) {
            return $eq;
        }
        return new DefaultEqualityComparer();
    }

    #region implements ISequence

    /**
     * @inheritDoc
     */
    #[Override]
    public function all(callable $predicate): bool
    {
        foreach ($this->getIterator() as $item) {
            if (!$predicate($item)) {
                return false;
            }
        }
        return true;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function any(callable $predicate): bool
    {
        foreach ($this->getIterator() as $item) {
            if ($predicate($item)) {
                return true;
            }
        }
        return false;
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
            $master = $this->getIterator();
            while ($master->valid()) {
                $limiter = new NoRewindLimitIterator($master, $size);
                yield $this->createLazySequence($limiter);
                $limiter->exhaust();
            }
        };
        return $this->createLazySequence($generator());
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function contains(mixed $value, ?IEqualityComparer $eq = null): bool
    {
        $eq = $this->getEqualityComparer($eq);
        foreach ($this->getIterator() as $item) {
            if ($eq->equals($item, $value)) {
                return true;
            }
        }
        return false;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function distinct(?IEqualityComparer $eq = null): ISequence
    {
        $eq = $this->getEqualityComparer($eq);
        $generator = function () use ($eq) {
            $set = [];
            foreach ($this->getIterator() as $item) {
                foreach ($set as $s) {
                    if ($eq->equals($s, $item)) {
                        continue 2;
                    }
                }
                $set[] = $item;
                yield $item;
            }
        };

        return $this->createLazySequence($generator());
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function except(iterable $sequence, ?IEqualityComparer $eq = null): ISequence
    {
        $eq = $this->getEqualityComparer($eq);
        $generator = function () use ($sequence, $eq) {
            $cache = new CacheIterator(self::iterableToIterator($sequence));
            foreach ($this->getIterator() as $item) {
                foreach ($cache as $compareTo) {
                    if ($eq->equals($item, $compareTo)) {
                        continue 2;
                    }
                }
                yield $item;
            }
        };
        return $this->createLazySequence($generator());
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function exists(callable $predicate): bool
    {
        foreach ($this->getIterator() as $item) {
            if ($predicate($item)) {
                return true;
            }
        }
        return false;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function filter(callable $predicate): ISequence
    {
        $generator = function () use ($predicate) {
            $i = 0;
            foreach ($this->getIterator() as $item) {
                if ($predicate($item, $i)) {
                    yield $item;
                }
                $i++;
            }
        };
        return $this->createLazySequence($generator());
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function first(?callable $predicate = null): mixed
    {
        $isEmpty = true;
        $i = 0;
        foreach ($this->getIterator() as $item) {
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
        $i = 0;
        foreach ($this->getIterator() as $item) {
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
    public function isEmpty(): bool
    {
        foreach ($this->getIterator() as $item) {
            return false;
        }
        return true;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function intersect(iterable $sequence, ?IEqualityComparer $eq = null): ISequence
    {
        $eq = $this->getEqualityComparer($eq);
        $generator = function () use ($sequence, $eq) {
            $cache = new CacheIterator(self::iterableToIterator($sequence));
            foreach ($this->getIterator() as $item) {
                foreach ($cache as $compareTo) {
                    if ($eq->equals($item, $compareTo)) {
                        yield $item;
                    }
                }
            }
        };

        return $this->createLazySequence($generator());
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function last(?callable $predicate = null): mixed
    {
        $isEmpty = true;
        $i = 0;
        $temp = new \stdClass();
        $last = $temp;
        foreach ($this->getIterator() as $item) {
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
        $i = 0;
        $last = null;
        foreach ($this->getIterator() as $item) {
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
            foreach ($this->getIterator() as $item) {
                yield $callback($item, $i);
                $i++;
            }
        };
        return $this->createLazySequence($generator());
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function orderBy(IComparer $comparer): ISequence
    {
        $list = [];
        foreach ($this->getIterator() as $item) {
            $list[] = $item;
        }
        usort($list, function ($a, $b) use ($comparer) {
            return $comparer->compare($a, $b);
        });
        return $this->createLazySequence($list);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function orderDescBy(IComparer $comparer): ISequence
    {
        $list = [];
        foreach ($this->getIterator() as $item) {
            $list[] = $item;
        }
        usort($list, function ($a, $b) use ($comparer) {
            return -$comparer->compare($a, $b);
        });
        return $this->createLazySequence($list);
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
            foreach ($this->getIterator() as $item) {
                yield $item;
            }
        };
        return $this->createLazySequence($generator());
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function reduce(callable $callback, mixed $initial): mixed
    {
        $result = $initial;
        $i = 0;
        foreach ($this->getIterator() as $item) {
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
        $list = [];
        foreach ($this->getIterator() as $item) {
            $list[] = $item;
        }
        $list = array_reverse($list);

        return $this->createLazySequence($list);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function shuffle(): ISequence
    {
        $list = [];
        foreach ($this->getIterator() as $item) {
            $list[] = $item;
        }
        shuffle($list);
        return $this->createLazySequence($list);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function skip(int $count): ISequence
    {
        if ($count < 0) {
            throw new InvalidArgumentException('Count must be greater than 0');
        }

        $generator = function () use ($count) {
            $i = 0;
            foreach ($this->getIterator() as $item) {
                if ($i >= $count) {
                    yield $item;
                }
                $i++;
            }
        };
        return $this->createLazySequence($generator());
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function skipLast(int $count): ISequence
    {
        if ($count < 0) {
            throw new InvalidArgumentException('Count must be greater than 0');
        }

        $generator = function () use ($count) {
            $buffer = [];
            foreach ($this->getIterator() as $item) {
                $buffer[] = $item;
                if (count($buffer) > $count) {
                    $shifted = array_shift($buffer);
                    yield $shifted;
                }
            }
        };
        return $this->createLazySequence($generator());
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function take(int $count): ISequence
    {
        $generator = function () use ($count) {
            $i = 0;
            foreach ($this->getIterator() as $item) {
                if ($i >= $count) {
                    break;
                }
                yield $item;
                $i++;
            }
        };
        return $this->createLazySequence($generator());
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function takeLast(int $count): ISequence
    {
        $generator = function () use ($count) {
            $buffer = [];
            foreach ($this->getIterator() as $item) {
                $buffer[] = $item;
                if (count($buffer) > $count) {
                    array_shift($buffer);
                }
            }
            foreach ($buffer as $item) {
                yield $item;
            }
        };
        return $this->createLazySequence($generator());
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function then(iterable ...$sequences): ISequence
    {
        $generator = function () use ($sequences) {
            foreach ($this->getIterator() as $item) {
                yield $item;
            }
            foreach ($sequences as $seq) {
                foreach ($seq as $item) {
                    yield $item;
                }
            }
        };

        return $this->createLazySequence($generator());
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function union(iterable $sequence, ?IEqualityComparer $eq = null): ISequence
    {
        $eq = $this->getEqualityComparer($eq);
        $generator = function () use ($sequence, $eq) {
            $set = [];
            foreach ($this->getIterator() as $item) {
                foreach ($set as $s) {
                    if ($eq->equals($item, $s)) {
                        continue 2;
                    }
                }
                $set[] = $item;
                yield $item;
            }
            foreach ($sequence as $item) {
                foreach ($set as $s) {
                    if ($eq->equals($item, $s)) {
                        continue 2;
                    }
                }
                $set[] = $item;
                yield $item;
            }
        };
        return $this->createLazySequence($generator());
    }

    #endregion implements ISequence
}
