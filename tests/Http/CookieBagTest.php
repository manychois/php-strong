<?php

declare(strict_types=1);

namespace Manychois\PhpStrongTests\Http;

use Manychois\PhpStrong\Http\Cookie;
use Manychois\PhpStrong\Http\CookieBag;
use Manychois\PhpStrong\Http\Response;
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

    #[Test]
    public function applyToAppendsOneHeaderPerQueuedCookie(): void
    {
        $bag = CookieBag::fromRequest(new ServerRequest());
        $bag->set(new Cookie('theme', 'dark'));
        $bag->set(new Cookie('lang', 'en', path: '/'));

        $response = $bag->applyTo(new Response());

        static::assertSame(['theme=dark', 'lang=en; Path=/'], $response->getHeader('Set-Cookie'));
    }

    #[Test]
    public function applyToPreservesASetCookieHeaderAlreadyOnTheResponse(): void
    {
        $bag = CookieBag::fromRequest(new ServerRequest());
        $bag->set(new Cookie('theme', 'dark'));

        $response = $bag->applyTo(new Response(headers: ['Set-Cookie' => 'existing=1']));

        static::assertSame(['existing=1', 'theme=dark'], $response->getHeader('Set-Cookie'));
    }

    #[Test]
    public function setReplacesACookieWithTheSameNameDomainAndPath(): void
    {
        $bag = CookieBag::fromRequest(new ServerRequest());
        $bag->set(new Cookie('theme', 'dark', path: '/'));
        $bag->set(new Cookie('theme', 'light', path: '/'));

        static::assertCount(1, $bag->queued());
        static::assertSame(['theme=light; Path=/'], $bag->applyTo(new Response())->getHeader('Set-Cookie'));
    }

    #[Test]
    public function setKeepsCookiesWhichDifferByPathOrDomain(): void
    {
        $bag = CookieBag::fromRequest(new ServerRequest());
        $bag->set(new Cookie('theme', 'dark', path: '/'));
        $bag->set(new Cookie('theme', 'light', path: '/admin'));
        $bag->set(new Cookie('theme', 'blue', domain: 'example.com', path: '/'));

        static::assertCount(3, $bag->queued());
    }

    #[Test]
    public function expireQueuesAClearingCookie(): void
    {
        $bag = CookieBag::fromRequest(new ServerRequest());
        $bag->expire('sid', 'example.com', '/app');

        $header = $bag->applyTo(new Response())->getHeader('Set-Cookie');

        static::assertCount(1, $header);
        static::assertStringContainsString('sid=;', $header[0]);
        static::assertStringContainsString('Max-Age=-1', $header[0]);
        static::assertStringContainsString('Expires=Thu, 01 Jan 1970 00:00:00 GMT', $header[0]);
        static::assertStringContainsString('Domain=example.com', $header[0]);
        static::assertStringContainsString('Path=/app', $header[0]);
    }

    #[Test]
    public function setThenExpireCollapsesToASingleExpiryHeader(): void
    {
        $bag = CookieBag::fromRequest(new ServerRequest());
        $bag->set(new Cookie('sid', 'abc', domain: 'example.com', path: '/app'));
        $bag->expire('sid', 'example.com', '/app');

        $header = $bag->applyTo(new Response())->getHeader('Set-Cookie');

        static::assertCount(1, $header);
        static::assertStringContainsString('Max-Age=-1', $header[0]);
    }

    #[Test]
    public function applyToLeavesTheResponseAloneWhenNothingIsQueued(): void
    {
        $bag = CookieBag::fromRequest(new ServerRequest());
        $response = new Response();

        static::assertFalse($bag->applyTo($response)->hasHeader('Set-Cookie'));
    }
}
