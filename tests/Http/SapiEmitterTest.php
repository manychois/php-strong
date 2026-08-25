<?php

declare(strict_types=1);

namespace Manychois\PhpStrongTests\Http;

use InvalidArgumentException;
use Manychois\PhpStrong\Http\Cookie;
use Manychois\PhpStrong\Http\CookieBag;
use Manychois\PhpStrong\Http\Request;
use Manychois\PhpStrong\Http\Response;
use Manychois\PhpStrong\Http\SapiEmitter;
use Manychois\PhpStrong\Http\ServerRequest;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\StreamInterface as IStream;
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

    #[Test]
    #[DataProvider('provideStatusesForbiddingABody')]
    public function emitNeverTouchesTheBodyWhenTheStatusForbidsOne(int $status): void
    {
        $body = $this->createMock(IStream::class);
        $body->expects(static::never())->method('read');
        $body->expects(static::never())->method('rewind');
        $body->expects(static::never())->method('__toString');

        (new SapiEmitter())->emit(new Response($status, body: $body));
    }

    /**
     * @return iterable<string,array{int}>
     */
    public static function provideStatusesForbiddingABody(): iterable
    {
        yield '100 Continue' => [100];
        yield '199 unassigned informational' => [199];
        yield '204 No Content' => [204];
        yield '304 Not Modified' => [304];
    }

    #[Test]
    #[DataProvider('provideHeadMethodCasings')]
    public function emitNeverTouchesTheBodyWhenAnsweringAHeadRequest(string $method): void
    {
        $body = $this->createMock(IStream::class);
        $body->expects(static::never())->method('read');

        $request = new Request($method, 'https://example.com/');

        (new SapiEmitter())->emit(new Response(200, body: $body), $request);
    }

    /**
     * @return iterable<string,array{string}>
     */
    public static function provideHeadMethodCasings(): iterable
    {
        yield 'uppercase' => ['HEAD'];
        yield 'lowercase' => ['head'];
        yield 'mixed case' => ['Head'];
    }

    #[Test]
    public function emitStillSendsTheHeadersOfASuppressedBody(): void
    {
        $response = (new Response(304))->withHeader('ETag', '"v1"');

        (new SapiEmitter())->emit($response);

        static::assertSame([
            ['HTTP/1.1 304 Not Modified', true, 304],
            ['ETag: "v1"', true, 0],
        ], SapiSpy::recorded());
    }
}
