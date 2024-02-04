<?php

declare(strict_types=1);

namespace Manychois\PhpStrong;

use InvalidArgumentException;
use Manychois\PhpStrong\Preg\Regex;

/**
 * Provides string functions.
 */
class StringUtility
{
    private const ENCODING = 'UTF-8';

    /**
     * Returns the character by Unicode code point.
     *
     * @param int $codepoint The Unicode code point.
     *
     * @return string The character.
     */
    public static function chr(int $codepoint): string
    {
        if ($codepoint < 0) {
            throw new InvalidArgumentException('Codepoint cannot be negative.');
        }

        if ($codepoint < 256) {
            return \chr($codepoint);
        }

        $ch = \mb_chr($codepoint, self::ENCODING);
        if ($ch === false) {
            throw new InvalidArgumentException(\sprintf('Invalid codepoint for %s.', self::ENCODING));
        }

        return $ch;
    }

    /**
     * Returns an array of characters from a string.
     *
     * @param string $input  The input string.
     * @param int    $length How many characters to put into each chunk.
     *
     * @return array<int, string> The array of characters.
     */
    public static function chunk(string $input, int $length = 1): array
    {
        if ($length < 1) {
            throw new InvalidArgumentException('$length cannot be less than 1.');
        }

        return \mb_str_split($input, $length, self::ENCODING);
    }

    /**
     * Joins a list of strings with a glue.
     *
     * @param string               $glue     The glue string used to join the parts.
     * @param string|array<string> ...$parts The parts to join. If a part is an array of strings, it will be flattened.
     *
     * @return string The joined string.
     */
    public static function join(string $glue, string|array ...$parts): string
    {
        $values = \array_map(fn ($s) => \is_array($s) ? \implode($glue, $s) : $s, $parts);

        return \implode($glue, $values);
    }

    /**
     * Gets the length of a string.
     *
     * @param string $input The input string.
     *
     * @return int The length.
     */
    public static function length(string $input): int
    {
        return \mb_strlen($input, self::ENCODING);
    }

    /**
     * Gets the unicode code point of the first character of a string.
     *
     * @param string $input The input string.
     *
     * @return int The code point.
     */
    public static function ord(string $input): int
    {
        if ($input === '') {
            throw new InvalidArgumentException('Input cannot be empty.');
        }

        $codepoint = \mb_ord($input, self::ENCODING);
        if ($codepoint === false) {
            throw new InvalidArgumentException('Invalid UTF-8 string.');
        }

        return $codepoint;
    }

    /**
     * Pads a string to a certain length with another string on both sides.
     *
     * @param string $input     The input string.
     * @param int    $length    The length of the resulting string.
     * @param string $padString The string to pad the input string with.
     *
     * @return string The padded string.
     */
    public static function pad(string $input, int $length, string $padString = ' '): string
    {
        if ($length < 0) {
            throw new InvalidArgumentException('$length cannot be negative.');
        }
        if ($padString === '') {
            throw new InvalidArgumentException('$padString cannot be empty.');
        }

        $sLen = self::length($input);
        $pLen = self::length($padString);
        while ($sLen < $length) {
            $diff = \min($length - $sLen, $pLen);
            if ($diff === $pLen) {
                $input .= $padString;
            } else {
                $input .= self::substring($padString, 0, $diff);
            }
            $sLen += $diff;
            if ($sLen < $length) {
                $diff = \min($length - $sLen, $pLen);
                if ($diff === $pLen) {
                    $input = $padString . $input;
                } else {
                    $input = self::substring($padString, 0, $diff) . $input;
                }
                $sLen += $diff;
            }
        }

        return $input;
    }

    /**
     * Pads a string to a certain length with another string on the left.
     *
     * @param string $input     The input string.
     * @param int    $length    The length of the resulting string.
     * @param string $padString The string to pad the input string with.
     *
     * @return string The padded string.
     */
    public static function padLeft(string $input, int $length, string $padString = ' '): string
    {
        if ($length < 0) {
            throw new InvalidArgumentException('$length cannot be negative.');
        }
        if ($padString === '') {
            throw new InvalidArgumentException('$padString cannot be empty.');
        }

        $sLen = self::length($input);
        $pLen = self::length($padString);
        while ($sLen < $length) {
            $diff = \min($length - $sLen, $pLen);
            if ($diff === $pLen) {
                $input = $padString . $input;
            } else {
                $input = self::substring($padString, 0, $diff) . $input;
            }
            $sLen += $diff;
        }

        return $input;
    }

    /**
     * Pads a string to a certain length with another string on the right.
     *
     * @param string $input     The input string.
     * @param int    $length    The length of the resulting string.
     * @param string $padString The string to pad the input string with.
     *
     * @return string The padded string.
     */
    public static function padRight(string $input, int $length, string $padString = ' '): string
    {
        if ($length < 0) {
            throw new InvalidArgumentException('$length cannot be negative.');
        }
        if ($padString === '') {
            throw new InvalidArgumentException('$padString cannot be empty.');
        }

        $sLen = self::length($input);
        $pLen = self::length($padString);
        while ($sLen < $length) {
            $diff = \min($length - $sLen, $pLen);
            if ($diff === $pLen) {
                $input .= $padString;
            } else {
                $input .= self::substring($padString, 0, $diff);
            }
            $sLen += $diff;
        }

        return $input;
    }

    /**
     * Replaces all occurrences of the search string with the replacement string.
     *
     * @param string $subject The string being searched and replaced on.
     * @param string $search  The value being searched for.
     * @param string $replace The replacement value that replaces found search value.
     *
     * @return string The result string.
     */
    public static function replace(string $subject, string $search, string $replace): string
    {
        if ($search === '') {
            throw new InvalidArgumentException('$search cannot be empty.');
        }

        return \str_replace($search, $replace, $subject);
    }

    /**
     * Splits a string by a separator.
     *
     * @param string $input     The input string.
     * @param string $separator The separator.
     * @param int    $limit     The maximum number of elements to return. 0 means no limit.
     *
     * @return array<int, string> The array of split parts.
     */
    public static function split(string $input, string $separator, int $limit = 0): array
    {
        if ($separator === '') {
            throw new InvalidArgumentException('Separator cannot be empty.');
        }
        if ($limit <= 0) {
            $limit = \PHP_INT_MAX;
        }

        return \explode($separator, $input, $limit);
    }

    /**
     * Gets part of a string.
     *
     * @param string   $input  The input string.
     * @param int      $start  The start position.
     * @param int|null $length Maximum number of characters to extract.
     *                         If null, extract all characters to the end of the string.
     *
     * @return string The portion of string.
     */
    public static function substring(string $input, int $start, ?int $length = null): string
    {
        if ($start < 0) {
            throw new InvalidArgumentException('$start cannot be negative.');
        }
        if ($length < 0) {
            throw new InvalidArgumentException('$length cannot be negative.');
        }

        return \mb_substr($input, $start, $length, self::ENCODING);
    }

    /**
     * Makes a string lowercase.
     *
     * @param string $input The input string.
     *
     * @return string The lowercased string.
     */
    public static function toLowerCase(string $input): string
    {
        return \mb_strtolower($input, self::ENCODING);
    }

    /**
     * Makes a string uppercase.
     *
     * @param string $input The input string.
     *
     * @return string The uppercased string.
     */
    public static function toUpperCase(string $input): string
    {
        return \mb_strtoupper($input, self::ENCODING);
    }

    /**
     * Strips whitespace (or other characters) from the beginning and the end of a string.
     *
     * @param string $input The input string.
     * @param string $chars The characters to strip. Default is " \t\n\r\0\v".
     *
     * @return string The trimmed string.
     */
    public static function trim(string $input, string $chars = " \t\n\r\0\v"): string
    {
        if ($chars === '') {
            throw new InvalidArgumentException('$chars cannot be empty.');
        }
        $group = '[' . Regex::quote($chars) . ']+';
        $pattern = '/[^' . $group . '|' . $group . '$]/u';

        return Regex::replace($input, $pattern, '', 1);
    }

    /**
     * Strips whitespace (or other characters) from the beginning of a string.
     *
     * @param string $input The input string.
     * @param string $chars The characters to strip. Default is " \t\n\r\0\v".
     *
     * @return string The trimmed string.
     */
    public static function trimLeft(string $input, string $chars = " \t\n\r\0\v"): string
    {
        if ($chars === '') {
            throw new InvalidArgumentException('$chars cannot be empty.');
        }
        $pattern = '/^[' . Regex::quote($chars) . ']+/u';

        return Regex::replace($input, $pattern, '', 1);
    }

    /**
     * Strips whitespace (or other characters) from the end of a string.
     *
     * @param string $input The input string.
     * @param string $chars The characters to strip. Default is " \t\n\r\0\v".
     *
     * @return string The trimmed string.
     */
    public static function trimRight(string $input, string $chars = " \t\n\r\0\v"): string
    {
        if ($chars === '') {
            throw new InvalidArgumentException('$chars cannot be empty.');
        }
        $pattern = '/[' . Regex::quote($chars) . ']+$/u';

        return Regex::replace($input, $pattern, '', 1);
    }
}
