<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\Texts;

/**
 * Represents the result from a regular expression match.
 */
final class MatchResult extends Capture
{
    public readonly bool $success;
    /**
     * The numbered capturing groups, excluding the whole match.
     * A group that did not participate in the match yields a `Capture` with an empty value and a `null` index.
     *
     * @var list<Capture>
     */
    public readonly array $captures;
    /**
     * The named capturing groups, keyed by group name.
     *
     * @var array<string, Capture>
     */
    public readonly array $namedCaptures;

    /**
     * Initializes a new instance of the MatchResult class.
     *
     * @param array<mixed> $matches The `$matches` result from the PHP function `preg_match()`,
     * with or without `PREG_OFFSET_CAPTURE`.
     */
    public function __construct(array $matches)
    {
        if (count($matches) === 0) {
            parent::__construct('');

            $this->success = false;
            $this->captures = [];
            $this->namedCaptures = [];

            return;
        }

        $matchValue = '';
        $matchIndex = null;
        $captures = [];
        $namedCaptures = [];
        foreach ($matches as $key => $value) {
            /** @var string|array{0:string,1:int} $value */
            $capture = self::toCapture($value);
            if ($key === 0) {
                $matchValue = $capture->value;
                $matchIndex = $capture->index;
            } elseif (is_int($key)) {
                $captures[] = $capture;
            } else {
                $namedCaptures[$key] = $capture;
            }
        }

        parent::__construct($matchValue, $matchIndex);

        $this->success = true;
        $this->captures = $captures;
        $this->namedCaptures = $namedCaptures;
    }

    /**
     * Converts a `preg_match()` group entry into a `Capture`.
     *
     * @param string|array{0:string,1:int} $value The group entry.
     *
     * @return Capture The converted capture.
     */
    private static function toCapture(string|array $value): Capture
    {
        if (is_string($value)) {
            return new Capture($value);
        }

        [$text, $offset] = $value;

        // preg_* reports a group which did not participate in the match as ['', -1].
        return new Capture($text, $offset >= 0 ? $offset : null);
    }
}
