<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\Collections;

use BadMethodCallException;
use InvalidArgumentException;
use Iterator;
use Manychois\PhpStrong\Collections\MapInterface as IMap;
use Manychois\PhpStrong\Collections\ReadonlyMapInterface as IReadonlyMap;
use Manychois\PhpStrong\Collections\SequenceInterface as ISequence;
use OutOfBoundsException;
use Override;
use RuntimeException;
use SplObjectStorage;

/**
 * A map of items keyed by object identity.
 * Implementation is not yet provided.
 *
 * @template TKey of object
 * @template TValue
 *
 * @implements IMap<TKey, TValue>
 */
class ObjectMap implements IMap
{
    /**
     * @var SplObjectStorage<TKey, TValue>
     */
    private SplObjectStorage $source;
    private readonly DuplicationPolicy $policy;

    /**
     * Initializes a new object map with the specified source.
     *
     * @param iterable<TKey, TValue> $source The source iterable for the object map.
     * @param DuplicationPolicy $policy The policy for handling duplicate keys.
     *
     * @throws BadMethodCallException If the source is a non-empty array (population not implemented).
     * @throws InvalidArgumentException If the key is not an object (when population is implemented).
     */
    public function __construct(iterable $source = [], DuplicationPolicy $policy = DuplicationPolicy::Overwrite)
    {
        $this->policy = $policy;
        if ($source instanceof ObjectMap) {
            // @phpstan-ignore assign.propertyType
            $this->source = clone $source->source;
        } else {
            $this->source = new SplObjectStorage();
            foreach ($source as $key => $value) {
                $this->source->attach($key, $value);
            }
        }
    }

    /**
     * Validates the key is an object.
     *
     * @param mixed $key The key to validate.
     * @param string $argName The name of the argument for error messages.
     *
     * @throws InvalidArgumentException If the key is not an object.
     */
    protected function validateKey(mixed $key, string $argName = 'Key'): void
    {
        if (!is_object($key)) {
            throw new InvalidArgumentException(
                sprintf('%s must be an object, type %s given', $argName, get_debug_type($key))
            );
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
        if ($this->source->contains($key)) {
            if ($this->policy === DuplicationPolicy::Overwrite) {
                $this->source->attach($key, $value);
            } elseif ($this->policy === DuplicationPolicy::Ignore) {
                // do nothing
            } elseif ($this->policy === DuplicationPolicy::ThrowError) {
                throw new InvalidArgumentException('Key already exists');
            }
        } else {
            $this->source->attach($key, $value);
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
        $this->source = new SplObjectStorage();
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function count(): int
    {
        return $this->source->count();
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
     * @inheritDoc
     */
    #[Override]
    public function get(mixed $key): mixed
    {
        $this->validateKey($key);
        if (!$this->source->contains($key)) {
            throw new OutOfBoundsException('Key not found');
        }
        return $this->source->offsetGet($key);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function getIterator(): Iterator
    {
        foreach ($this->source as $objKey) {
            yield $objKey => $this->source->offsetGet($objKey);
        }
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function has(mixed $key): bool
    {
        $this->validateKey($key);
        return $this->source->contains($key);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function keys(): ISequence
    {
        $generator = function () {
            foreach ($this->source as $objKey) {
                yield $objKey;
            }
        };
        return new LazySequence($generator());
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function nullGet(mixed $key): mixed
    {
        $this->validateKey($key);
        if (!$this->source->contains($key)) {
            return null;
        }
        return $this->source->offsetGet($key);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function offsetExists(mixed $offset): bool
    {
        $this->validateKey($offset, 'Offset');
        return $this->source->contains($offset);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function offsetGet(mixed $offset): mixed
    {
        $this->validateKey($offset, 'Offset');
        if (!$this->source->contains($offset)) {
            throw new OutOfBoundsException('Key not found');
        }
        return $this->source->offsetGet($offset);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->validateKey($offset, 'Offset');
        assert(is_object($offset));
        $this->source->attach($offset, $value);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function offsetUnset(mixed $offset): void
    {
        $this->validateKey($offset, 'Offset');
        $this->source->detach($offset);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function remove(mixed $key): bool
    {
        $this->validateKey($key, 'Key');
        if (!$this->source->contains($key)) {
            return false;
        }
        $this->source->detach($key);
        return true;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function values(): ISequence
    {
        $generator = function () {
            foreach ($this->source as $objKey) {
                yield $this->source->offsetGet($objKey);
            }
        };
        return new LazySequence($generator());
    }

    #endregion implements IMap
}
