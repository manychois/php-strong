<?php

declare(strict_types=1);

namespace Manychois\PhpStrongTests\Http;

use InvalidArgumentException;
use Manychois\PhpStrong\Http\Cookie;
use Manychois\PhpStrong\Http\CookieBag;
use Manychois\PhpStrong\Http\Response;
use Manychois\PhpStrong\Http\SapiEmitter;
use Manychois\PhpStrong\Http\ServerRequest;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Unit tests for {@see SapiEmitter}.
 */
final class SapiEmitterTest extends TestCase
{
    protected function setUp(): void
    {
        SapiSpy::reset();
    }

    #[Test]
    public function constructorRejectsAChunkSizeBelowOne(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The chunk size must be at least 1 byte; got 0.');

        new SapiEmitter(0);
    }

    #[Test]
    public function emitThrowsWhenOutputHasAlreadyStarted(): void
    {
        SapiSpy::markSent('/app/public/index.php', 12);
        $emitter = new SapiEmitter();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cannot emit the response: output already started at /app/public/index.php:12.');

        $emitter->emit(new Response());
    }

    #[Test]
    public function emitWritesNothingWhenOutputHasAlreadyStarted(): void
    {
        SapiSpy::markSent('/app/public/index.php', 12);
        $emitter = new SapiEmitter();

        try {
            $emitter->emit(new Response());
        } catch (RuntimeException) {
            // The throw is asserted by its own test; this one asserts nothing leaked out before it.
        }

        static::assertSame([], SapiSpy::recorded());
    }

    #[Test]
    public function emitSendsTheStatusLineWithTheDefaultReasonPhrase(): void
    {
        (new SapiEmitter())->emit(new Response(404));

        static::assertSame(['HTTP/1.1 404 Not Found', true, 404], SapiSpy::recorded()[0]);
    }

    #[Test]
    public function emitSendsTheStatusLineWithACustomReasonPhrase(): void
    {
        (new SapiEmitter())->emit(new Response(418, 'I Am A Teapot'));

        static::assertSame(['HTTP/1.1 418 I Am A Teapot', true, 418], SapiSpy::recorded()[0]);
    }

    #[Test]
    public function emitOmitsTheTrailingSpaceWhenThereIsNoReasonPhrase(): void
    {
        (new SapiEmitter())->emit(new Response(599));

        static::assertSame(['HTTP/1.1 599', true, 599], SapiSpy::recorded()[0]);
    }

    #[Test]
    public function emitSendsTheProtocolVersionTheResponseCarries(): void
    {
        (new SapiEmitter())->emit(new Response(200, protocolVersion: '2'));

        static::assertSame(['HTTP/2 200 OK', true, 200], SapiSpy::recorded()[0]);
    }

    #[Test]
    public function emitReplacesOnTheFirstValueAndAppendsOnTheRest(): void
    {
        $response = (new Response())
            ->withHeader('Vary', 'Accept')
            ->withAddedHeader('Vary', 'Accept-Encoding')
            ->withAddedHeader('Vary', 'Origin');

        (new SapiEmitter())->emit($response);

        static::assertSame([
            ['Vary: Accept', true, 0],
            ['Vary: Accept-Encoding', false, 0],
            ['Vary: Origin', false, 0],
        ], array_slice(SapiSpy::recorded(), 1));
    }

    #[Test]
    public function emitNeverReplacesOnSetCookieNotEvenTheFirstValue(): void
    {
        $response = (new Response())
            ->withHeader('Set-Cookie', 'a=1')
            ->withAddedHeader('Set-Cookie', 'b=2');

        (new SapiEmitter())->emit($response);

        static::assertSame([
            ['Set-Cookie: a=1', false, 0],
            ['Set-Cookie: b=2', false, 0],
        ], array_slice(SapiSpy::recorded(), 1));
    }

    #[Test]
    public function theSetCookieRuleIsCaseInsensitive(): void
    {
        $response = (new Response())->withHeader('set-cookie', 'a=1');

        (new SapiEmitter())->emit($response);

        static::assertSame([['set-cookie: a=1', false, 0]], array_slice(SapiSpy::recorded(), 1));
    }

    #[Test]
    public function everyCookieQueuedOnACookieBagSurvivesTheEmit(): void
    {
        $bag = CookieBag::fromRequest(new ServerRequest());
        $bag->set(new Cookie('sid', 'abc', path: '/'));
        $bag->set(new Cookie('theme', 'dark', path: '/'));

        (new SapiEmitter())->emit($bag->applyTo(new Response()));

        $recorded = array_slice(SapiSpy::recorded(), 1);

        static::assertCount(2, $recorded);
        foreach ($recorded as $call) {
            static::assertStringStartsWith('Set-Cookie: ', $call[0]);
            static::assertFalse($call[1], 'A Set-Cookie header must never be emitted with replace = true.');
        }
    }

    #[Test]
    public function emitPreservesHeaderNameCasingAndDoesNotTrimValues(): void
    {
        $response = (new Response())->withHeader('X-Weird-CASE', 'a, b');

        (new SapiEmitter())->emit($response);

        static::assertSame([['X-Weird-CASE: a, b', true, 0]], array_slice(SapiSpy::recorded(), 1));
    }
}
