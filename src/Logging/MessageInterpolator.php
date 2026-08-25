<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\Logging;

use DateTimeInterface;
use Stringable;

/**
 * Replaces PSR-3 `{placeholder}` tokens in a message with context values.
 */
final class MessageInterpolator
{
    /**
     * Interpolates context values into the message.
     *
     * @param string $message Message containing `{key}` placeholders.
     * @param array<string, mixed> $context Values keyed by placeholder name; keys absent from the context are left
     * untouched.
     *
     * @return string The message with placeholders replaced.
     */
    public function interpolate(string $message, array $context): string
    {
        if (!str_contains($message, '{')) {
            return $message;
        }
        $replace = [];
        foreach ($context as $key => $value) {
            $replace['{' . $key . '}'] = $this->stringify($value);
        }

        return strtr($message, $replace);
    }

    /**
     * Converts a context value to its placeholder text.
     *
     * Strings and `Stringable` objects are used verbatim, `DateTimeInterface` is formatted as RFC 3339, and everything
     * else is JSON-encoded (`null`, `true`, numbers, arrays, objects).
     *
     * @param mixed $value The context value.
     *
     * @return string The replacement text.
     */
    private function stringify(mixed $value): string
    {
        if (is_string($value) || $value instanceof Stringable) {
            return (string) $value;
        }
        if ($value instanceof DateTimeInterface) {
            return $value->format(DateTimeInterface::RFC3339);
        }
        $json = json_encode($value, \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE);

        return $json === false ? '[' . get_debug_type($value) . ']' : $json;
    }
}
