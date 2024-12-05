<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\Collections;

use Generator;
use Manychois\PhpStrong\Collections\Internal\AbstractArrayMap;
use Manychois\PhpStrong\Collections\Internal\MapFactoryTrait;
use OutOfBoundsException;
use Traversable;

/**
 * Represents a read-only map based on either an array or a traversable.
 *
 * @template TKey
 * @template TValue
 *
 * @template-extends AbstractArrayMap<TKey,TValue>
 */
class ReadonlyMap extends AbstractArrayMap
{
    use MapFactoryTrait;

    /**
     * @var Traversable<TKey,TValue>|null The traversable of the map.
     *                         If null, the map is based on an arrays.
     */
    protected ?Traversable $traversable;

    /**
     * Initializes a new instance of the ReadonlyMap class.
     *
     * @param array<TKey,TValue>|Traversable<TKey,TValue> $initial The initial items of the map.
     * @param DuplicateKeyPolicy                          $policy  Action to take when a duplicate key is found.
     */
    public function __construct(
        array|Traversable $initial = [],
        DuplicateKeyPolicy $policy = DuplicateKeyPolicy::ThrowException
    ) {
        if (\is_array($initial) || $initial instanceof AbstractArrayMap && !($initial instanceof self)) {
            parent::__construct($initial, $policy);

            $this->traversable = null;
        } else {
            parent::__construct([]);

            $this->traversable = $initial;
        }
    }

    /**
     * If the map is based on a traversable, stores its keys and values such that
     * further operations will not iterate the traversable again.
     *
     * @return self<TKey,TValue> This instance.
     */
    public function freeze(): self
    {
        if ($this->traversable !== null) {
            $this->keys = [];
            $this->values = [];
            $allIntOrStringKeys = true;
            foreach ($this->traversable as $key => $value) {
                if (!\is_int($key) && !\is_string($key)) {
                    $allIntOrStringKeys = false;
                }
                $this->internalSet($key, $value);
            }
            if ($allIntOrStringKeys) {
                $this->keys = null;
            }
        }

        return $this;
    }

    #region extends AbstractArrayMap

    /**
     * @inheritDoc
     */
    public function asArray(): array
    {
        if ($this->traversable === null) {
            return parent::asArray();
        }

        $array = [];
        foreach ($this->traversable as $k => $v) {
            $validKey = $this->getArrayKey($k);
            if (\array_key_exists($validKey, $array)) {
                if ($this->duplicateKeyPolicy === DuplicateKeyPolicy::ThrowException) {
                    throw new OutOfBoundsException('The key is duplicated.');
                }

                if ($this->duplicateKeyPolicy === DuplicateKeyPolicy::Ignore) {
                    continue;
                }
            }

            $array[$validKey] = $v;
        }

        return $array;
    }

    /**
     * @inheritDoc
     */
    public function count(): int
    {
        if ($this->traversable === null) {
            return parent::count();
        }

        return \iterator_count($this->validTraversable());
    }

    /**
     * @inheritDoc
     */
    public function get(mixed $key): mixed
    {
        if ($this->traversable === null) {
            return parent::get($key);
        }

        $key = $this->getArrayKey($key);
        foreach ($this->validTraversable() as $k => $v) {
            if ($key === $k) {
                return $v;
            }
        }

        throw new OutOfBoundsException('The key does not exist.');
    }

    /**
     * @inheritDoc
     */
    public function getIterator(): Generator
    {
        if ($this->traversable === null) {
            return parent::getIterator();
        }

        foreach ($this->validTraversable() as $k => $v) {
            yield $k => $v;
        }
    }

    /**
     * @inheritDoc
     */
    public function hasKey(mixed $key): bool
    {
        if ($this->traversable === null) {
            return parent::hasKey($key);
        }

        $key = $this->getArrayKey($key);
        // phpcs:ignore SlevomatCodingStandard.Variables.UnusedVariable.UnusedVariable
        foreach ($this->validTraversable() as $k => $v) {
            if ($key === $k) {
                return true;
            }
        }

        return false;
    }

    /**
     * @inheritDoc
     */
    public function keys(): ReadonlySequence
    {
        if ($this->traversable === null) {
            return parent::keys();
        }

        $generator = function () {
            // phpcs:ignore SlevomatCodingStandard.Variables.UnusedVariable.UnusedVariable
            foreach ($this->validTraversable() as $k => $v) {
                yield $k;
            }
        };

        return new ReadonlySequence($generator());
    }

    /**
     * @inheritDoc
     */
    public function values(): ReadonlySequence
    {
        if ($this->traversable === null) {
            return parent::values();
        }

        $generator = function () {
            // phpcs:ignore SlevomatCodingStandard.Variables.UnusedVariable.UnusedVariable
            foreach ($this->validTraversable() as $k => $v) {
                yield $v;
            }
        };

        return new ReadonlySequence($generator());
    }

    #endregion extends AbstractArrayMap

    /**
     * @internal
     *
     * Iterates through the map, and checks if the keys follow the policy.
     *
     * @return Generator<TKey,TValue> The keys and values of the map.
     */
    protected function validTraversable(): Generator
    {
        \assert($this->traversable !== null);

        $validKeys = [];
        foreach ($this->traversable as $k => $v) {
            $validKey = $this->getArrayKey($k);
            if (\in_array($validKey, $validKeys, true)) {
                if ($this->duplicateKeyPolicy === DuplicateKeyPolicy::ThrowException) {
                    throw new OutOfBoundsException('The key is duplicated.');
                }

                if ($this->duplicateKeyPolicy === DuplicateKeyPolicy::Ignore) {
                    continue;
                }
            }

            $validKeys[] = $validKey;

            yield $k => $v;
        }
    }
}
