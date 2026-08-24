<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\Texts;

use RuntimeException;
use Throwable;

/**
 * Represents a regular expression.
 *
 * All methods surface `preg_*` failures as `RuntimeException`s instead of warnings and `false`/`null` returns.
 */
final class Regex
{
    public readonly string $pattern;

    /**
     * Initializes a new instance of the Regex class.
     *
     * @param string $pattern The regular expression pattern, including delimiters and modifiers.
     */
    public function __construct(string $pattern)
    {
        $this->pattern = $pattern;
    }

    /**
     * Escapes a text so that it can be used as a literal in a regular expression.
     *
     * @param string $text The text to escape.
     * @param ?string $delimiter The delimiter character to escape as well. Note that "/" is the most commonly used
     * delimiter, but is not a special regular expression character.
     *
     * @return string The escaped text.
     */
    public static function escape(string $text, ?string $delimiter = null): string
    {
        return preg_quote($text, $delimiter);
    }

    /**
     * Matches the regular expression against a subject string.
     *
     * @param string $subject The subject string to search.
     * @param int $offset The byte offset in the subject at which to start the search. A negative value counts
     * from the end of the subject.
     *
     * @return MatchResult The result of the match.
     *
     * @throws RuntimeException if the pattern is invalid or the match fails.
     */
    public function match(string $subject, int $offset = 0): MatchResult
    {
        $matches = [];
        $this->callNativeRegexFn(
            function () use (&$matches, $subject, $offset): int|false {
                return preg_match($this->pattern, $subject, $matches, \PREG_OFFSET_CAPTURE, $offset);
            }
        );

        return new MatchResult($matches);
    }

    /**
     * Finds all the matches of the regular expression in a subject string.
     *
     * @param string $subject The subject string to search.
     * @param int $offset The byte offset in the subject at which to start the search. A negative value counts
     * from the end of the subject.
     *
     * @return list<MatchResult> The match results, empty if there is no match.
     *
     * @throws RuntimeException if the pattern is invalid or the match fails.
     */
    public function matchAll(string $subject, int $offset = 0): array
    {
        $matches = [];
        $this->callNativeRegexFn(
            function () use (&$matches, $subject, $offset): int|false {
                return preg_match_all(
                    $this->pattern,
                    $subject,
                    $matches,
                    \PREG_PATTERN_ORDER | \PREG_OFFSET_CAPTURE,
                    $offset
                );
            }
        );

        /** @phpstan-var array<int|string, list<array{0:string,1:int}>> $matches */
        $matches = $matches;

        $matchResults = [];
        if (count($matches) > 0) {
            $count = count($matches[0]);
            for ($i = 0; $i < $count; $i++) {
                $matchGroup = [];
                foreach ($matches as $key => $value) {
                    $matchGroup[$key] = $value[$i];
                }
                $matchResults[] = new MatchResult($matchGroup);
            }
        }

        return $matchResults;
    }

    /**
     * Performs a regular expression search and replace.
     *
     * @param string $subject The subject string to search.
     * @param string $replacement The replacement string.
     * @param int $limit The maximum possible replacements. Default is -1 (no limit).
     *
     * @return string The resulting string.
     *
     * @throws RuntimeException if the pattern is invalid or the replacement fails.
     */
    public function replace(string $subject, string $replacement, int $limit = -1): string
    {
        return $this->callNativeRegexFn(
            fn () => preg_replace($this->pattern, $replacement, $subject, $limit)
        );
    }

    /**
     * Performs a regular expression search and replace using a callback.
     *
     * @param string $subject The subject string to search.
     * @param callable $callback The callback that returns the replacement for each match.
     * @param int $limit The maximum possible replacements. Default is -1 (no limit).
     *
     * @return string The resulting string.
     *
     * @throws RuntimeException if the pattern is invalid or the replacement fails.
     *
     * @phpstan-param callable(MatchResult):string $callback
     */
    public function replaceCallback(string $subject, callable $callback, int $limit = -1): string
    {
        $strongCallback = static fn (array $matches): string => $callback(new MatchResult($matches));

        return $this->callNativeRegexFn(
            fn () => preg_replace_callback(
                $this->pattern,
                $strongCallback,
                $subject,
                $limit,
                $count,
                \PREG_OFFSET_CAPTURE
            )
        );
    }

    /**
     * Splits a string by the regular expression.
     *
     * @param string $subject The subject string to split.
     * @param int $limit Only substrings up to limit are returned with the rest of the string being placed in the
     * last substring. A limit of -1 or 0 means "no limit".
     * @param bool $nonEmpty If true, only non-empty substrings are returned.
     *
     * @return list<string> The substrings.
     *
     * @throws RuntimeException if the pattern is invalid or the split fails.
     */
    public function split(string $subject, int $limit = -1, bool $nonEmpty = false): array
    {
        $flags = $nonEmpty ? \PREG_SPLIT_NO_EMPTY : 0;

        return $this->callNativeRegexFn(
            fn () => preg_split($this->pattern, $subject, $limit, $flags)
        );
    }

    /**
     * Calls a native regular expression function and converts its failure signal into an exception.
     * All `preg_*` functions signal failure by returning `false` or `null`, never as a valid result.
     *
     * @param callable $fn The function to call.
     *
     * @return mixed The result of the function call.
     *
     * @throws RuntimeException if the function signals an error.
     *
     * @template TResult
     *
     * @phpstan-param callable():(TResult|false|null) $fn
     *
     * @phpstan-return TResult
     */
    private function callNativeRegexFn(callable $fn): mixed
    {
        /** @var ?Throwable $lastWarning */
        $lastWarning = null;
        set_error_handler(
            static function (int $errno, string $errstr) use (&$lastWarning): bool {
                $lastWarning = new RuntimeException($errstr, $errno);

                return true;
            },
            \E_WARNING
        );

        try {
            $result = $fn();
        } finally {
            restore_error_handler();
        }

        if ($result === false || $result === null) {
            if ($lastWarning !== null) {
                throw $lastWarning;
            }

            throw new RuntimeException(preg_last_error_msg(), preg_last_error());
        }

        return $result;
    }
}
