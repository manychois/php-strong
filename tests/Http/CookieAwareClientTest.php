<?php

declare(strict_types=1);

namespace Manychois\PhpStrongTests\Http;

use Manychois\PhpStrong\Http\CookieAwareClient;
use Manychois\PhpStrong\Http\CookieStore;
use Manychois\PhpStrong\Http\Request;
use Manychois\PhpStrong\Http\Response;
use Manychois\PhpStrong\Http\Uri;
use Manychois\PhpStrong\Time\TestClock;
use Override;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface as IClient;
use Psr\Http\Message\RequestInterface as IRequest;
use Psr\Http\Message\ResponseInterface as IResponse;

/**
 * Unit tests for {@see CookieAwareClient}.
 */
final class CookieAwareClientTest extends TestCase
{
    #[Test]
    public function sendRequestAbsorbsTheCookiesTheResponseSets(): void
    {
        $store = new CookieStore(new TestClock('2026-08-25 00:00:00'));
        $inner = $this->fakeClient(new Response(headers: ['Set-Cookie' => 'sid=abc']));
        $client = new CookieAwareClient($inner, $store);

        $client->sendRequest(new Request('GET', 'https://example.com/login'));

        static::assertCount(1, $store->all());
        static::assertSame('sid', $store->all()[0]->name);
    }

    #[Test]
    public function sendRequestAttachesStoredCookiesToTheOutgoingRequest(): void
    {
        $store = new CookieStore(new TestClock('2026-08-25 00:00:00'));
        $inner = $this->fakeClient(new Response());
        $client = new CookieAwareClient($inner, $store);

        $store->absorb(
            new Response(headers: ['Set-Cookie' => 'sid=abc']),
            Uri::fromString('https://example.com/')
        );

        $client->sendRequest(new Request('GET', 'https://example.com/things'));

        static::assertNotNull($inner->lastRequest);
        static::assertSame('sid=abc', $inner->lastRequest->getHeaderLine('Cookie'));
    }

    #[Test]
    public function aLoginThenCallFlowCarriesTheSessionCookie(): void
    {
        $store = new CookieStore(new TestClock('2026-08-25 00:00:00'));
        $inner = $this->fakeClient(new Response(headers: ['Set-Cookie' => 'sid=abc; Path=/']));
        $client = new CookieAwareClient($inner, $store);

        $client->sendRequest(new Request('POST', 'https://example.com/login'));
        $inner->next = new Response();
        $client->sendRequest(new Request('GET', 'https://example.com/profile'));

        static::assertNotNull($inner->lastRequest);
        static::assertSame('sid=abc', $inner->lastRequest->getHeaderLine('Cookie'));
    }

    #[Test]
    public function sendRequestReturnsTheInnerResponseUnchanged(): void
    {
        $expected = new Response(201, 'Created');
        $client = new CookieAwareClient($this->fakeClient($expected), new CookieStore());

        static::assertSame($expected, $client->sendRequest(new Request('GET', 'https://example.com/')));
    }

    /**
     * Creates a client stub that records the request it was given.
     *
     * @return IClient&object{lastRequest: ?IRequest, next: Response}
     */
    private function fakeClient(Response $response): IClient
    {
        return new class ($response) implements IClient {
            public ?IRequest $lastRequest = null;

            public function __construct(public Response $next)
            {
            }

            #[Override]
            public function sendRequest(IRequest $request): IResponse
            {
                $this->lastRequest = $request;

                return $this->next;
            }
        };
    }
}
