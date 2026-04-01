<?php

declare(strict_types=1);

namespace Manychois\PhpStrong;

use Manychois\PhpStrong\ArrayReaderInterface as IArrayReader;
use Manychois\PhpStrong\Collections\MapInterface as IMap;
use Manychois\PhpStrong\Internal\AbstractArrayReader;
use Override;
use Traversable;

/**
 * Reads nested values from an array or object using dot-separated paths.
 */
class ArrayReader extends AbstractArrayReader
{
    /**
     * @var array<string,mixed>|object
     */
    private array|object $root;

    /**
     * @param array<string,mixed>|object $root The root value to traverse.
     */
    public function __construct(array|object $root)
    {
        parent::__construct();
        $this->root = $root;
    }

    #region extends AbstractArrayReader

    /**
     * @inheritDoc
     */
    #[Override]
    public function at(string $path): IArrayReader
    {
        $value = $this->get($path);
        if (is_array($value) || is_object($value)) {
            // @phpstan-ignore argument.type
            return new self($value);
        }
        throw $this->createMismatchException($path, 'array or object', $value);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    protected function getRoot(): array|object
    {
        return $this->root;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function with(array|IMap $overrides): IArrayReader
    {
        if ($overrides instanceof IMap) {
            $overrides = $overrides->asArray();
        }
        if ($this->root instanceof Traversable) {
            /** @var array<string,mixed> $newRoot */
            $newRoot = [];
            foreach ($this->root as $key => $value) {
                if (is_int($key) || is_string($key)) {
                    $newRoot[$key] = $value;
                }
            }
            foreach ($overrides as $key => $value) {
                $newRoot[$key] = $value;
            }
            // @phpstan-ignore argument.type
            return new self($newRoot);
        }

        throw $this->createMismatchException('', 'array or Traversable object', $this->root);
    }

    #endregion extends AbstractArrayReader
}
