<?php

declare(strict_types=1);

namespace Manychois\PhpStrongTests\Http;

use Manychois\PhpStrong\Http\CookieStore;
use Manychois\PhpStrong\Http\Request;
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

    #[Test]
    public function aCookieWithNeitherExpiresNorMaxAgeSurvivesIndefinitely(): void
    {
        $clock = new TestClock('2026-08-25 00:00:00');
        $store = new CookieStore($clock);
        $store->absorb($this->responseWith('sid=abc'), Uri::fromString('https://example.com/'));

        $clock->advance('P10Y');

        static::assertCount(1, $store->all());
    }

    #[Test]
    public function maxAgeExpiresTheCookieWhenTheClockPassesIt(): void
    {
        $clock = new TestClock('2026-08-25 00:00:00');
        $store = new CookieStore($clock);
        $store->absorb($this->responseWith('sid=abc; Max-Age=60'), Uri::fromString('https://example.com/'));

        $clock->advance('PT59S');
        static::assertCount(1, $store->all());

        $clock->advance('PT2S');
        static::assertSame([], $store->all());
    }

    #[Test]
    public function expiresExpiresTheCookieWhenTheClockPassesIt(): void
    {
        $clock = new TestClock('2026-08-25 00:00:00');
        $store = new CookieStore($clock);
        $store->absorb(
            $this->responseWith('sid=abc; Expires=Tue, 25 Aug 2026 00:01:00 GMT'),
            Uri::fromString('https://example.com/')
        );

        $clock->advance('PT30S');
        static::assertCount(1, $store->all());

        $clock->advance('PT31S');
        static::assertSame([], $store->all());
    }

    #[Test]
    public function maxAgeWinsOverExpiresWhenBothAreGiven(): void
    {
        $clock = new TestClock('2026-08-25 00:00:00');
        $store = new CookieStore($clock);
        $store->absorb(
            $this->responseWith('sid=abc; Expires=Tue, 25 Aug 2026 10:00:00 GMT; Max-Age=60'),
            Uri::fromString('https://example.com/')
        );

        $clock->advance('PT61S');

        static::assertSame([], $store->all());
    }

    #[Test]
    public function aZeroOrNegativeMaxAgeDeletesTheCookieImmediately(): void
    {
        $clock = new TestClock('2026-08-25 00:00:00');
        $store = new CookieStore($clock);
        $uri = Uri::fromString('https://example.com/');
        $store->absorb($this->responseWith('sid=abc'), $uri);

        $store->absorb($this->responseWith('sid=; Max-Age=0'), $uri);

        static::assertSame([], $store->all());
    }

    #[Test]
    public function anExpiresAlreadyInThePastDeletesTheCookieImmediately(): void
    {
        $clock = new TestClock('2026-08-25 00:00:00');
        $store = new CookieStore($clock);
        $uri = Uri::fromString('https://example.com/');
        $store->absorb($this->responseWith('sid=abc'), $uri);

        $store->absorb($this->responseWith('sid=; Expires=Mon, 24 Aug 2026 00:00:00 GMT'), $uri);

        static::assertSame([], $store->all());
    }

    #[Test]
    public function aSecurePrefixedCookieIsAcceptedWhenItIsSecure(): void
    {
        $store = new CookieStore(new TestClock('2026-08-25 00:00:00'));

        $store->absorb($this->responseWith('__Secure-sid=abc; Secure'), Uri::fromString('https://example.com/'));

        static::assertCount(1, $store->all());
    }

    #[Test]
    public function aSecurePrefixedCookieIsRejectedWhenItIsNotSecure(): void
    {
        $store = new CookieStore(new TestClock('2026-08-25 00:00:00'));

        $store->absorb($this->responseWith('__Secure-sid=abc'), Uri::fromString('https://example.com/'));

        static::assertSame([], $store->all());
    }

    #[Test]
    public function aHostPrefixedCookieIsAcceptedWhenItMeetsEveryCondition(): void
    {
        $store = new CookieStore(new TestClock('2026-08-25 00:00:00'));

        $store->absorb(
            $this->responseWith('__Host-sid=abc; Secure; Path=/'),
            Uri::fromString('https://example.com/')
        );

        static::assertCount(1, $store->all());
    }

    #[Test]
    public function aHostPrefixedCookieIsRejectedWithoutSecure(): void
    {
        $store = new CookieStore(new TestClock('2026-08-25 00:00:00'));

        $store->absorb($this->responseWith('__Host-sid=abc; Path=/'), Uri::fromString('https://example.com/'));

        static::assertSame([], $store->all());
    }

    #[Test]
    public function aHostPrefixedCookieIsRejectedWithADomain(): void
    {
        $store = new CookieStore(new TestClock('2026-08-25 00:00:00'));

        $store->absorb(
            $this->responseWith('__Host-sid=abc; Secure; Path=/; Domain=example.com'),
            Uri::fromString('https://example.com/')
        );

        static::assertSame([], $store->all());
    }

    #[Test]
    public function aHostPrefixedCookieIsRejectedWithoutAnExplicitRootPath(): void
    {
        $store = new CookieStore(new TestClock('2026-08-25 00:00:00'));

        $store->absorb($this->responseWith('__Host-sid=abc; Secure'), Uri::fromString('https://example.com/'));

        static::assertSame([], $store->all());
    }

    #[Test]
    public function attachToSendsAMatchingCookie(): void
    {
        $store = new CookieStore(new TestClock('2026-08-25 00:00:00'));
        $store->absorb($this->responseWith('sid=abc'), Uri::fromString('https://example.com/'));

        $request = $store->attachTo(new Request('GET', 'https://example.com/v1/things'));

        static::assertSame('sid=abc', $request->getHeaderLine('Cookie'));
    }

    #[Test]
    public function attachToSendsThePercentEncodedValueVerbatim(): void
    {
        $store = new CookieStore(new TestClock('2026-08-25 00:00:00'));
        $store->absorb($this->responseWith('sid=a%20b'), Uri::fromString('https://example.com/'));

        $request = $store->attachTo(new Request('GET', 'https://example.com/'));

        static::assertSame('sid=a%20b', $request->getHeaderLine('Cookie'));
    }

    #[Test]
    public function attachToSendsAValueWhichWasNeverEncodedVerbatim(): void
    {
        $store = new CookieStore(new TestClock('2026-08-25 00:00:00'));
        $store->absorb($this->responseWith('sid=dK9x+7Qf/aQ='), Uri::fromString('https://example.com/'));

        $request = $store->attachTo(new Request('GET', 'https://example.com/'));

        static::assertSame('sid=dK9x+7Qf/aQ=', $request->getHeaderLine('Cookie'));
    }

    #[Test]
    public function attachToKeepsTheQuotesAValueArrivedWith(): void
    {
        $store = new CookieStore(new TestClock('2026-08-25 00:00:00'));
        $store->absorb($this->responseWith('sid="a"'), Uri::fromString('https://example.com/'));

        $request = $store->attachTo(new Request('GET', 'https://example.com/'));

        static::assertSame('sid="a"', $request->getHeaderLine('Cookie'));
        static::assertSame('a', $store->all()[0]->value);
    }

    #[Test]
    public function absorbRejectsASecureCookieOverPlainHttp(): void
    {
        $store = new CookieStore(new TestClock('2026-08-25 00:00:00'));

        $store->absorb($this->responseWith('sid=abc; Secure'), Uri::fromString('http://example.com/'));

        static::assertSame([], $store->all());
    }

    #[Test]
    public function absorbRejectsAHostPrefixedCookieOverPlainHttp(): void
    {
        $store = new CookieStore(new TestClock('2026-08-25 00:00:00'));

        $store->absorb($this->responseWith('__Host-sid=abc; Secure; Path=/'), Uri::fromString('http://example.com/'));

        static::assertSame([], $store->all());
    }

    #[Test]
    public function absorbDoesNotLetAPlainHttpResponseOverwriteASecureEntry(): void
    {
        $store = new CookieStore(new TestClock('2026-08-25 00:00:00'));
        $store->absorb($this->responseWith('sid=good; Secure; Path=/'), Uri::fromString('https://example.com/'));

        $store->absorb($this->responseWith('sid=attacker; Path=/'), Uri::fromString('http://example.com/'));

        $all = $store->all();
        static::assertCount(1, $all);
        static::assertSame('good', $all[0]->value);
        static::assertTrue($all[0]->secure);
    }

    #[Test]
    public function attachToLeavesTheRequestUntouchedWhenNothingMatches(): void
    {
        $store = new CookieStore(new TestClock('2026-08-25 00:00:00'));
        $request = new Request('GET', 'https://example.com/');

        static::assertFalse($store->attachTo($request)->hasHeader('Cookie'));
    }

    #[Test]
    public function attachToDoesNotSendAHostOnlyCookieToASubdomain(): void
    {
        $store = new CookieStore(new TestClock('2026-08-25 00:00:00'));
        $store->absorb($this->responseWith('sid=abc'), Uri::fromString('https://example.com/'));

        $request = $store->attachTo(new Request('GET', 'https://api.example.com/'));

        static::assertFalse($request->hasHeader('Cookie'));
    }

    #[Test]
    public function attachToSendsADomainCookieToASubdomain(): void
    {
        $store = new CookieStore(new TestClock('2026-08-25 00:00:00'));
        $store->absorb(
            $this->responseWith('sid=abc; Domain=example.com'),
            Uri::fromString('https://example.com/')
        );

        $request = $store->attachTo(new Request('GET', 'https://api.example.com/'));

        static::assertSame('sid=abc', $request->getHeaderLine('Cookie'));
    }

    #[Test]
    public function attachToRespectsThePath(): void
    {
        $store = new CookieStore(new TestClock('2026-08-25 00:00:00'));
        $store->absorb($this->responseWith('sid=abc; Path=/admin'), Uri::fromString('https://example.com/'));

        static::assertFalse($store->attachTo(new Request('GET', 'https://example.com/public'))->hasHeader('Cookie'));
        static::assertSame(
            'sid=abc',
            $store->attachTo(new Request('GET', 'https://example.com/admin/users'))->getHeaderLine('Cookie')
        );
    }

    #[Test]
    public function attachToDoesNotSendASecureCookieOverPlainHttp(): void
    {
        $store = new CookieStore(new TestClock('2026-08-25 00:00:00'));
        $store->absorb($this->responseWith('sid=abc; Secure'), Uri::fromString('https://example.com/'));

        static::assertFalse($store->attachTo(new Request('GET', 'http://example.com/'))->hasHeader('Cookie'));
        static::assertSame(
            'sid=abc',
            $store->attachTo(new Request('GET', 'https://example.com/'))->getHeaderLine('Cookie')
        );
    }

    #[Test]
    public function attachToDoesNotSendAnExpiredCookie(): void
    {
        $clock = new TestClock('2026-08-25 00:00:00');
        $store = new CookieStore($clock);
        $store->absorb($this->responseWith('sid=abc; Max-Age=60'), Uri::fromString('https://example.com/'));

        $clock->advance('PT61S');

        static::assertFalse($store->attachTo(new Request('GET', 'https://example.com/'))->hasHeader('Cookie'));
    }

    #[Test]
    public function attachToOrdersByLongestPathThenOldestFirst(): void
    {
        $store = new CookieStore(new TestClock('2026-08-25 00:00:00'));
        $uri = Uri::fromString('https://example.com/');
        $store->absorb($this->responseWith('a=1; Path=/'), $uri);
        $store->absorb($this->responseWith('b=2; Path=/admin'), $uri);
        $store->absorb($this->responseWith('c=3; Path=/'), $uri);

        $request = $store->attachTo(new Request('GET', 'https://example.com/admin/users'));

        static::assertSame('b=2; a=1; c=3', $request->getHeaderLine('Cookie'));
    }

    #[Test]
    public function attachToLetsAnExistingCookieHeaderWin(): void
    {
        $store = new CookieStore(new TestClock('2026-08-25 00:00:00'));
        $uri = Uri::fromString('https://example.com/');
        $store->absorb($this->responseWith('sid=stored'), $uri);
        $store->absorb($this->responseWith('other=stored'), $uri);

        $request = $store->attachTo(
            new Request('GET', 'https://example.com/', ['Cookie' => 'sid=explicit'])
        );

        static::assertSame('sid=explicit; other=stored', $request->getHeaderLine('Cookie'));
    }

    private function responseWith(string $setCookie): Response
    {
        return new Response(headers: ['Set-Cookie' => $setCookie]);
    }
}
