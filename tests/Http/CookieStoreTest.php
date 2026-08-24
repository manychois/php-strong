<?php

declare(strict_types=1);

namespace Manychois\PhpStrongTests\Http;

use Manychois\PhpStrong\Http\CookieStore;
use Manychois\PhpStrong\Http\Response;
use Manychois\PhpStrong\Http\Uri;
use Manychois\PhpStrong\Time\TestClock;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for {@see CookieStore}.
 */
final class CookieStoreTest extends TestCase
{
    #[Test]
    public function absorbStoresACookieAsHostOnlyWhenNoDomainIsGiven(): void
    {
        $store = new CookieStore(new TestClock('2026-08-25 00:00:00'));

        $store->absorb($this->responseWith('sid=abc'), Uri::fromString('https://api.example.com/v1/login'));

        $all = $store->all();
        static::assertCount(1, $all);
        static::assertSame('sid', $all[0]->name);
        static::assertSame('abc', $all[0]->value);
        static::assertSame('api.example.com', $all[0]->domain);
        static::assertSame('/v1', $all[0]->path);
    }

    #[Test]
    public function absorbAcceptsADomainWhichMatchesTheRequestHost(): void
    {
        $store = new CookieStore(new TestClock('2026-08-25 00:00:00'));

        $store->absorb(
            $this->responseWith('sid=abc; Domain=example.com'),
            Uri::fromString('https://api.example.com/')
        );

        static::assertSame('example.com', $store->all()[0]->domain);
    }

    #[Test]
    public function absorbStripsALeadingDotFromTheDomain(): void
    {
        $store = new CookieStore(new TestClock('2026-08-25 00:00:00'));

        $store->absorb(
            $this->responseWith('sid=abc; Domain=.example.com'),
            Uri::fromString('https://api.example.com/')
        );

        static::assertSame('example.com', $store->all()[0]->domain);
    }

    #[Test]
    public function absorbRejectsADomainWhichIsNotASuffixOfTheRequestHost(): void
    {
        $store = new CookieStore(new TestClock('2026-08-25 00:00:00'));

        $store->absorb(
            $this->responseWith('sid=abc; Domain=other.com'),
            Uri::fromString('https://api.example.com/')
        );

        static::assertSame([], $store->all());
    }

    #[Test]
    public function absorbRejectsASingleLabelDomain(): void
    {
        $store = new CookieStore(new TestClock('2026-08-25 00:00:00'));

        $store->absorb($this->responseWith('sid=abc; Domain=com'), Uri::fromString('https://api.example.com/'));

        static::assertSame([], $store->all());
    }

    #[Test]
    public function absorbUsesTheExplicitPathWhenGiven(): void
    {
        $store = new CookieStore(new TestClock('2026-08-25 00:00:00'));

        $store->absorb(
            $this->responseWith('sid=abc; Path=/admin'),
            Uri::fromString('https://example.com/v1/login')
        );

        static::assertSame('/admin', $store->all()[0]->path);
    }

    #[Test]
    public function absorbFallsBackToTheDefaultPathForARootRequest(): void
    {
        $store = new CookieStore(new TestClock('2026-08-25 00:00:00'));

        $store->absorb($this->responseWith('sid=abc'), Uri::fromString('https://example.com/login'));

        static::assertSame('/', $store->all()[0]->path);
    }

    #[Test]
    public function absorbIgnoresAPathWhichIsNotAbsolute(): void
    {
        $store = new CookieStore(new TestClock('2026-08-25 00:00:00'));

        $store->absorb(
            $this->responseWith('sid=abc; Path=relative'),
            Uri::fromString('https://example.com/v1/login')
        );

        static::assertSame('/v1', $store->all()[0]->path);
    }

    #[Test]
    public function absorbSkipsAMalformedSetCookieHeader(): void
    {
        $store = new CookieStore(new TestClock('2026-08-25 00:00:00'));

        $store->absorb($this->responseWith('nonsense'), Uri::fromString('https://example.com/'));

        static::assertSame([], $store->all());
    }

    #[Test]
    public function absorbSkipsACookieWhoseAttributesBrowsersReject(): void
    {
        $store = new CookieStore(new TestClock('2026-08-25 00:00:00'));

        $store->absorb($this->responseWith('sid=abc; SameSite=None'), Uri::fromString('https://example.com/'));

        static::assertSame([], $store->all());
    }

    #[Test]
    public function absorbReadsEverySetCookieHeader(): void
    {
        $store = new CookieStore(new TestClock('2026-08-25 00:00:00'));
        $response = new Response(headers: ['Set-Cookie' => ['a=1', 'b=2']]);

        $store->absorb($response, Uri::fromString('https://example.com/'));

        static::assertCount(2, $store->all());
    }

    #[Test]
    public function absorbReplacesACookieWithTheSameDomainPathAndName(): void
    {
        $store = new CookieStore(new TestClock('2026-08-25 00:00:00'));
        $uri = Uri::fromString('https://example.com/');

        $store->absorb($this->responseWith('sid=first'), $uri);
        $store->absorb($this->responseWith('sid=second'), $uri);

        static::assertCount(1, $store->all());
        static::assertSame('second', $store->all()[0]->value);
    }

    #[Test]
    public function clearEmptiesTheStore(): void
    {
        $store = new CookieStore(new TestClock('2026-08-25 00:00:00'));
        $store->absorb($this->responseWith('sid=abc'), Uri::fromString('https://example.com/'));

        $store->clear();

        static::assertSame([], $store->all());
    }

    private function responseWith(string $setCookie): Response
    {
        return new Response(headers: ['Set-Cookie' => $setCookie]);
    }
}
