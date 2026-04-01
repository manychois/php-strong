<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\Texts;

use Manychois\PhpStrong\Collections\ArrayList;
use Manychois\PhpStrong\Collections\ReadonlyList;
use Manychois\PhpStrong\Collections\ReadonlyListInterface as IReadonlyList;
use Manychois\PhpStrong\Collections\ReadonlyMapInterface as IReadonlyMap;
use Manychois\PhpStrong\Collections\StringMap;

/**
 * Represents the result from a regular expression match.
 */
class MatchResult extends Capture
{
    public readonly bool $success;
    /**
     * @var IReadonlyList<Capture>
     */
    public readonly IReadonlyList $captures;
    /**
     * @var IReadonlyMap<string,Capture>
     */
    public readonly IReadonlyMap $namedCaptures;

    /**
     * @param array<mixed> $matches The `$matches` result from the PHP function `preg_match()`.
     */
    public function __construct(array $matches)
    {
        if (count($matches) === 0) {
            parent::__construct('');

            $this->success = false;
            $this->captures = new ReadonlyList([]);
            $this->namedCaptures = new StringMap()->asReadonly();

            return;
        }

        $matchValue = '';
        $matchIndex = null;
        $captures = new ArrayList();
        $namedCaptures = new StringMap();
        foreach ($matches as $key => $value) {
            /** @var string|array{0:string,1:non-negative-int} $value */
            if ($key === 0) {
                if (is_array($value)) {
                    $matchValue = $value[0];
                    $matchIndex = $value[1];
                } else {
                    $matchValue = $value;
                }
            } else {
                if (is_int($key)) {
                    if (is_array($value)) {
                        $captures->add(new Capture($value[0], $value[1]));
                    } else {
                        $captures->add(new Capture($value));
                    }
                } else {
                    if (is_array($value)) {
                        $namedCaptures->add($key, new Capture($value[0], $value[1]));
                    } else {
                        $namedCaptures->add($key, new Capture($value));
                    }
                }
            }
        }

        parent::__construct($matchValue, $matchIndex);

        $this->success = true;
        $this->captures = $captures->asReadonly();
        $this->namedCaptures = $namedCaptures->asReadonly();
    }
}
