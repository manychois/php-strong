<?php

declare(strict_types=1);

namespace Manychois\PhpStrongFeatureTests\Http;

use Manychois\PhpStrong\Http\Client;
use Manychois\PhpStrong\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Feature tests for {@see Client::sendAsync()} against a real PHP built-in server.
 */
final class ClientAsyncFeatureTest extends TestCase
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
    public function sendAsync_returns_a_response_with_status_headers_and_body(): void
    {
        $client = new Client();

        $pending = $client->sendAsync(new Request('GET', self::url('/hello')));
        $response = $pending->response();

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('php-strong', $response->getHeaderLine('X-Server'));
        self::assertSame('Hello, world!', (string) $response->getBody());
    }

    #[Test]
    public function concurrent_requests_overlap_instead_of_running_serially(): void
    {
        $client = new Client();

        $start = microtime(true);
        $p1 = $client->sendAsync(new Request('GET', self::url('/slow?ms=400')));
        $p2 = $client->sendAsync(new Request('GET', self::url('/slow?ms=400')));
        self::assertSame('slow', (string) $p1->response()->getBody());
        self::assertSame('slow', (string) $p2->response()->getBody());
        $elapsed = microtime(true) - $start;

        self::assertLessThan(0.75, $elapsed, 'Two 400ms requests must overlap, not serialize.');
    }

    #[Test]
    public function responses_can_be_collected_in_any_order(): void
    {
        $client = new Client();

        $slow = $client->sendAsync(new Request('GET', self::url('/slow?ms=300')));
        $fast = $client->sendAsync(new Request('GET', self::url('/hello')));

        self::assertSame('slow', (string) $slow->response()->getBody());
        self::assertSame('Hello, world!', (string) $fast->response()->getBody());
    }

    #[Test]
    public function non_2xx_responses_are_returned_not_thrown(): void
    {
        $client = new Client();

        $pending = $client->sendAsync(new Request('GET', self::url('/status?code=503')));

        self::assertSame(503, $pending->response()->getStatusCode());
    }
}
