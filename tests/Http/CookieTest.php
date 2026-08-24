<?php

declare(strict_types=1);

namespace Manychois\PhpStrongTests\Http;

use DateTimeImmutable;
use InvalidArgumentException;
use Manychois\PhpStrong\Http\Cookie;
use Manychois\PhpStrong\Http\SameSite;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for {@see Cookie}.
 */
final class CookieTest extends TestCase
{
    #[Test]
    public function everyAttributeDefaultsToTheUnsetValue(): void
    {
        $cookie = new Cookie('theme', 'dark');

        static::assertSame('theme', $cookie->name);
        static::assertSame('dark', $cookie->value);
        static::assertNull($cookie->expires);
        static::assertNull($cookie->maxAge);
        static::assertNull($cookie->domain);
        static::assertNull($cookie->path);
        static::assertFalse($cookie->secure);
        static::assertFalse($cookie->httpOnly);
        static::assertNull($cookie->sameSite);
        static::assertFalse($cookie->partitioned);
    }

    #[Test]
    public function attributesArePreserved(): void
    {
        $expires = new DateTimeImmutable('2026-08-25 10:00:00');
        $cookie = new Cookie(
            name: 'sid',
            value: 'abc',
            expires: $expires,
            maxAge: 600,
            domain: 'example.com',
            path: '/app',
            secure: true,
            httpOnly: true,
            sameSite: SameSite::Strict,
            partitioned: true,
        );

        static::assertSame($expires, $cookie->expires);
        static::assertSame(600, $cookie->maxAge);
        static::assertSame('example.com', $cookie->domain);
        static::assertSame('/app', $cookie->path);
        static::assertTrue($cookie->secure);
        static::assertTrue($cookie->httpOnly);
        static::assertSame(SameSite::Strict, $cookie->sameSite);
        static::assertTrue($cookie->partitioned);
    }

    #[Test]
    public function anEmptyNameIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cookie name must be a valid token, got "".');

        new Cookie('', 'x');
    }

    #[Test]
    public function aNameWithASeparatorIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cookie name must be a valid token, got "a=b".');

        new Cookie('a=b', 'x');
    }

    #[Test]
    public function aNameWithWhitespaceIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Cookie("a b", 'x');
    }

    #[Test]
    public function anyValueIsAcceptedBecauseValuesAreEncodedOnOutput(): void
    {
        $cookie = new Cookie('t', 'a b;c,d"e');

        static::assertSame('a b;c,d"e', $cookie->value);
    }

    #[Test]
    public function aNegativeMaxAgeIsAcceptedBecauseItExpiresTheCookie(): void
    {
        $cookie = new Cookie('t', '', maxAge: -1);

        static::assertSame(-1, $cookie->maxAge);
    }

    #[Test]
    public function sameSiteNoneRequiresASecureCookie(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('SameSite None requires a secure cookie, which browsers enforce.');

        new Cookie('t', 'v', sameSite: SameSite::None);
    }

    #[Test]
    public function aPartitionedCookieMustBeSecure(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('A partitioned cookie must be secure, which browsers enforce.');

        new Cookie('t', 'v', partitioned: true);
    }

    #[Test]
    public function expiredBuildsACookieThatClearsItself(): void
    {
        $cookie = Cookie::expired('sid', 'example.com', '/app');

        static::assertSame('sid', $cookie->name);
        static::assertSame('', $cookie->value);
        static::assertSame(-1, $cookie->maxAge);
        static::assertNotNull($cookie->expires);
        static::assertSame(0, $cookie->expires->getTimestamp());
        static::assertSame('example.com', $cookie->domain);
        static::assertSame('/app', $cookie->path);
    }
}
