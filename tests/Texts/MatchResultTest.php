<?php

declare(strict_types=1);

namespace Manychois\PhpStrongTests\Texts;

use Manychois\PhpStrong\Texts\Capture;
use Manychois\PhpStrong\Texts\MatchResult;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for MatchResult.
 */
final class MatchResultTest extends TestCase
{
    #[Test]
    public function empty_matches_indicates_failure(): void
    {
        $r = new MatchResult([]);

        self::assertFalse($r->success);
        self::assertSame('', $r->value);
        self::assertNull($r->index);
        self::assertCount(0, $r->captures);
        self::assertCount(0, $r->namedCaptures);
    }

    #[Test]
    public function builds_from_offset_capture_arrays(): void
    {
        $matches = [
            0 => ['full', 10],
            1 => ['one', 11],
            2 => ['two', 12],
            'n' => ['nm', 13],
        ];
        $r = new MatchResult($matches);

        self::assertTrue($r->success);
        self::assertSame('full', $r->value);
        self::assertSame(10, $r->index);
        self::assertCount(2, $r->captures);
        self::assertSame('one', $r->captures->at(0)->value);
        self::assertSame(11, $r->captures->at(0)->index);
        self::assertSame('two', $r->captures->at(1)->value);
        self::assertSame(12, $r->captures->at(1)->index);
        self::assertCount(1, $r->namedCaptures);
        $named = $r->namedCaptures->get('n');
        self::assertInstanceOf(Capture::class, $named);
        self::assertSame('nm', $named->value);
        self::assertSame(13, $named->index);
    }

    #[Test]
    public function builds_from_string_only_groups(): void
    {
        $matches = [
            0 => 'full',
            1 => 'g1',
            'x' => 'gx',
        ];
        $r = new MatchResult($matches);

        self::assertTrue($r->success);
        self::assertSame('full', $r->value);
        self::assertNull($r->index);
        self::assertNull($r->captures->at(0)->index);
        self::assertSame('g1', $r->captures->at(0)->value);
        $gx = $r->namedCaptures->get('x');
        self::assertInstanceOf(Capture::class, $gx);
        self::assertNull($gx->index);
    }
}
