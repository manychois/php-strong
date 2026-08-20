<?php

declare(strict_types=1);

namespace Manychois\PhpStrongFeatureTests\Http;

use Manychois\PhpStrong\Http\Client;
use Manychois\PhpStrong\Http\NetworkException;
use Manychois\PhpStrong\Http\Request;
use Manychois\PhpStrong\Http\RequestOptions;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Feature tests for {@see Client} against a real PHP built-in server.
 */
final class ClientFeatureTest extends TestCase
{
    use FixtureServerTrait;

    public static function setUpBeforeClass(): void
    {
        self::startFixtureServer();
    }

    public static function tearDownAfterClass(): void
    {
        self::stopFixtureServer();
    }

    #[Test]
    public function get_request_returns_status_headers_and_body(): void
    {
        $client = new Client();

        $response = $client->sendRequest(new Request('GET', self::url('/hello')));

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('OK', $response->getReasonPhrase());
        self::assertSame('php-strong', $response->getHeaderLine('X-Server'));
        self::assertSame('Hello, world!', (string) $response->getBody());
        self::assertSame('1.1', $response->getProtocolVersion());
    }

    #[Test]
    public function post_request_sends_body_and_headers(): void
    {
        $client = new Client();
        $request = new Request('POST', self::url('/echo'), ['X-Custom' => 'abc'], 'payload');

        $response = $client->sendRequest($request);

        self::assertSame('POST|payload|abc', (string) $response->getBody());
    }

    #[Test]
    public function error_status_is_returned_as_a_response_not_an_exception(): void
    {
        $client = new Client();

        $response = $client->sendRequest(new Request('GET', self::url('/status?code=503')));

        self::assertSame(503, $response->getStatusCode());
        self::assertSame('status body', (string) $response->getBody());
    }

    #[Test]
    public function redirects_are_not_followed(): void
    {
        $client = new Client();

        $response = $client->sendRequest(new Request('GET', self::url('/redirect')));

        self::assertSame(302, $response->getStatusCode());
        self::assertSame('/hello', $response->getHeaderLine('Location'));
    }

    #[Test]
    public function repeated_headers_are_preserved(): void
    {
        $client = new Client();

        $response = $client->sendRequest(new Request('GET', self::url('/cookies')));

        self::assertSame(['a=1', 'b=2'], $response->getHeader('Set-Cookie'));
    }

    #[Test]
    public function head_request_returns_no_body(): void
    {
        $client = new Client();

        $response = $client->sendRequest(new Request('HEAD', self::url('/hello')));

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('', (string) $response->getBody());
    }

    #[Test]
    public function redirects_are_followed_when_enabled(): void
    {
        $client = new Client(new RequestOptions(followRedirects: true));

        $response = $client->sendRequest(new Request('GET', self::url('/redirect')));

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('Hello, world!', (string) $response->getBody());
    }

    #[Test]
    public function default_user_agent_is_sent_when_request_has_none(): void
    {
        $client = new Client(new RequestOptions(userAgent: 'php-strong/1.0'));

        $response = $client->sendRequest(new Request('GET', self::url('/ua')));

        self::assertSame('php-strong/1.0', (string) $response->getBody());
    }

    #[Test]
    public function connection_to_a_closed_port_throws_NetworkException(): void
    {
        $client = new Client(new RequestOptions(timeout: 1.0));
        $request = new Request('GET', sprintf('http://127.0.0.1:%d/', self::findFreePort()));

        try {
            $client->sendRequest($request);
            self::fail('Expected NetworkException.');
        } catch (NetworkException $ex) {
            self::assertSame($request, $ex->getRequest());
            self::assertStringContainsString('cURL error', $ex->getMessage());
        }
    }

    #[Test]
    public function slow_response_throws_NetworkException_on_timeout(): void
    {
        $client = new Client(new RequestOptions(timeout: 0.2));

        $this->expectException(NetworkException::class);

        $client->sendRequest(new Request('GET', self::url('/slow')));
    }
}
