<?php

namespace Manychois\PhpStrong;

use TypeError;

class ArrayAccessor
{
    private array $inner;

    public function __construct(array $inner = [])
    {
        $this->inner = $inner;
    }

    public function bool(string $key, bool $default = false): bool
    {
        if (array_key_exists($key, $this->inner)) {
            $value = $this->inner[$key];
            if (is_scalar($value)) {
                $value = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                if (is_bool($value)) {
                    return $value;
                }
                return $default;
            }
            throw new TypeError(sprintf('The value associated with key "%s" is not a boolean, but %s.', $key, get_debug_type($value)));
        }
        return $default;
    }

    public function int(string $key, int $default = 0): int
    {
        if (array_key_exists($key, $this->inner)) {
            $value = $this->inner[$key];
            if (is_scalar($value)) {
                return (int)$value;
            }
            throw new TypeError(sprintf('The value associated with key "%s" is not an integer, but %s.', $key, get_debug_type($value)));
        }
        return $default;
    }

    public function string(string $key, string $default = ''): string
    {
        if (array_key_exists($key, $this->inner)) {
            $value = $this->inner[$key];
            if (is_scalar($value)) {
                return (string)$value;
            }
            throw new TypeError(sprintf('The value associated with key "%s" is not a string, but %s.', $key, get_debug_type($value)));
        }
        return $default;
    }
}
