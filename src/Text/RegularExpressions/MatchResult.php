<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\Text\RegularExpressions;

use Manychois\PhpStrong\Collections\ReadonlyMap;
use Manychois\PhpStrong\Collections\ReadonlySequence;

/**
 * Represents the result from a regular expression match.
 */
class MatchResult extends Capture
{
    public readonly bool $success;
    /**
     * @var ReadonlySequence<Capture>
     */
    public readonly ReadonlySequence $captures;
    /**
     * @var ReadonlyMap<string,Capture>
     */
    public readonly ReadonlyMap $namedCaptures;

    /**
     * @param array<mixed> $matches The `$matches` result from the PHP function `preg_match()`.
     */
    public function __construct(array $matches)
    {
        if (\count($matches) === 0) {
            parent::__construct('', -1);

            $this->success = false;
            $this->captures = ReadonlySequence::ofObject(Capture::class);
            $this->namedCaptures = ReadonlyMap::ofStringToObject(Capture::class);

            return;
        }

        $matchValue = '';
        $matchIndex = -1;
        $captures = [];
        $namedCaptures = [];
        foreach ($matches as $key => $value) {
            /** @var string|array{0:string,1:int} $value */
            if ($key === 0) {
                if (\is_array($value)) {
                    $matchValue = $value[0];
                    $matchIndex = $value[1];
                } else {
                    $matchValue = $value;
                }
            } else {
                if (\is_int($key)) {
                    if (\is_array($value)) {
                        $captures[] = new Capture($value[0], $value[1]);
                    } else {
                        $captures[] = new Capture($value);
                    }
                } else {
                    if (\is_array($value)) {
                        $namedCaptures[$key] = new Capture($value[0], $value[1]);
                    } else {
                        $namedCaptures[$key] = new Capture($value);
                    }
                }
            }
        }

        parent::__construct($matchValue, $matchIndex);

        $this->success = true;
        $this->captures = ReadonlySequence::ofObject(Capture::class, $captures);
        $this->namedCaptures = ReadonlyMap::ofStringToObject(Capture::class, $namedCaptures);
    }
}
