<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\Text\RegularExpressions;

use Manychois\PhpStrong\Collections\ReadonlySequence;
use RuntimeException;

/**
 * Represents a regular expression.
 */
class Regex
{
    public readonly string $pattern;

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
     * Matches the regular expression against a subject string.
     *
     * @param string $subject    The subject string to search.
     * @param int    $offset     The offset in the subject at which to start the search.
     * @param bool   $getOffsets Whether to return the offsets of the matches.
     *
     * @return MatchResult The result of the match.
     */
    public function match(string $subject, int $offset = 0, bool $getOffsets = false): MatchResult
    {
        $flags = $getOffsets ? \PREG_OFFSET_CAPTURE : 0;
        $result = \preg_match($this->pattern, $subject, $matches, $flags, $offset);
        if ($result === false) {
            throw new RuntimeException(\preg_last_error_msg(), \preg_last_error());
        }

        return new MatchResult($matches);
    }

    /**
     * Find all the matches of the regular expression in a subject string.
     *
     * @param string $subject    The subject string to search.
     * @param int    $offset     The offset in the subject at which to start the search.
     * @param bool   $getOffsets Whether to return the offsets of the matches.
     *
     * @return ReadonlySequence<MatchResult> The list of match results.
     */
    public function matchAll(string $subject, int $offset = 0, bool $getOffsets = false): ReadonlySequence
    {
        $flags = $getOffsets ? \PREG_OFFSET_CAPTURE : 0;
        $result = \preg_match_all($this->pattern, $subject, $matches, $flags, $offset);
        if ($result === false) {
            throw new RuntimeException(\preg_last_error_msg(), \preg_last_error());
        }

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

        return new ReadonlySequence($matchResults);
    }
}
