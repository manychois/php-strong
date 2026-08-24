<?php

declare(strict_types=1);

namespace Manychois\PhpStrongTests\Http;

use InvalidArgumentException;
use Manychois\PhpStrong\Http\NativeSessionOptions;
use Manychois\PhpStrong\Http\SameSite;
use Manychois\PhpStrong\Http\SessionSerializer;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class NativeSessionOptionsTest extends TestCase
{
    #[Test]
    public function defaultsAreSecure(): void
    {
        $options = new NativeSessionOptions();

        static::assertNull($options->name);
        static::assertNull($options->savePath);
        static::assertSame(0, $options->cookieLifetime);
        static::assertSame('/', $options->cookiePath);
        static::assertSame('', $options->cookieDomain);
        static::assertTrue($options->cookieSecure);
        static::assertTrue($options->cookieHttpOnly);
        static::assertSame(SameSite::Lax, $options->cookieSameSite);
        static::assertFalse($options->cookiePartitioned);
        static::assertTrue($options->useStrictMode);
        static::assertTrue($options->useOnlyCookies);
        static::assertNull($options->gcMaxLifetime);
        static::assertNull($options->serializeHandler);
        static::assertSame([], $options->ini);
    }

    #[Test]
    public function valuesArePreserved(): void
    {
        $options = new NativeSessionOptions(
            name: 'app_session',
            savePath: '/tmp/sessions',
            cookieLifetime: 3600,
            cookiePath: '/app',
            cookieDomain: 'example.com',
            cookieSecure: false,
            cookieHttpOnly: false,
            cookieSameSite: SameSite::Strict,
            cookiePartitioned: false,
            useStrictMode: false,
            useOnlyCookies: false,
            gcMaxLifetime: 1440,
            serializeHandler: SessionSerializer::PhpSerialize,
            ini: ['gc_probability' => 1],
        );

        static::assertSame('app_session', $options->name);
        static::assertSame('/tmp/sessions', $options->savePath);
        static::assertSame(3600, $options->cookieLifetime);
        static::assertSame('/app', $options->cookiePath);
        static::assertSame('example.com', $options->cookieDomain);
        static::assertFalse($options->cookieSecure);
        static::assertFalse($options->cookieHttpOnly);
        static::assertSame(SameSite::Strict, $options->cookieSameSite);
        static::assertFalse($options->useStrictMode);
        static::assertFalse($options->useOnlyCookies);
        static::assertSame(1440, $options->gcMaxLifetime);
        static::assertSame(SessionSerializer::PhpSerialize, $options->serializeHandler);
        static::assertSame(['gc_probability' => 1], $options->ini);
    }

    #[Test]
    public function emptyNameIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new NativeSessionOptions(name: '');
    }

    #[Test]
    public function aNameOfDigitsOnlyIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new NativeSessionOptions(name: '123');
    }

    #[Test]
    public function aNameWithAnIllegalCharacterIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new NativeSessionOptions(name: 'app session');
    }

    #[Test]
    public function anEmptySavePathIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new NativeSessionOptions(savePath: '');
    }

    #[Test]
    public function aNegativeCookieLifetimeIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new NativeSessionOptions(cookieLifetime: -1);
    }

    #[Test]
    public function anEmptyCookiePathIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new NativeSessionOptions(cookiePath: '');
    }

    #[Test]
    public function aNonPositiveGcMaxLifetimeIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new NativeSessionOptions(gcMaxLifetime: 0);
    }

    #[Test]
    public function sameSiteNoneRequiresASecureCookie(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new NativeSessionOptions(cookieSecure: false, cookieSameSite: SameSite::None);
    }

    #[Test]
    public function aPartitionedCookieMustBeSecure(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new NativeSessionOptions(cookieSecure: false, cookiePartitioned: true);
    }

    #[Test]
    public function anIniKeyWithTheSessionPrefixIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new NativeSessionOptions(ini: ['session.gc_probability' => 1]);
    }

    #[Test]
    public function anIniKeyCoveredByADedicatedOptionIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new NativeSessionOptions(ini: ['name' => 'app_session']);
    }
}
