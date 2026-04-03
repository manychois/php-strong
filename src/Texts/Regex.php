<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\Texts;

use Manychois\PhpStrong\Collections\ArrayList;
use Manychois\PhpStrong\Collections\ListInterface as IList;
use RuntimeException;
use Throwable;

/**
 * Represents a regular expression.
 */
class Regex
{
    public readonly string $pattern;
    private ?Throwable $lastWarning = null;

    /**
     * Initializes a new instance of the Regex class.
     *
     * @param string $pattern The regular expression pattern.
     */
    public function __construct(string $pattern)
    {
        $this->pattern = $pattern;
    }

    /**
     * Escapes a text so that it can be used as a literal in a regular expression.
     *
     * @param string $text The text to escape.
     * @param ?string $delimiter The delimiter character. Note that "/" is the most commonly used delimiter, but is not
     *                           a special regular expression character.
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
     * @param int $offset The offset in the subject at which to start the search.
     *
     * @return MatchResult The result of the match.
     */
    public function match(string $subject, int $offset = 0): MatchResult
    {
        $matches = [];
        $this->callNativeRegexFn(
            false,
            function () use (&$matches, $subject, $offset): int|false {
                return preg_match($this->pattern, $subject, $matches, PREG_OFFSET_CAPTURE, $offset);
            }
        );

        return new MatchResult($matches);
    }

    /**
     * Find all the matches of the regular expression in a subject string.
     *
     * @param string $subject The subject string to search.
     * @param int $offset The offset in the subject at which to start the search.
     *
     * @return IList<MatchResult> The match results.
     */
    public function matchAll(string $subject, int $offset = 0): IList
    {
        $matches = [];
        $this->callNativeRegexFn(
            false,
            function () use (&$matches, $subject, $offset): int|false {
                return preg_match_all($this->pattern, $subject, $matches, PREG_OFFSET_CAPTURE, $offset);
            }
        );

        /** @phpstan-var array<int|string, array<int, array{0: string, 1: int}>> $matches */
        $matches = $matches;

        $matchResults = new ArrayList();
        if (count($matches) > 0) {
            $count = count($matches[0]);
            for ($i = 0; $i < $count; $i++) {
                $matchGroup = [];
                foreach ($matches as $key => $value) {
                    $matchGroup[$key] = $value[$i];
                }
                $matchResults->add(new MatchResult($matchGroup));
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
     */
    public function replace(string $subject, string $replacement, int $limit = -1): string
    {
        $result = $this->callNativeRegexFn(
            null,
            fn() => preg_replace($this->pattern, $replacement, $subject, $limit)
        );

        assert($result !== null);
        return $result;
    }

    /**
     * Perform a regular expression search and replace using a callback.
     *
     * @param string $subject The subject string to search.
     * @param callable $callback The callback that will be called for each match.
     * @param int $limit The maximum possible replacements. Default is -1 (no limit).
     *
     * @return string The resulting string.
     *
     * @phpstan-param callable(MatchResult):string $callback
     */
    public function replaceCallback(string $subject, callable $callback, int $limit = -1): string
    {
        $strongCallback = static fn(array $matches) => $callback(new MatchResult($matches));
        $result = $this->callNativeRegexFn(
            null,
            fn() => preg_replace_callback(
                $this->pattern,
                $strongCallback,
                $subject,
                $limit,
                $count,
                PREG_OFFSET_CAPTURE
            )
        );

        assert($result !== null);
        return $result;
    }

    /**
     * Splits a string by a regular expression.
     *
     * @param string $subject The subject string to split.
     * @param int $limit Only substrings up to limit are returned with the rest of the string being placed in the last
     *                   substring. A limit of -1 or 0 means "no limit".
     * @param bool $nonEmpty If true, only non-empty substrings are returned.
     *
     * @return IList<string> A sequence of substrings.
     */
    public function split(string $subject, int $limit = -1, bool $nonEmpty = false): IList
    {
        $flags = $nonEmpty ? PREG_SPLIT_NO_EMPTY : 0;
        $result = $this->callNativeRegexFn(
            false,
            fn() => preg_split($this->pattern, $subject, $limit, $flags)
        );
        assert($result !== false);
        return new ArrayList($result);
    }

    /**
     * Handles a warning generated by a regular expression function.
     *
     * @param int $errno The error number.
     * @param string $errstr The error message.
     */
    private function handleWarning(int $errno, string $errstr): void
    {
        $this->lastWarning = new RuntimeException($errstr, $errno);
    }

    /**
     * Calls a native regular expression function and handles the result.
     *
     * @template TResult
     *
     * @param mixed $errorValue The value that indicates an error.
     * @param callable $fn The function to call.
     *
     * @return TResult The result of the function call.
     *
     * @phpstan-param callable():TResult $fn
     */
    private function callNativeRegexFn(mixed $errorValue, callable $fn): mixed
    {
        $this->lastWarning = null;
        set_error_handler([$this, 'handleWarning'], E_WARNING);

        $result = $fn();

        restore_error_handler();
        if ($result === $errorValue) {
            // @phpstan-ignore notIdentical.alwaysFalse
            if ($this->lastWarning !== null) {
                throw $this->lastWarning;
            }

            throw new RuntimeException(preg_last_error_msg(), preg_last_error());
        }

        return $result;
    }
}
