<?php

declare(strict_types=1);

namespace Manychois\PhpStrongTests\Texts;

use Manychois\PhpStrong\Texts\MatchResult;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class MatchResultTest extends TestCase
{
    #[Test]
    public function constructor_buildsFromOffsetCaptureArrays(): void
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
        self::assertSame('one', $r->captures[0]->value);
        self::assertSame(11, $r->captures[0]->index);
        self::assertSame('two', $r->captures[1]->value);
        self::assertSame(12, $r->captures[1]->index);
        self::assertCount(1, $r->namedCaptures);
        self::assertSame('nm', $r->namedCaptures['n']->value);
        self::assertSame(13, $r->namedCaptures['n']->index);
    }

    #[Test]
    public function constructor_buildsFromStringOnlyGroups(): void
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
        self::assertSame('g1', $r->captures[0]->value);
        self::assertNull($r->captures[0]->index);
        self::assertArrayHasKey('x', $r->namedCaptures);
        self::assertNull($r->namedCaptures['x']->index);
    }

    #[Test]
    public function constructor_indicatesFailureOnEmptyMatches(): void
    {
        $r = new MatchResult([]);

        self::assertFalse($r->success);
        self::assertSame('', $r->value);
        self::assertNull($r->index);
        self::assertCount(0, $r->captures);
        self::assertCount(0, $r->namedCaptures);
    }

    #[Test]
    public function constructor_treatsNegativeOffsetAsNonParticipatingGroup(): void
    {
        $matches = [
            0 => ['b', 0],
            1 => ['', -1],
            2 => ['b', 0],
        ];
        $r = new MatchResult($matches);

        self::assertTrue($r->success);
        self::assertSame('', $r->captures[0]->value);
        self::assertNull($r->captures[0]->index);
        self::assertSame('b', $r->captures[1]->value);
        self::assertSame(0, $r->captures[1]->index);
    }
}
