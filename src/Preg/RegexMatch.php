<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\Preg;

/**
 * Represents a regular expression match result.
 */
class RegexMatch extends Capture
{
    public readonly bool $success;
    /**
     * @var array<int, Capture>
     */
    public readonly array $captures;
    /**
     * @var array<string, Capture>
     */
    public readonly array $namedCaptures;

    /**
     * Creates a regular expression match result.
     *
     * @param array<array<int, int|string>> $result The result of a preg_match() or preg_match_all() call.
     */
    public function __construct(array $result)
    {
        if (\count($result) === 0) {
            parent::__construct([]);
            $this->success = false;
            $this->captures = [];
            $this->namedCaptures = [];
        } else {
            parent::__construct($result[0]);
            $this->success = true;
            $captures = [];
            $namedCaptures = [];
            foreach ($result as $key => $value) {
                if (\is_int($key)) {
                    if ($key === 0) {
                        continue;
                    }
                    $captures[] = new Capture($value);
                } else {
                    $namedCaptures[$key] = new Capture($value);
                }
            }
            $this->captures = $captures;
            $this->namedCaptures = $namedCaptures;
        }
    }
}
