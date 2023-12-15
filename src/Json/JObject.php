<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\Json;

use InvalidArgumentException;

/**
 * Represents a JSON object.
 */
class JObject
{
    /**
     * @var array<string, mixed>
     */
    private array $data = [];

    /**
     * Resets the object and applies the given data.
     *
     * @param array<string, mixed> $data The JSON data as an associative array.
     */
    public function apply(array $data): void
    {
        if (\array_is_list($data)) {
            throw new InvalidArgumentException('JObject::apply() requires an associative array');
        }

        $this->data = [];
        foreach ($data as $key => $value) {
            if (\is_array($value)) {
                if (\array_is_list($value)) {
                    $this->data[$key] = new JArray();
                } else {
                    $this->data[$key] = new self();
                }
                $this->data[$key]->apply($value);
            } else {
                $this->data[$key] = $value;
            }
        }
    }

    /**
     * Gets all the field names.
     *
     * @return array<int, string> The field names.
     */
    public function fields(): array
    {
        return \array_keys($this->data);
    }

    /**
     * Gets the value of the given field.
     *
     * @param string $name The name of the field.
     *
     * @return mixed The value of the field.
     */
    public function get(string $name): mixed
    {
        if (!\array_key_exists($name, $this->data)) {
            throw new InvalidArgumentException("Field '$name' does not exist.");
        }

        return $this->data[$name];
    }

    /**
     * Gets the value of the given field as an array.
     *
     * @param string $name The name of the field.
     *
     * @return JArray The value of the field.
     */
    public function getArray(string $name): JArray
    {
        $value = $this->get($name);
        if (!($value instanceof JArray)) {
            throw new InvalidArgumentException("Field '$name' is not an array.");
        }

        return $value;
    }

    /**
     * Gets the value of the given field as a boolean.
     *
     * @param string $name The name of the field.
     *
     * @return bool The value of the field.
     */
    public function getBoolean(string $name): bool
    {
        $value = $this->get($name);
        if (!\is_bool($value)) {
            throw new InvalidArgumentException("Field '$name' is not a boolean.");
        }

        return $value;
    }

    /**
     * Gets the value of the given field as a float.
     *
     * @param string $name The name of the field.
     *
     * @return float The value of the field.
     */
    public function getFloat(string $name): float
    {
        $value = $this->get($name);
        if (\is_int($value)) {
            return (float) $value;
        }
        if (\is_float($value)) {
            return $value;
        }
        throw new InvalidArgumentException("Field '$name' is not a float.");
    }

    /**
     * Gets the value of the given field as an integer.
     *
     * @param string $name The name of the field.
     *
     * @return int The value of the field.
     */
    public function getInt(string $name): int
    {
        $value = $this->get($name);
        if (!\is_int($value)) {
            throw new InvalidArgumentException("Field '$name' is not an integer.");
        }

        return $value;
    }

    /**
     * Gets the value of the given field as an object.
     *
     * @param string $name The name of the field.
     *
     * @return JObject The value of the field.
     */
    public function getObject(string $name): JObject
    {
        $value = $this->get($name);
        if (!($value instanceof JObject)) {
            throw new InvalidArgumentException("Field '$name' is not an object.");
        }

        return $value;
    }

    /**
     * Gets the value of the given field as a string.
     *
     * @param string $name The name of the field.
     *
     * @return string The value of the field.
     */
    public function getString(string $name): string
    {
        $value = $this->get($name);
        if (!\is_string($value)) {
            throw new InvalidArgumentException("Field '$name' is not a string.");
        }

        return $value;
    }

    /**
     * Checks if the given field exists.
     *
     * @param string $name The name of the field.
     *
     * @return bool True if the field exists, false otherwise.
     */
    public function hasField(string $name): bool
    {
        return \array_key_exists($name, $this->data);
    }

    /**
     * Checks if the given field is null.
     *
     * @param string $name The name of the field.
     *
     * @return bool True if the field is null, false otherwise.
     */
    public function isNull(string $name): bool
    {
        $value = $this->get($name);

        return $value === null;
    }

    /**
     * Removes the given field.
     *
     * @param string $name The name of the field.
     *
     * @return bool True if the field is removed, false otherwise.
     */
    public function remove(string $name): bool
    {
        if (\array_key_exists($name, $this->data)) {
            unset($this->data[$name]);

            return true;
        }

        return false;
    }

    /**
     * Sets the value of the given field.
     *
     * @param string $name  The name of the field.
     * @param mixed  $value The value of the field.
     */
    public function set(string $name, mixed $value): void
    {
        if (
            \is_null($value) ||
            \is_bool($value) ||
            \is_int($value) ||
            \is_float($value) ||
            \is_string($value) ||
            $value instanceof self ||
            $value instanceof JArray
        ) {
            $this->data[$name] = $value;
        }

        throw new InvalidArgumentException('Value must be null, a scalar, a JArray, or a JObject.');
    }

    /**
     * Converts the JSON object to an associative array.
     *
     * @return array<string, mixed> The JSON data as an associative array.
     */
    public function toArray(): array
    {
        $result = $this->data;
        foreach ($result as $key => $value) {
            if ($value instanceof self) {
                $result[$key] = $value->toArray();
            } elseif ($value instanceof JArray) {
                $result[$key] = $value->toArray();
            }
        }

        return $result;
    }

    /**
     * Returns the JSON representation of the array.
     *
     * @param bool $pretty True to format the JSON string with indentation and line breaks.
     *
     * @return string The JSON representation of the array.
     */
    public function toJson(bool $pretty = false): string
    {
        $flags = \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR;
        if ($pretty) {
            $flags |= \JSON_PRETTY_PRINT;
        }

        return \json_encode($this->toArray(), $flags);
    }
}
