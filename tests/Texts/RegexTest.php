<?php

declare(strict_types=1);

namespace Manychois\PhpStrongTests\Texts;

use Manychois\PhpStrong\Texts\MatchResult;
use Manychois\PhpStrong\Texts\Regex;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Unit tests for Regex.
 */
final class RegexTest extends TestCase
{
    #[Test]
    public function constructor_stores_pattern(): void
    {
        $r = new Regex('/foo/');

        self::assertSame('/foo/', $r->pattern);
    }

    #[Test]
    public function escape_delegates_to_preg_quote(): void
    {
        self::assertSame('\$', Regex::escape('$'));
        self::assertSame('\+\.', Regex::escape('+.', '+'));
    }

    #[Test]
    public function match_returns_success_with_offset_data(): void
    {
        $r = new Regex('/hello/');
        $result = $r->match('xxhelloyy', 2);

        self::assertTrue($result->success);
        self::assertSame('hello', $result->value);
        self::assertSame(2, $result->index);
    }

    #[Test]
    public function match_returns_failure_when_no_match(): void
    {
        $r = new Regex('/zzz/');
        $result = $r->match('abc');

        self::assertFalse($result->success);
        self::assertSame('', $result->value);
        self::assertCount(0, $result->captures);
    }

    #[Test]
    public function match_throws_when_utf8_pattern_and_subject_is_not_valid_utf8(): void
    {
        $r = new Regex('//u');
        $invalidUtf8 = "\xFF";

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Malformed UTF-8');
        $this->expectExceptionCode(\PREG_BAD_UTF8_ERROR);

        $r->match($invalidUtf8);
    }

    #[Test]
    public function match_collects_named_groups(): void
    {
        $r = new Regex('/(?<word>\w+)/');
        $result = $r->match('hi there');

        self::assertTrue($result->success);
        self::assertTrue($result->namedCaptures->has('word'));
        self::assertSame('hi', $result->namedCaptures->get('word')->value);
    }

    #[Test]
    public function matchAll_returns_each_match_as_match_result(): void
    {
        $r = new Regex('/a/');
        $all = $r->matchAll('aba');

        self::assertCount(2, $all);
        $first = $all->at(0);
        $second = $all->at(1);
        self::assertInstanceOf(MatchResult::class, $first);
        self::assertInstanceOf(MatchResult::class, $second);
        self::assertTrue($first->success);
        self::assertSame('a', $first->value);
        self::assertSame(0, $first->index);
        self::assertSame('a', $second->value);
        self::assertSame(2, $second->index);
    }

    #[Test]
    public function matchAll_returns_empty_list_when_no_matches(): void
    {
        $r = new Regex('/xyz/');
        $all = $r->matchAll('abc');

        self::assertCount(0, $all);
    }

    #[Test]
    public function replace_performs_substitution(): void
    {
        $r = new Regex('/a/');
        self::assertSame('xbxc', $r->replace('abac', 'x'));
    }

    #[Test]
    public function replace_respects_limit(): void
    {
        $r = new Regex('/a/');
        self::assertSame('xbac', $r->replace('abac', 'x', 1));
    }

    #[Test]
    public function replaceCallback_receives_match_result_with_named_group_and_index(): void
    {
        $r = new Regex('/(?<d>\d)/');
        $out = $r->replaceCallback('x1y', static function (MatchResult $m): string {
            self::assertTrue($m->success);
            self::assertSame('1', $m->value);
            self::assertSame(1, $m->index);
            self::assertSame('1', $m->namedCaptures->get('d')->value);

            return 'n';
        }, 1);

        self::assertSame('xny', $out);
    }

    #[Test]
    public function split_returns_segments(): void
    {
        $r = new Regex('/,/');
        $parts = $r->split('a,b,,c');

        self::assertSame(['a', 'b', '', 'c'], $parts->asArray());
    }

    #[Test]
    public function split_with_non_empty_omits_empty_segments(): void
    {
        $r = new Regex('/,/');
        $parts = $r->split('a,b,,c', -1, true);

        self::assertSame(['a', 'b', 'c'], $parts->asArray());
    }

    #[Test]
    public function invalid_pattern_throws_runtime_exception(): void
    {
        $r = new Regex('/(no end');

        $this->expectException(RuntimeException::class);
        $r->match('x');
    }
}
