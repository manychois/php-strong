<?php

declare(strict_types=1);

namespace Manychois\PhpStrongTests\Http;

use DateTimeImmutable;
use DateTimeZone;
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

    #[Test]
    public function toSetCookieHeaderWritesTheNameAndValueOnly(): void
    {
        $cookie = new Cookie('theme', 'dark');

        static::assertSame('theme=dark', $cookie->toSetCookieHeader());
    }

    #[Test]
    public function toSetCookieHeaderEncodesTheValue(): void
    {
        $cookie = new Cookie('t', 'a b+c%d');

        static::assertSame('t=a%20b%2Bc%25d', $cookie->toSetCookieHeader());
    }

    #[Test]
    public function toSetCookieHeaderWritesEveryAttributeInAFixedOrder(): void
    {
        $cookie = new Cookie(
            name: 'sid',
            value: 'abc',
            expires: new DateTimeImmutable('2026-08-25 10:00:00', new DateTimeZone('UTC')),
            maxAge: 600,
            domain: 'example.com',
            path: '/app',
            secure: true,
            httpOnly: true,
            sameSite: SameSite::Lax,
            partitioned: true,
        );

        static::assertSame(
            'sid=abc; Expires=Tue, 25 Aug 2026 10:00:00 GMT; Max-Age=600; Domain=example.com; Path=/app; '
            . 'Secure; HttpOnly; SameSite=Lax; Partitioned',
            $cookie->toSetCookieHeader()
        );
    }

    #[Test]
    public function toSetCookieHeaderConvertsExpiresToUtc(): void
    {
        $cookie = new Cookie(
            't',
            'v',
            expires: new DateTimeImmutable('2026-08-25 20:00:00', new DateTimeZone('Australia/Sydney')),
        );

        static::assertSame('t=v; Expires=Tue, 25 Aug 2026 10:00:00 GMT', $cookie->toSetCookieHeader());
    }

    #[Test]
    public function toSetCookieHeaderOmitsFalseFlags(): void
    {
        $cookie = new Cookie('t', 'v', secure: false, httpOnly: false);

        static::assertSame('t=v', $cookie->toSetCookieHeader());
    }

    #[Test]
    public function parseSetCookieReadsTheNameAndValue(): void
    {
        $cookie = Cookie::parseSetCookie('theme=dark');

        static::assertSame('theme', $cookie->name);
        static::assertSame('dark', $cookie->value);
    }

    #[Test]
    public function parseSetCookieDecodesTheValue(): void
    {
        $cookie = Cookie::parseSetCookie('t=a%20b%2Bc%25d');

        static::assertSame('a b+c%d', $cookie->value);
    }

    #[Test]
    public function parseSetCookieStripsSurroundingQuotesFromTheValue(): void
    {
        $cookie = Cookie::parseSetCookie('t="quoted"');

        static::assertSame('quoted', $cookie->value);
    }

    #[Test]
    public function parseSetCookieReadsEveryAttributeCaseInsensitively(): void
    {
        $cookie = Cookie::parseSetCookie(
            'sid=abc; expires=Tue, 25 Aug 2026 10:00:00 GMT; MAX-AGE=600; Domain=example.com; path=/app; '
            . 'secure; httponly; samesite=lax; PARTITIONED'
        );

        static::assertSame('sid', $cookie->name);
        static::assertNotNull($cookie->expires);
        static::assertSame('2026-08-25T10:00:00+00:00', $cookie->expires->format('c'));
        static::assertSame(600, $cookie->maxAge);
        static::assertSame('example.com', $cookie->domain);
        static::assertSame('/app', $cookie->path);
        static::assertTrue($cookie->secure);
        static::assertTrue($cookie->httpOnly);
        static::assertSame(SameSite::Lax, $cookie->sameSite);
        static::assertTrue($cookie->partitioned);
    }

    #[Test]
    public function parseSetCookieIgnoresUnknownAttributes(): void
    {
        $cookie = Cookie::parseSetCookie('t=v; Comment=hello; Version=1; Path=/');

        static::assertSame('/', $cookie->path);
        static::assertSame('v', $cookie->value);
    }

    #[Test]
    public function parseSetCookieKeepsBothExpiresAndMaxAge(): void
    {
        $cookie = Cookie::parseSetCookie('t=v; Expires=Tue, 25 Aug 2026 10:00:00 GMT; Max-Age=60');

        static::assertNotNull($cookie->expires);
        static::assertSame(60, $cookie->maxAge);
    }

    #[Test]
    public function parseSetCookieIgnoresAnUnparseableExpires(): void
    {
        $cookie = Cookie::parseSetCookie('t=v; Expires=not-a-date');

        static::assertNull($cookie->expires);
    }

    #[Test]
    public function parseSetCookieIgnoresANonNumericMaxAge(): void
    {
        $cookie = Cookie::parseSetCookie('t=v; Max-Age=soon');

        static::assertNull($cookie->maxAge);
    }

    #[Test]
    public function parseSetCookieIgnoresAnUnknownSameSiteValue(): void
    {
        $cookie = Cookie::parseSetCookie('t=v; SameSite=sideways');

        static::assertNull($cookie->sameSite);
    }

    #[Test]
    public function parseSetCookieRejectsAHeaderWithNoNameValuePair(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Set-Cookie header must begin with a name=value pair, got "nonsense".');

        Cookie::parseSetCookie('nonsense');
    }

    #[Test]
    public function parseSetCookieRoundTripsWithToSetCookieHeader(): void
    {
        $original = new Cookie(
            name: 'sid',
            value: 'a b/c',
            maxAge: 600,
            domain: 'example.com',
            path: '/app',
            secure: true,
            httpOnly: true,
            sameSite: SameSite::Strict,
        );

        $parsed = Cookie::parseSetCookie($original->toSetCookieHeader());

        static::assertSame($original->name, $parsed->name);
        static::assertSame($original->value, $parsed->value);
        static::assertSame($original->maxAge, $parsed->maxAge);
        static::assertSame($original->domain, $parsed->domain);
        static::assertSame($original->path, $parsed->path);
        static::assertTrue($parsed->secure);
        static::assertTrue($parsed->httpOnly);
        static::assertSame(SameSite::Strict, $parsed->sameSite);
    }
}
