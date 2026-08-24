<?php

declare(strict_types=1);

namespace Manychois\PhpStrongTests\Texts;

use Manychois\PhpStrong\Texts\MatchResult;
use Manychois\PhpStrong\Texts\Regex;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class RegexTest extends TestCase
{
    #[Test]
    public function constructor_storesPattern(): void
    {
        $r = new Regex('/foo/');

        self::assertSame('/foo/', $r->pattern);
    }

    #[Test]
    public function escape_delegatesToPregQuote(): void
    {
        self::assertSame('\$', Regex::escape('$'));
        self::assertSame('\+\.', Regex::escape('+.', '+'));
    }

    #[Test]
    public function match_collectsNamedGroups(): void
    {
        $r = new Regex('/(?<word>\w+)/');
        $result = $r->match('hi there');

        self::assertTrue($result->success);
        self::assertArrayHasKey('word', $result->namedCaptures);
        self::assertSame('hi', $result->namedCaptures['word']->value);
    }

    #[Test]
    public function match_reportsNonParticipatingGroupWithNullIndex(): void
    {
        $r = new Regex('/(a)|(b)/');
        $result = $r->match('b');

        self::assertTrue($result->success);
        self::assertSame('', $result->captures[0]->value);
        self::assertNull($result->captures[0]->index);
        self::assertSame('b', $result->captures[1]->value);
        self::assertSame(0, $result->captures[1]->index);
    }

    #[Test]
    public function match_returnsFailureWhenNoMatch(): void
    {
        $r = new Regex('/zzz/');
        $result = $r->match('abc');

        self::assertFalse($result->success);
        self::assertSame('', $result->value);
        self::assertCount(0, $result->captures);
    }

    #[Test]
    public function match_returnsSuccessWithOffsetData(): void
    {
        $r = new Regex('/hello/');
        $result = $r->match('xxhelloyy', 2);

        self::assertTrue($result->success);
        self::assertSame('hello', $result->value);
        self::assertSame(2, $result->index);
    }

    #[Test]
    public function match_throwsOnInvalidPattern(): void
    {
        $r = new Regex('/(no end');

        $this->expectException(RuntimeException::class);

        $r->match('x');
    }

    #[Test]
    public function match_throwsWhenSubjectIsNotValidUtf8(): void
    {
        $r = new Regex('//u');
        $invalidUtf8 = "\xFF";

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Malformed UTF-8');
        $this->expectExceptionCode(\PREG_BAD_UTF8_ERROR);

        $r->match($invalidUtf8);
    }

    #[Test]
    public function matchAll_returnsEachMatchAsMatchResult(): void
    {
        $r = new Regex('/a/');
        $all = $r->matchAll('aba');

        self::assertCount(2, $all);
        self::assertContainsOnlyInstancesOf(MatchResult::class, $all);
        self::assertTrue($all[0]->success);
        self::assertSame('a', $all[0]->value);
        self::assertSame(0, $all[0]->index);
        self::assertSame('a', $all[1]->value);
        self::assertSame(2, $all[1]->index);
    }

    #[Test]
    public function matchAll_returnsEmptyListWhenNoMatches(): void
    {
        $r = new Regex('/xyz/');
        $all = $r->matchAll('abc');

        self::assertCount(0, $all);
    }

    #[Test]
    public function replace_performsSubstitution(): void
    {
        $r = new Regex('/a/');

        self::assertSame('xbxc', $r->replace('abac', 'x'));
    }

    #[Test]
    public function replace_respectsLimit(): void
    {
        $r = new Regex('/a/');

        self::assertSame('xbac', $r->replace('abac', 'x', 1));
    }

    #[Test]
    public function replace_throwsOnInvalidPattern(): void
    {
        $r = new Regex('/(no end');

        $this->expectException(RuntimeException::class);

        $r->replace('x', 'y');
    }

    #[Test]
    public function replaceCallback_receivesMatchResultWithNamedGroupAndIndex(): void
    {
        $r = new Regex('/(?<d>\d)/');
        $out = $r->replaceCallback('x1y', static function (MatchResult $m): string {
            self::assertTrue($m->success);
            self::assertSame('1', $m->value);
            self::assertSame(1, $m->index);
            self::assertSame('1', $m->namedCaptures['d']->value);

            return 'n';
        }, 1);

        self::assertSame('xny', $out);
    }

    #[Test]
    public function replaceCallback_restoresErrorHandlerWhenCallbackThrows(): void
    {
        $r = new Regex('/a/');

        try {
            $r->replaceCallback('a', static function (): string {
                throw new RuntimeException('boom');
            });
            self::fail('Expected exception was not thrown.');
        } catch (RuntimeException $ex) {
            self::assertSame('boom', $ex->getMessage());
        }

        // The error handler must have been restored; a subsequent call works normally.
        self::assertSame('x', $r->replace('a', 'x'));
    }

    #[Test]
    public function split_returnsSegments(): void
    {
        $r = new Regex('/,/');
        $parts = $r->split('a,b,,c');

        self::assertSame(['a', 'b', '', 'c'], $parts);
    }

    #[Test]
    public function split_withNonEmptyOmitsEmptySegments(): void
    {
        $r = new Regex('/,/');
        $parts = $r->split('a,b,,c', -1, true);

        self::assertSame(['a', 'b', 'c'], $parts);
    }
}
