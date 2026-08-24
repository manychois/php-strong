<?php

declare(strict_types=1);

namespace Manychois\PhpStrongTests\Http;

use Manychois\PhpStrong\Http\CookieBag;
use Manychois\PhpStrong\Http\ServerRequest;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for {@see CookieBag}.
 */
final class CookieBagTest extends TestCase
{
    #[Test]
    public function fromRequestReadsTheCookieParams(): void
    {
        $request = new ServerRequest(cookieParams: ['theme' => 'dark', 'sid' => 'abc']);

        $bag = CookieBag::fromRequest($request);

        static::assertSame(['theme' => 'dark', 'sid' => 'abc'], $bag->all());
        static::assertSame('dark', $bag->get('theme'));
        static::assertTrue($bag->has('sid'));
    }

    #[Test]
    public function getReturnsNullForAnAbsentCookie(): void
    {
        $bag = CookieBag::fromRequest(new ServerRequest());

        static::assertNull($bag->get('nope'));
        static::assertFalse($bag->has('nope'));
    }

    #[Test]
    public function fromRequestSkipsNonStringKeysAndValues(): void
    {
        $request = new ServerRequest(cookieParams: [
            'good' => 'yes',
            7 => 'numeric key',
            'array' => ['not', 'a', 'string'],
            'null' => null,
        ]);

        $bag = CookieBag::fromRequest($request);

        static::assertSame(['good' => 'yes'], $bag->all());
    }

    #[Test]
    public function fromRequestDoesNotDecodeBecausePhpAlreadyDid(): void
    {
        $request = new ServerRequest(cookieParams: ['pct' => '100%25', 'hex' => '%41']);

        $bag = CookieBag::fromRequest($request);

        static::assertSame('100%25', $bag->get('pct'));
        static::assertSame('%41', $bag->get('hex'));
    }
}
