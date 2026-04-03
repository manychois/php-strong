<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\Texts;

/**
 * Which end(s) of a string an operation applies to (e.g. padding or trimming).
 */
enum StringSide: int
{
    case Left = STR_PAD_LEFT;
    case Right = STR_PAD_RIGHT;
    case Both = STR_PAD_BOTH;
}
