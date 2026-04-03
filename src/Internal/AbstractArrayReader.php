<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\Internal;

use ArrayAccess;
use Manychois\PhpStrong\ArrayReaderInterface as IArrayReader;
use OutOfBoundsException;
use Override;
use stdClass;
use Stringable;
use UnexpectedValueException;

/**
 * Reads nested values from an array using dot-separated paths.
 */
abstract class AbstractArrayReader implements IArrayReader
{
    /**
     * @var stdClass The value to return when a key is not found.
     */
    protected static stdClass $missingValue;

    /**
     * Initializes an array reader.
     */
    public function __construct()
    {
        if (!isset(static::$missingValue)) {
            static::$missingValue = new stdClass();
        }
    }

    /**
     * Gets the root value of the array reader.
     *
     * @return object|array<string,mixed> The root value.
     */
    abstract protected function getRoot(): object|array;

    /**
     * Gets the value at the given dot-separated path, or missing if the path cannot be resolved.
     *
     * @param string $path The path, e.g. `user.address.city`.
     *
     * @return mixed The resolved value, or {@see static::$missingValue} when the path cannot be resolved.
     */
    protected function getOrMissingValue(string $path): mixed
    {
        $segments = $this->splitPath($path);
        $current = $this->getRoot();
        if ($segments === []) {
            return $current;
        }

        foreach ($segments as $i => $key) {
            if ($i === 0 && $key === self::ROOT_KEY) {
                continue;
            }
            if (is_array($current)) {
                if (!array_key_exists($key, $current)) {
                    return static::$missingValue;
                }
                $current = $current[$key];
            } elseif ($current instanceof ArrayAccess) {
                if (!$current->offsetExists($key)) {
                    return static::$missingValue;
                }
                $current = $current->offsetGet($key);
            } elseif (is_object($current)) {
                if (!property_exists($current, $key)) {
                    return static::$missingValue;
                }
                // @phpstan-ignore property.dynamicName
                $current = $current->{$key};
            } else {
                return static::$missingValue;
            }
        }

        return $current;
    }

    /**
     * Creates a mismatch exception.
     *
     * @param string $path The path.
     * @param string $expectedType The expected type.
     * @param mixed $value The value.
     *
     * @return UnexpectedValueException The mismatch exception.
     */
    protected function createMismatchException(
        string $path,
        string $expectedType,
        mixed $value
    ): UnexpectedValueException {
        if ($path === '') {
            $path = 'The root';
        } else {
            $path = sprintf('The path "%s"', $path);
        }
        $article = in_array($expectedType[0], ['a', 'e', 'i', 'o', 'u'], true) ? 'an' : 'a';
        return new UnexpectedValueException(
            sprintf('%s is not %s %s; got %s', $path, $article, $expectedType, get_debug_type($value))
        );
    }

    /**
     * @return list<string>
     */
    private function splitPath(string $path): array
    {
        $parts = explode('.', $path);
        $segments = [];
        foreach ($parts as $part) {
            if ($part !== '') {
                $segments[] = $part;
            }
        }

        return $segments;
    }

    #region implements IArrayReader

    /**
     * @inheritDoc
     */
    #[Override]
    public function array(string $path): array
    {
        $value = $this->get($path);
        if (is_array($value)) {
            return $value;
        }
        throw self::createMismatchException($path, 'array', $value);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function arrayOrNull(string $path): ?array
    {
        $value = $this->getOrMissingValue($path);
        if (is_array($value)) {
            return $value;
        }
        return null;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function asBool(string $path, bool $default = false): bool
    {
        $value = $this->getOrMissingValue($path);
        if ($value === static::$missingValue) {
            return $default;
        }
        $value = filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
        if (is_bool($value)) {
            return $value;
        }
        return $default;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function asFloat(string $path, float $default = 0.0): float
    {
        $value = $this->getOrMissingValue($path);
        if ($value === static::$missingValue) {
            return $default;
        }
        $value = filter_var($value, FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE);
        if (is_float($value)) {
            return $value;
        }
        return $default;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function asInt(string $path, int $default = 0): int
    {
        $value = $this->getOrMissingValue($path);
        if ($value === static::$missingValue) {
            return $default;
        }
        $value = filter_var($value, FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE);
        if (is_int($value)) {
            return $value;
        }
        return $default;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function asString(string $path, string $default = ''): string
    {
        $value = $this->getOrMissingValue($path);
        if ($value === static::$missingValue) {
            return $default;
        }
        if (is_string($value)) {
            return $value;
        }
        if (is_scalar($value)) {
            return (string) $value;
        }
        if ($value === null) {
            return $default;
        }
        if ($value instanceof Stringable) {
            return $value->__toString();
        }
        return $default;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function bool(string $path): bool
    {
        $value = $this->get($path);
        if (is_bool($value)) {
            return $value;
        }
        throw self::createMismatchException($path, 'boolean', $value);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function boolOrNull(string $path): ?bool
    {
        $value = $this->getOrMissingValue($path);
        if (is_bool($value)) {
            return $value;
        }
        return null;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function callable(string $path): callable
    {
        $value = $this->get($path);
        if (is_callable($value)) {
            return $value;
        }
        throw self::createMismatchException($path, 'callable', $value);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function callableOrNull(string $path): ?callable
    {
        $value = $this->getOrMissingValue($path);
        if (is_callable($value)) {
            return $value;
        }
        return null;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function float(string $path): float
    {
        $value = $this->get($path);
        if (is_float($value)) {
            return $value;
        }
        throw self::createMismatchException($path, 'float', $value);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function floatOrNull(string $path): ?float
    {
        $value = $this->getOrMissingValue($path);
        if (is_float($value)) {
            return $value;
        }
        return null;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function get(string $path): mixed
    {
        $value = $this->getOrMissingValue($path);
        if ($value === static::$missingValue) {
            throw new OutOfBoundsException(sprintf('Path "%s" not found', $path));
        }
        return $value;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function getOrNull(string $path): mixed
    {
        $value = $this->getOrMissingValue($path);
        return $value === static::$missingValue ? null : $value;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function has(string $path): bool
    {
        $value = $this->getOrMissingValue($path);
        return $value !== static::$missingValue && $value !== null;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function instanceOf(string $path, string $class): object
    {
        $value = $this->get($path);
        if (!is_object($value)) {
            throw self::createMismatchException($path, 'object', $value);
        }
        if (!($value instanceof $class)) {
            throw self::createMismatchException($path, 'instance of ' . $class, $value);
        }
        return $value;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function instanceOfOrNull(string $path, string $class): ?object
    {
        $value = $this->getOrMissingValue($path);
        if (is_object($value) && $value instanceof $class) {
            return $value;
        }
        return null;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function int(string $path): int
    {
        $value = $this->get($path);
        if (is_int($value)) {
            return $value;
        }
        throw self::createMismatchException($path, 'integer', $value);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function intOrNull(string $path): ?int
    {
        $value = $this->getOrMissingValue($path);
        if (is_int($value)) {
            return $value;
        }
        return null;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function object(string $path): object
    {
        $value = $this->get($path);
        if (is_object($value)) {
            return $value;
        }
        throw self::createMismatchException($path, 'object', $value);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function objectOrNull(string $path): ?object
    {
        $value = $this->getOrMissingValue($path);
        if ($value === static::$missingValue) {
            return null;
        }
        if (is_object($value)) {
            return $value;
        }
        return null;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function string(string $path): string
    {
        $value = $this->get($path);
        if (is_string($value)) {
            return $value;
        }
        throw self::createMismatchException($path, 'string', $value);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function stringOrNull(string $path): ?string
    {
        $value = $this->getOrMissingValue($path);
        if (is_string($value)) {
            return $value;
        }
        return null;
    }

    #endregion implements IArrayReader
}
