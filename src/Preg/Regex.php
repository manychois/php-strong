<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\Preg;

use RuntimeException;

/**
 * Provides regular expression functions.
 */
class Regex
{
    /**
     * Performs a regular expression match.
     *
     * @param string $subject The input string.
     * @param string $pattern The pattern to search for.
     * @param int    $offset  The offset to start searching from.
     *
     * @return RegexMatch The match result.
     */
    public static function match(string $subject, string $pattern, int $offset = 0): RegexMatch
    {
        $matched = \preg_match($pattern, $subject, $matches, \PREG_OFFSET_CAPTURE, $offset);
        if ($matched === false) {
            throw new RuntimeException(\preg_last_error_msg(), \preg_last_error());
        }

        return new RegexMatch($matches);
    }

    /**
     * Performs a global regular expression match.
     *
     * @param string $subject The input string.
     * @param string $pattern The pattern to search for.
     * @param int    $offset  The offset to start searching from.
     *
     * @return array<int, RegexMatch> A collection of match results.
     */
    public static function matchAll(string $subject, string $pattern, int $offset = 0): array
    {
        $matched = \preg_match_all($pattern, $subject, $matches, \PREG_SET_ORDER | \PREG_OFFSET_CAPTURE, $offset);
        if ($matched === false) {
            throw new RuntimeException(\preg_last_error_msg(), \preg_last_error());
        }

        $regexMatches = [];
        foreach ($matches as $match) {
            $regexMatches[] = new RegexMatch($match);
        }

        return $regexMatches;
    }

    /**
     * Quotes a string for use in a regular expression.
     *
     * @param string $str       The input string.
     * @param string $delimiter The delimiter character.
     *
     * @return string The quoted string.
     */
    public static function quote(string $str, string $delimiter = '/'): string
    {
        return \preg_quote($str, $delimiter);
    }

    /**
     * Performs a regular expression search and replace.
     *
     * @param string $subject     The input string.
     * @param string $pattern     The pattern to search for.
     * @param string $replacement The replacement string.
     * @param int    $limit       The maximum number of replacements to perform. -1 means no limit.
     *
     * @return string The replaced string.
     */
    public static function replace(string $subject, string $pattern, string $replacement, int $limit = -1): string
    {
        $replaced = \preg_replace($pattern, $replacement, $subject, $limit);
        if ($replaced === null) {
            throw new RuntimeException(\preg_last_error_msg(), \preg_last_error());
        }

        return $replaced;
    }

    /**
     * Performs a regular expression search and replace using a callback.
     *
     * @param string   $subject  The input string.
     * @param string   $pattern  The pattern to search for.
     * @param callable $callback The callback function.
     * @param int      $limit    The maximum number of replacements to perform. -1 means no limit.
     *
     * @return string The replaced string.
     *
     * @phpstan-param callable(RegexMatch $match): string $callback
     */
    public static function replaceFn(string $subject, string $pattern, callable $callback, int $limit = -1): string
    {
        $fn = fn (array $matches) => $callback(new RegexMatch($matches));
        $replaced = \preg_replace_callback($pattern, $fn, $subject, $limit, $count, \PREG_OFFSET_CAPTURE);
        if ($replaced === null) {
            throw new RuntimeException(\preg_last_error_msg(), \preg_last_error());
        }

        return $replaced;
    }

    /**
     * Split a string by a regular expression.
     *
     * @param string $subject The input string.
     * @param string $pattern The pattern to search for.
     * @param int    $limit   The maximum number of splits to perform. -1 means no limit.
     *
     * @return array<int, string> The split parts.
     */
    public static function split(string $subject, string $pattern, int $limit = -1): array
    {
        $parts = \preg_split($pattern, $subject, $limit);
        if ($parts === false) {
            throw new RuntimeException(\preg_last_error_msg(), \preg_last_error());
        }

        return $parts;
    }

    /**
     * Split a string by a regular expression and return the offset of each part.
     *
     * @param string $subject The input string.
     * @param string $pattern The pattern to search for.
     * @param int    $limit   The maximum number of splits to perform. -1 means no limit.
     *
     * @return array<int, Capture> The split parts.
     */
    public static function splitOffset(string $subject, string $pattern, int $limit = -1): array
    {
        $parts = \preg_split($pattern, $subject, $limit, \PREG_SPLIT_OFFSET_CAPTURE);
        if ($parts === false) {
            throw new RuntimeException(\preg_last_error_msg(), \preg_last_error());
        }

        return \array_map(fn (array $result) => new Capture($result), $parts);
    }
}
