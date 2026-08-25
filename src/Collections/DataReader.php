<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\Collections;

use InvalidArgumentException;
use Manychois\PhpStrong\Collections\DataReaderInterface as IDataReader;
use Manychois\PhpStrong\Collections\Internal\AbstractDataReader;
use Override;

/**
 * Provides strongly typed access to the values of a string-keyed array or an object.
 *
 * A key may be written in dot notation to reach a value nested inside the source, e.g. `user.address.city`.
 *
 * An object is read through its offsets when it implements `ArrayAccess`, then through its public properties, and
 * finally through `__isset()` and `__get()` when it defines both. A non-public or uninitialized property counts as
 * absent, which keeps a value object such as a date a leaf rather than a node to descend into.
 */
final class DataReader extends AbstractDataReader
{
    /**
     * @var array<string,mixed>|object
     */
    private readonly array|object $data;

    /**
     * Initializes a new instance of the DataReader class.
     *
     * @param array|object $source The array or object to read from. All keys of an array must be strings.
     *
     * @throws InvalidArgumentException if any key of an array is not a string.
     *
     * @phpstan-param array<array-key,mixed>|object $source
     */
    public function __construct(array|object $source)
    {
        $this->data = is_array($source) ? self::stringKeyed($source) : $source;
    }

    #region extends AbstractDataReader

    /**
     * @inheritDoc
     */
    #[Override]
    protected function createReader(array|object $source): IDataReader
    {
        return new self($source);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    protected function source(): array|object
    {
        return $this->data;
    }

    #endregion extends AbstractDataReader
}
