<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\Collections;

use InvalidArgumentException;
use Iterator;
use Manychois\PhpStrong\Collections\MapInterface as IMap;
use Manychois\PhpStrong\Collections\ReadonlyMapInterface as IReadonlyMap;
use Manychois\PhpStrong\Collections\SequenceInterface as ISequence;
use OutOfBoundsException;
use Override;
use RuntimeException;
use SplObjectStorage;
use stdClass;
use WeakMap;
use WeakReference;

/**
 * A map of items keyed by object identity.
 *
 * Optional weak keys or weak values are controlled by the constructor flags `isWeakKey` and `isWeakValue`.
 *
 * @template TKey of object
 * @template TValue
 *
 * @implements IMap<TKey, TValue>
 */
class ObjectMap implements IMap
{
    private static object $destroyed;
    private static object $missing;

    public readonly bool $isWeakKey;
    public readonly bool $isWeakValue;

    private readonly DuplicationPolicy $policy;
    /**
     * @var SplObjectStorage<TKey, TValue|WeakReference<object>>
     */
    private SplObjectStorage $spl;
    /**
     * @var WeakMap<TKey, TValue|WeakReference<object>>
     */
    private WeakMap $weakMap;

    /**
     * Initializes a new object map with the specified source.
     *
     * @param iterable<TKey, TValue> $source The source iterable for the object map.
     * @param DuplicationPolicy $policy The policy for handling duplicate keys.
     *
     * @throws InvalidArgumentException If the key of the source is not an object.
     */
    public function __construct(
        iterable $source = [],
        DuplicationPolicy $policy = DuplicationPolicy::Overwrite,
        bool $isWeakKey = false,
        bool $isWeakValue = false,
    ) {
        if (!isset(self::$destroyed)) {
            self::$destroyed = new stdClass();
        }
        if (!isset(self::$missing)) {
            self::$missing = new stdClass();
        }

        $this->isWeakKey = $isWeakKey;
        $this->isWeakValue = $isWeakValue;
        $this->policy = $policy;
        if ($source instanceof ObjectMap) {
            // @phpstan-ignore assign.propertyType
            $this->spl = clone $source->spl;
            // @phpstan-ignore assign.propertyType
            $this->weakMap = clone $source->weakMap;
        } else {
            $this->spl = new SplObjectStorage();
            $this->weakMap = new WeakMap();
            $this->addRange($source);
        }
    }

    /**
     * Validates the key is an object.
     *
     * @param mixed $key The key to validate.
     * @param string $argName The name of the argument for error messages.
     *
     * @throws InvalidArgumentException If the key is not an object.
     *
     * @phpstan-assert object $key
     */
    protected function validateKey(mixed $key, string $argName = 'Key'): void
    {
        if (!is_object($key)) {
            throw new InvalidArgumentException(
                sprintf('%s must be an object, type %s given', $argName, get_debug_type($key))
            );
        }
    }

    /**
     * Adds a strong key to the map.
     *
     * @param TKey $key The key to add.
     * @param TValue|WeakReference<object> $value The value to add (possibly wrapped when `isWeakValue` is true).
     *
     * @throws InvalidArgumentException If the key already exists and the policy is not to overwrite.
     */
    private function addStrongKey(object $key, mixed $value): void
    {
        if ($this->spl->contains($key)) {
            if ($this->policy === DuplicationPolicy::Overwrite) {
                $this->spl->attach($key, $value);
            } elseif ($this->policy === DuplicationPolicy::Ignore) {
                // do nothing
            } elseif ($this->policy === DuplicationPolicy::ThrowError) {
                throw new InvalidArgumentException('Key already exists');
            }
        } else {
            $this->spl->attach($key, $value);
        }
    }

    /**
     * Adds a weak key to the map.
     *
     * @param TKey $key The key to add.
     * @param TValue|WeakReference<object> $value The value to add.
     *
     * @throws InvalidArgumentException If the key already exists and the policy is not to overwrite.
     */
    private function addWeakKey(object $key, mixed $value): void
    {
        if ($this->weakMap->offsetExists($key)) {
            if ($this->policy === DuplicationPolicy::Overwrite) {
                $this->weakMap->offsetSet($key, $value);
            } elseif ($this->policy === DuplicationPolicy::Ignore) {
                // do nothing
            } elseif ($this->policy === DuplicationPolicy::ThrowError) {
                throw new InvalidArgumentException('Key already exists');
            }
        } else {
            $this->weakMap->offsetSet($key, $value);
        }
    }

    /**
     * Gets the value associated with the specified strong key.
     *
     * May detach the key if `isWeakValue` is true and the stored referent was collected.
     *
     * @param TKey $key The key to get the value for.
     *
     * @return mixed The value associated with the key, or `self::$destroyed` if the value has been garbage collected,
     * or `self::$missing` if the key is not found.
     */
    private function getFromStrongKey(object $key): mixed
    {
        if ($this->spl->contains($key)) {
            $value = $this->spl->offsetGet($key);
            if ($this->isWeakValue && $value instanceof WeakReference) {
                $value = $value->get();
                if ($value === null) {
                    $this->spl->detach($key);
                    return self::$destroyed;
                }
            }
            return $value;
        }
        return self::$missing;
    }

    /**
     * Gets the value associated with the specified weak key.
     *
     * May unset the key if `isWeakValue` is true and the stored referent was collected.
     *
     * @param TKey $key The key to get the value for.
     *
     * @return mixed The value associated with the key, or `self::$destroyed` if the value has been garbage collected,
     * or `self::$missing` if the key is not found.
     */
    private function getFromWeakKey(object $key): mixed
    {
        if ($this->weakMap->offsetExists($key)) {
            $value = $this->weakMap->offsetGet($key);
            if ($this->isWeakValue && $value instanceof WeakReference) {
                $value = $value->get();
                if ($value === null) {
                    $this->weakMap->offsetUnset($key);
                    return self::$destroyed;
                }
            }
            return $value;
        }
        return self::$missing;
    }

    /**
     * @return Iterator<TKey, TValue>
     */
    private function iterateWeakKeys(): Iterator
    {
        $toDestroy = [];
        try {
            foreach ($this->weakMap as $objKey => $value) {
                if ($this->isWeakValue && $value instanceof WeakReference) {
                    $dereferenced = $value->get();
                    if ($dereferenced === null) {
                        $toDestroy[] = $objKey;
                        continue;
                    }
                    $value = $dereferenced;
                }
                // @phpstan-ignore generator.valueType
                yield $objKey => $value;
            }
        } finally {
            foreach ($toDestroy as $key) {
                $this->weakMap->offsetUnset($key);
            }
        }
    }

    /**
     * @return Iterator<TKey, TValue>
     */
    private function iterateStrongKeys(): Iterator
    {
        $toDestroy = [];
        try {
            foreach ($this->spl as $objKey) {
                $value = $this->spl->offsetGet($objKey);
                if ($this->isWeakValue && $value instanceof WeakReference) {
                    $dereferenced = $value->get();
                    if ($dereferenced === null) {
                        $toDestroy[] = $objKey;
                        continue;
                    }
                    $value = $dereferenced;
                }
                // @phpstan-ignore generator.valueType
                yield $objKey => $value;
            }
        } finally {
            foreach ($toDestroy as $key) {
                $this->spl->detach($key);
            }
        }
    }

    #region implements IMap

    /**
     * @inheritDoc
     */
    #[Override]
    public DuplicationPolicy $duplicationPolicy { get => $this->policy; }

    /**
     * @inheritDoc
     */
    #[Override]
    public function add(mixed $key, mixed $value): void
    {
        $this->validateKey($key);
        if ($this->isWeakValue && is_object($value)) {
            $value = WeakReference::create($value);
        }
        if ($this->isWeakKey) {
            $this->addWeakKey($key, $value);
        } else {
            $this->addStrongKey($key, $value);
        }
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function addRange(iterable ...$ranges): void
    {
        foreach ($ranges as $range) {
            foreach ($range as $key => $value) {
                $this->add($key, $value);
            }
        }
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function asArray(): array
    {
        throw new RuntimeException('ObjectMap cannot be converted to an array.');
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function asReadonly(): IReadonlyMap
    {
        return new ReadonlyMap($this);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function clear(): void
    {
        $this->spl = new SplObjectStorage();
        $this->weakMap = new WeakMap();
    }

    /**
     * Returns the number of values in the map.
     * Note that if the map is weak, the count is not guaranteed to be accurate.
     */
    #[Override]
    public function count(): int
    {
        return $this->isWeakKey ? $this->weakMap->count() : $this->spl->count();
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function entries(): ISequence
    {
        $generator = function () {
            foreach ($this->getIterator() as $key => $value) {
                yield new Entry($key, $value);
            }
        };
        return new LazySequence($generator());
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function flip(): Iterator
    {
        foreach ($this->getIterator() as $key => $value) {
            yield $value => $key;
        }
    }

    /**
     * Gets the value associated with the specified key.
     *
     * When `isWeakValue` is true and the referent was collected, the entry is removed and a {@see RuntimeException} is
     * thrown (use {@see nullGet} for a non-throwing null).
     *
     * @param TKey $key The key to get the value for.
     *
     * @return TValue The value associated with the key.
     *
     * @throws InvalidArgumentException If the key is not an object.
     * @throws OutOfBoundsException If the key is not found.
     * @throws RuntimeException If the value has been garbage collected.
     */
    #[Override]
    public function get(mixed $key): mixed
    {
        $this->validateKey($key);
        $value = $this->isWeakKey ? $this->getFromWeakKey($key) : $this->getFromStrongKey($key);
        if ($value === self::$destroyed) {
            throw new RuntimeException('Value has been garbage collected');
        }
        if ($value === self::$missing) {
            throw new OutOfBoundsException('Key not found');
        }
        return $value;
    }

    /**
     * @inheritDoc
     *
     * Dead weak-value entries scheduled during iteration are removed in a `finally` block so cleanup runs even when the
     * consumer stops iterating early (generator close/destruction).
     */
    #[Override]
    public function getIterator(): Iterator
    {
        // Weak-value dereference widens the generator value type vs template `TValue`; inner ignores document this.
        return $this->isWeakKey ? $this->iterateWeakKeys() : $this->iterateStrongKeys();
    }


    /**
     * @inheritDoc
     *
     * When `isWeakValue` is true, may remove the entry if the weak referent was collected (same as {@see get}).
     */
    #[Override]
    public function has(mixed $key): bool
    {
        $this->validateKey($key);
        $value = $this->isWeakKey ? $this->getFromWeakKey($key) : $this->getFromStrongKey($key);
        return $value !== self::$destroyed && $value !== self::$missing;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function keys(): ISequence
    {
        $generator = function () {
            foreach ($this->getIterator() as $key => $value) {
                yield $key;
            }
        };
        return new LazySequence($generator());
    }

    /**
     * Gets the value associated with the specified key, or `null` if the key is not found, or the weak value has been
     * collected.
     *
     * @param TKey $key The key to get the value for.
     *
     * @return ?TValue The value associated with the key, or `null` if the key is not found, or the weak value has been
     * collected.
     *
     * @throws InvalidArgumentException If the key is not an object.
     */
    #[Override]
    public function nullGet(mixed $key): mixed
    {
        $this->validateKey($key);
        $value = $this->isWeakKey ? $this->getFromWeakKey($key) : $this->getFromStrongKey($key);
        if ($value === self::$destroyed) {
            return null;
        }
        if ($value === self::$missing) {
            return null;
        }
        return $value;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function offsetExists(mixed $offset): bool
    {
        $this->validateKey($offset, 'Offset');
        return $this->has($offset);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function offsetGet(mixed $offset): mixed
    {
        $this->validateKey($offset, 'Offset');
        return $this->get($offset);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->validateKey($offset, 'Offset');
        $this->add($offset, $value);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function offsetUnset(mixed $offset): void
    {
        $this->validateKey($offset, 'Offset');
        $this->remove($offset);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function remove(mixed $key): bool
    {
        $this->validateKey($key, 'Key');
        if ($this->isWeakKey) {
            if (!$this->weakMap->offsetExists($key)) {
                return false;
            }
            $this->weakMap->offsetUnset($key);
            return true;
        }
        if (!$this->spl->contains($key)) {
            return false;
        }
        $this->spl->detach($key);
        return true;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function values(): ISequence
    {
        $generator = function () {
            foreach ($this->getIterator() as $value) {
                yield $value;
            }
        };
        return new LazySequence($generator());
    }

    #endregion implements IMap
}
