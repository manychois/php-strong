<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\Collections\Internal;

use Iterator;
use Manychois\PhpStrong\Collections\Entry;
use Override;

/**
 * An iterator that caches the source iterator.
 *
 * @template TKey
 * @template TValue
 *
 * @implements Iterator<TKey, TValue>
 */
class CacheIterator implements Iterator
{
    private readonly Iterator $source;
    /**
     * @var list<Entry<TKey, TValue>>
     */
    private array $cache = [];
    private bool $rewinded = false;
    private int $index = 0;

    /**
     * @param Iterator<TKey, TValue> $source Iterator whose values are cached on read.
     */
    public function __construct(Iterator $source)
    {
        $this->source = $source;
    }

    #region implements Iterator

    /**
     * @inheritDoc
     */
    #[Override]
    public function current(): mixed
    {
        if ($this->index < count($this->cache)) {
            return $this->cache[$this->index]->value;
        }
        $value = $this->source->current();
        $key = $this->source->key();
        $this->cache[] = new Entry($key, $value);
        $this->index++;
        return $value;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function next(): void
    {
        if ($this->index < count($this->cache)) {
            $this->index++;
        } else {
            $this->source->next();
        }
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function key(): mixed
    {
        if ($this->index < count($this->cache)) {
            return $this->cache[$this->index]->key;
        }
        return $this->source->key();
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function valid(): bool
    {
        if ($this->index < count($this->cache)) {
            return true;
        }
        return $this->source->valid();
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function rewind(): void
    {
        if (!$this->rewinded) {
            $this->rewinded = true;
            $this->source->rewind();
        }
        $this->index = 0;
    }

    #endregion implements Iterator
}
