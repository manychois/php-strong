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

/**
 * A map of items with integer keys.
 *
 * @template T
 *
 * @implements IMap<int, T>
 */
class IntMap implements IMap
{
    /**
     * @var array<int, T>
     */
    private array $source = [];

    private readonly DuplicationPolicy $policy;

    /**
     * Initializes a new int map with the specified source.
     *
     * @param iterable<int, T> $source The source iterable for the int map.
     * @param DuplicationPolicy $policy The policy for handling duplicate keys.
     *
     * @throws InvalidArgumentException If the key is not an integer.
     */
    public function __construct(iterable $source = [], DuplicationPolicy $policy = DuplicationPolicy::Overwrite)
    {
        $this->policy = $policy;
        if (is_array($source)) {
            $this->source = $source;
        } else {
            foreach ($source as $key => $value) {
                $this->add($key, $value);
            }
        }
    }

    /**
     * Validates the key is an integer.
     *
     * @param mixed $key The key to validate.
     * @param string $argName The name of the argument for error messages.
     *
     * @throws InvalidArgumentException If the key is not an integer.
     */
    protected function validateKey(mixed $key, string $argName = 'Key'): void
    {
        if (!is_int($key)) {
            throw new InvalidArgumentException(
                sprintf('%s must be an integer, type %s given', $argName, get_debug_type($key))
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
        if (array_key_exists($key, $this->source)) {
            if ($this->policy === DuplicationPolicy::Overwrite) {
                $this->source[$key] = $value;
            } elseif ($this->policy === DuplicationPolicy::Ignore) {
                // do nothing
            } elseif ($this->policy === DuplicationPolicy::ThrowError) {
                throw new InvalidArgumentException(sprintf('Key %d already exists', $key));
            }
        } else {
            $this->source[$key] = $value;
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
        return $this->source;
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
        $this->source = [];
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function count(): int
    {
        return count($this->source);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function entries(): ISequence
    {
        $generator = function () {
            foreach ($this->source as $key => $value) {
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
        foreach ($this->source as $key => $value) {
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
        if (!array_key_exists($key, $this->source)) {
            throw new OutOfBoundsException(sprintf('Key %d not found', $key));
        }
        return $this->source[$key];
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function getIterator(): Iterator
    {
        foreach ($this->source as $key => $value) {
            yield $key => $value;
        }
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function has(mixed $key): bool
    {
        $this->validateKey($key);
        return array_key_exists($key, $this->source);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function keys(): ISequence
    {
        $generator = function () {
            foreach ($this->source as $key => $value) {
                yield $key;
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
        if (!array_key_exists($key, $this->source)) {
            return null;
        }
        return $this->source[$key];
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function offsetExists(mixed $offset): bool
    {
        $this->validateKey($offset, 'Offset');
        return array_key_exists($offset, $this->source);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function offsetGet(mixed $offset): mixed
    {
        $this->validateKey($offset, 'Offset');
        if (!array_key_exists($offset, $this->source)) {
            throw new OutOfBoundsException(sprintf('Offset %d not found', $offset));
        }
        return $this->source[$offset];
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function offsetSet(mixed $offset, mixed $value): void
    {
        if ($offset === null) {
            throw new InvalidArgumentException('Offset must be an integer');
        }
        $this->add($offset, $value);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function offsetUnset(mixed $offset): void
    {
        $this->remove($offset);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function remove(mixed $key): bool
    {
        $this->validateKey($key);
        if (!array_key_exists($key, $this->source)) {
            return false;
        }
        unset($this->source[$key]);
        return true;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function values(): ISequence
    {
        return new LazySequence($this->source);
    }

    #endregion implements IMap
}
