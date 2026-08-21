<?php

declare(strict_types=1);

namespace Manychois\PhpStrongTests\Links\Internal;

use Manychois\PhpStrong\Links\Internal\UriTemplate;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class UriTemplateTest extends TestCase
{
    /**
     * @return array<string, array{string}>
     */
    public static function provideTemplates(): array
    {
        $cases = [
            // RFC 6570 level 1
            '{var}',
            '{hello}',
            'http://example.com/~{username}/',
            // level 2 - reserved and fragment expansion
            '{+path}/here',
            '{#hash}',
            // level 3 - operators and multiple variables
            'map?{x,y}',
            'X{.dom*}',
            '{/var}',
            '{;x,y,empty}',
            '{?x,y}',
            '{&x}',
            // level 4 - modifiers
            '{var:3}',
            '{var:9999}',
            '{list*}',
            '{+path,x}/here',
            // misc valid shapes
            '{var.a}',
            '{a%20b}',
            '{.x}',
            'O{empty}X',
            '/posts/{id}',
        ];

        $data = [];
        foreach ($cases as $case) {
            $data[$case] = [$case];
        }

        return $data;
    }

    /**
     * @return array<string, array{string}>
     */
    public static function provideNonTemplates(): array
    {
        $cases = [
            '(empty string)' => '',
            'plain path' => '/posts/1',
            'absolute uri' => 'http://example.com/a',
            'unbalanced open brace' => '{',
            'unbalanced open brace with name' => '{var',
            'reversed braces' => '}var{',
            'empty expression' => '{}',
            'reserved operator =' => '{=x}',
            'reserved operator |' => '{|x}',
            'leading comma' => '{,x}',
            'trailing comma' => '{x,}',
            'varname ending with dot' => '{x.}',
            'varname with double dot' => '{x..y}',
            'prefix zero' => '{var:0}',
            'prefix too long' => '{var:12345}',
            'double explode' => '{var**}',
            'explode as prefix' => '{var:*}',
            'space in varname' => '{ x}',
            'space in literal' => 'a b{x}',
            'bare percent in literal' => 'a%zz{x}',
            'angle bracket in literal' => 'a<b{x}',
        ];

        $data = [];
        foreach ($cases as $label => $case) {
            $data[$label] = [$case];
        }

        return $data;
    }

    #[Test]
    #[DataProvider('provideTemplates')]
    public function isTemplate_returnsTrueForValidRfc6570Templates(string $href): void
    {
        self::assertTrue(UriTemplate::isTemplate($href));
    }

    #[Test]
    #[DataProvider('provideNonTemplates')]
    public function isTemplate_returnsFalseForPlainOrMalformedUris(string $href): void
    {
        self::assertFalse(UriTemplate::isTemplate($href));
    }

    #[Test]
    public function isTemplate_returnsFalseForInvalidUtf8(): void
    {
        self::assertFalse(UriTemplate::isTemplate("/a\xC3{var}"));
    }
}
