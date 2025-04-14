<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\Text\RegularExpressions;

use Manychois\PhpStrong\Collections\Sequence;
use RuntimeException;

/**
 * Represents a regular expression.
 */
class Regex
{
    public readonly string $pattern;
    private ?\Throwable $lastWarning = null;

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
     * @param string      $text      The text to escape.
     * @param string|null $delimiter The delimiter character. Note that "/" is the most commonly used delimiter, but is
     *                               not a special regular expression character.
     *
     * @return string The escaped text.
     */
    public static function escape(string $text, ?string $delimiter = null): string
    {
        return \preg_quote($text, $delimiter);
    }

    /**
     * Matches the regular expression against a subject string.
     *
     * @param string $subject The subject string to search.
     * @param int    $offset  The offset in the subject at which to start the search.
     *
     * @return MatchResult The result of the match.
     */
    public function match(string $subject, int $offset = 0): MatchResult
    {
        $this->preNativeRegexCall();
        $result = \preg_match($this->pattern, $subject, $matches, \PREG_OFFSET_CAPTURE, $offset);
        $this->postNativeRegexCall($result, false);

        return new MatchResult($matches);
    }

    /**
     * Find all the matches of the regular expression in a subject string.
     *
     * @param string $subject The subject string to search.
     * @param int    $offset  The offset in the subject at which to start the search.
     *
     * @return Sequence<MatchResult> The match results.
     */
    public function matchAll(string $subject, int $offset = 0): Sequence
    {
        $this->preNativeRegexCall();
        $result = \preg_match_all($this->pattern, $subject, $matches, \PREG_OFFSET_CAPTURE, $offset);
        $this->postNativeRegexCall($result, false);

        $matchResults = [];
        if (\count($matches) > 0) {
            $count = \count($matches[0]);
            for ($i = 0; $i < $count; $i++) {
                $matchGroup = [];
                foreach ($matches as $key => $value) {
                    $matchGroup[$key] = $value[$i];
                }
                $matchResults[] = new MatchResult($matchGroup);
            }
        }

        return Sequence::ofObject(MatchResult::class, $matchResults);
    }

    /**
     * Performs a regular expression search and replace.
     *
     * @param string $subject     The subject string to search.
     * @param string $replacement The replacement string.
     * @param int    $limit       The maximum possible replacements. Default is -1 (no limit).
     *
     * @return string The resulting string.
     */
    public function replace(string $subject, string $replacement, int $limit = -1): string
    {
        $this->preNativeRegexCall();
        $result = \preg_replace($this->pattern, $replacement, $subject, $limit);
        $this->postNativeRegexCall($result, null);
        \assert($result !== null);

        return $result;
    }

    /**
     * Perform a regular expression search and replace using a callback.
     *
     * @param string   $subject  The subject string to search.
     * @param callable $callback The callback that will be called for each match.
     * @param int      $limit    The maximum possible replacements. Default is -1 (no limit).
     *
     * @return string The resulting string.
     *
     * @phpstan-param callable(MatchResult):string $callback
     */
    public function replaceCallback(string $subject, callable $callback, int $limit = -1): string
    {
        $strongCallback = static fn (array $matches) => $callback(new MatchResult($matches));
        $this->preNativeRegexCall();
        $result = \preg_replace_callback(
            $this->pattern,
            $strongCallback,
            $subject,
            $limit,
            $count,
            \PREG_OFFSET_CAPTURE
        );
        $this->postNativeRegexCall($result, null);
        \assert($result !== null);

        return $result;
    }

    /**
     * Splits a string by a regular expression.
     *
     * @param string $subject  The subject string to split.
     * @param int    $limit    Only substrings up to limit are returned with the rest of the string being placed in the
     *                         last substring. A limit of -1 or 0 means "no limit".
     * @param bool   $nonEmpty If true, only non-empty substrings are returned.
     *
     * @return Sequence<string> A sequence of substrings.
     */
    public function split(string $subject, int $limit = -1, bool $nonEmpty = false): Sequence
    {
        $flags = $nonEmpty ? \PREG_SPLIT_NO_EMPTY : 0;
        $this->preNativeRegexCall();
        $result = \preg_split($this->pattern, $subject, $limit, $flags);
        $this->postNativeRegexCall($result, false);
        \assert($result !== false);

        return Sequence::ofString($result);
    }

    /**
     * Handles a warning generated by a regular expression function.
     *
     * @param int    $errno  The error number.
     * @param string $errstr The error message.
     */
    private function handleWarning(int $errno, string $errstr): void
    {
        $this->lastWarning = new RuntimeException($errstr, $errno);
    }

    /**
     * Prepares for a call to a native regular expression function.
     */
    private function preNativeRegexCall(): void
    {
        $this->lastWarning = null;
        \set_error_handler([$this, 'handleWarning'], \E_WARNING);
    }

    /**
     * Restores the error handler and checks the result of a native regular expression function call.
     *
     * @param mixed $result     The result of the function call.
     * @param mixed $errorValue The value that indicates an error.
     */
    private function postNativeRegexCall(mixed $result, mixed $errorValue): void
    {
        \restore_error_handler();
        if ($result === $errorValue) {
            if ($this->lastWarning !== null) {
                throw $this->lastWarning;
            }

            throw new RuntimeException(\preg_last_error_msg(), \preg_last_error());
        }
    }
}
