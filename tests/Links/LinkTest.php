<?php

declare(strict_types=1);

namespace Manychois\PhpStrongTests\Links;

use Manychois\PhpStrong\Links\InvalidArgumentException;
use Manychois\PhpStrong\Links\Link;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Stringable;

final class LinkTest extends TestCase
{
    #[Test]
    public function construct_withoutArgumentsYieldsAnEmptyLink(): void
    {
        $link = new Link();

        self::assertSame('', $link->getHref());
        self::assertFalse($link->isTemplated());
        self::assertSame([], $link->getRels());
        self::assertSame([], $link->getAttributes());
    }

    #[Test]
    public function construct_castsStringableHrefImmediately(): void
    {
        $href = new class implements Stringable {
            public int $calls = 0;

            public function __toString(): string
            {
                $this->calls++;

                return '/posts/1';
            }
        };

        $link = new Link($href);

        self::assertSame('/posts/1', $link->getHref());
        self::assertSame(1, $href->calls);
    }

    #[Test]
    public function construct_collapsesDuplicateRelsKeepingFirstSeenOrder(): void
    {
        $link = new Link('/a', ['next', 'prev', 'next']);

        self::assertSame(['next', 'prev'], $link->getRels());
    }

    #[Test]
    public function construct_acceptsEveryPermittedAttributeValueType(): void
    {
        $link = new Link('/a', [], [
            'title' => 'A page',
            'count' => 3,
            'weight' => 1.5,
            'me' => true,
            'hreflang' => ['en', 'fr'],
        ]);

        self::assertSame([
            'title' => 'A page',
            'count' => 3,
            'weight' => 1.5,
            'me' => true,
            'hreflang' => ['en', 'fr'],
        ], $link->getAttributes());
    }

    #[Test]
    public function construct_castsStringableAttributeValueToString(): void
    {
        $value = new class implements Stringable {
            public function __toString(): string
            {
                return 'text/html';
            }
        };

        $link = new Link('/a', [], ['type' => $value]);

        self::assertSame(['type' => 'text/html'], $link->getAttributes());
    }

    #[Test]
    public function isTemplated_followsTheHref(): void
    {
        $link = new Link('/posts/{id}');

        self::assertTrue($link->isTemplated());
        self::assertFalse($link->withHref('/posts/1')->isTemplated());
    }

    #[Test]
    public function withHref_returnsANewInstanceAndLeavesTheOriginalUntouched(): void
    {
        $link = new Link('/a');

        $evolved = $link->withHref('/b');

        self::assertNotSame($link, $evolved);
        self::assertSame('/b', $evolved->getHref());
        self::assertSame('/a', $link->getHref());
    }

    #[Test]
    public function withHref_castsStringable(): void
    {
        $href = new class implements Stringable {
            public function __toString(): string
            {
                return '/b';
            }
        };

        self::assertSame('/b', (new Link('/a'))->withHref($href)->getHref());
    }

    #[Test]
    public function withRel_addsTheRelWithoutTouchingTheOriginal(): void
    {
        $link = new Link('/a', ['next']);

        $evolved = $link->withRel('prev');

        self::assertSame(['next', 'prev'], $evolved->getRels());
        self::assertSame(['next'], $link->getRels());
    }

    #[Test]
    public function withRel_isIdempotentForAnAlreadyPresentRel(): void
    {
        $link = new Link('/a', ['next']);

        $evolved = $link->withRel('next');

        self::assertSame($link, $evolved);
        self::assertSame(['next'], $evolved->getRels());
    }

    #[Test]
    public function withoutRel_removesTheRelAndKeepsTheResultAList(): void
    {
        $link = new Link('/a', ['next', 'prev', 'up']);

        $evolved = $link->withoutRel('prev');

        self::assertSame(['next', 'up'], $evolved->getRels());
        self::assertSame(['next', 'prev', 'up'], $link->getRels());
    }

    #[Test]
    public function withoutRel_returnsNormallyForAnAbsentRel(): void
    {
        $link = new Link('/a', ['next']);

        $evolved = $link->withoutRel('prev');

        self::assertSame($link, $evolved);
        self::assertSame(['next'], $evolved->getRels());
    }

    #[Test]
    public function withAttribute_addsAndOverwritesInPlace(): void
    {
        $link = new Link('/a', [], ['title' => 'Old', 'type' => 'text/html']);

        $evolved = $link->withAttribute('title', 'New');

        self::assertSame(['title' => 'New', 'type' => 'text/html'], $evolved->getAttributes());
        self::assertSame(['title' => 'Old', 'type' => 'text/html'], $link->getAttributes());
    }

    #[Test]
    public function withoutAttribute_removesTheAttribute(): void
    {
        $link = new Link('/a', [], ['title' => 'A page']);

        self::assertSame([], $link->withoutAttribute('title')->getAttributes());
        self::assertSame(['title' => 'A page'], $link->getAttributes());
    }

    #[Test]
    public function withoutAttribute_returnsNormallyForAnAbsentAttribute(): void
    {
        $link = new Link('/a', [], ['title' => 'A page']);

        $evolved = $link->withoutAttribute('type');

        self::assertSame($link, $evolved);
        self::assertSame(['title' => 'A page'], $evolved->getAttributes());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function provideInvalidRels(): array
    {
        return [
            'empty' => [''],
            'whitespace only' => ["  \t"],
            'contains a space' => ['next prev'],
            'contains a newline' => ["next\nprev"],
            'contains a NUL byte' => ["next\x00prev"],
            'contains a CRLF-bearing header injection attempt' => ["next\r\nX-Evil: 1"],
        ];
    }

    #[Test]
    #[DataProvider('provideInvalidRels')]
    public function construct_rejectsAnInvalidRel(string $rel): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Link('/a', [$rel]);
    }

    #[Test]
    #[DataProvider('provideInvalidRels')]
    public function withRel_rejectsAnInvalidRel(string $rel): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new Link('/a'))->withRel($rel);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function provideInvalidAttributeNames(): array
    {
        return [
            'empty' => [''],
            'whitespace only' => ['   '],
            'contains a NUL byte' => ["ti\x00tle"],
            'contains a CRLF-bearing header injection attempt' => ["bad\r\nX-Evil: 1"],
        ];
    }

    #[Test]
    #[DataProvider('provideInvalidAttributeNames')]
    public function construct_rejectsAnInvalidAttributeName(string $name): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Link('/a', [], [$name => 'x']);
    }

    #[Test]
    #[DataProvider('provideInvalidAttributeNames')]
    public function withAttribute_rejectsAnInvalidAttributeName(string $name): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new Link('/a'))->withAttribute($name, 'x');
    }

    /**
     * @return array<string, array{string|Stringable|int|float|bool|array<mixed>}>
     */
    public static function provideInvalidAttributeValues(): array
    {
        return [
            'string keyed array' => [['a' => 'b']],
            'sparse array' => [[1 => 'b']],
            'nested array' => [[['a']]],
            'array with an int' => [['a', 1]],
            'array with null' => [['a', null]],
        ];
    }

    /**
     * @param string|Stringable|int|float|bool|array<mixed> $value
     */
    #[Test]
    #[DataProvider('provideInvalidAttributeValues')]
    public function withAttribute_rejectsAnInvalidAttributeValue(string|Stringable|int|float|bool|array $value): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new Link('/a'))->withAttribute('hreflang', $value);
    }

    #[Test]
    public function construct_rejectsAnInvalidAttributeValue(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Link('/a', [], ['hreflang' => ['a' => 'b']]);
    }

    #[Test]
    public function withAttribute_acceptsAnEmptyArrayAsAnEmptyList(): void
    {
        self::assertSame(['hreflang' => []], (new Link('/a'))->withAttribute('hreflang', [])->getAttributes());
    }
}
