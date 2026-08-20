<?php

declare(strict_types=1);

namespace Manychois\PhpStrongFeatureTests\Http;

use Manychois\PhpStrong\Http\Client;
use Manychois\PhpStrong\Http\NetworkException;
use Manychois\PhpStrong\Http\PendingRequest;
use Manychois\PhpStrong\Http\Request;
use Manychois\PhpStrong\Http\RequestOptions;
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

    #[Test]
    public function transfer_failure_throws_NetworkException_from_response(): void
    {
        $client = new Client();
        $request = new Request('GET', sprintf('http://127.0.0.1:%d/', self::findFreePort()));

        $pending = $client->sendAsync($request);

        try {
            $pending->response();
            self::fail('Expected NetworkException.');
        } catch (NetworkException $ex) {
            self::assertSame($request, $ex->getRequest());
            self::assertStringContainsString('cURL error', $ex->getMessage());
        }
    }

    #[Test]
    public function timeout_throws_NetworkException_from_response(): void
    {
        $client = new Client(new RequestOptions(timeout: 0.2));

        $pending = $client->sendAsync(new Request('GET', self::url('/slow?ms=300')));

        $this->expectException(NetworkException::class);
        $pending->response();
    }

    #[Test]
    public function response_is_idempotent_for_success_and_failure(): void
    {
        $client = new Client();

        $ok = $client->sendAsync(new Request('GET', self::url('/hello')));
        self::assertSame($ok->response(), $ok->response());

        $bad = $client->sendAsync(
            new Request('GET', sprintf('http://127.0.0.1:%d/', self::findFreePort())),
        );
        $first = null;
        try {
            $bad->response();
        } catch (NetworkException $ex) {
            $first = $ex;
        }
        try {
            $bad->response();
            self::fail('Expected NetworkException on second call.');
        } catch (NetworkException $ex) {
            self::assertSame($first, $ex);
        }
    }

    #[Test]
    public function mixed_batch_delivers_each_outcome_to_its_own_handle(): void
    {
        $client = new Client();

        $ok = $client->sendAsync(new Request('GET', self::url('/hello')));
        $serverError = $client->sendAsync(new Request('GET', self::url('/status?code=500')));
        $failed = $client->sendAsync(
            new Request('GET', sprintf('http://127.0.0.1:%d/', self::findFreePort())),
        );

        self::assertSame(200, $ok->response()->getStatusCode());
        self::assertSame(500, $serverError->response()->getStatusCode());
        $this->expectException(NetworkException::class);
        $failed->response();
    }

    #[Test]
    public function waitAny_returns_the_fastest_of_a_batch(): void
    {
        $client = new Client();

        $slow = $client->sendAsync(new Request('GET', self::url('/slow?ms=500')));
        $fast = $client->sendAsync(new Request('GET', self::url('/hello')));

        $winner = PendingRequest::waitAny([$slow, $fast]);
        self::assertSame($fast, $winner);
        self::assertSame('Hello, world!', (string) $winner->response()->getBody());

        self::assertSame($slow, PendingRequest::waitAny([$slow]));
        self::assertSame('slow', (string) $slow->response()->getBody());
    }

    #[Test]
    public function waitAny_spans_multiple_clients(): void
    {
        $clientA = new Client();
        $clientB = new Client();

        $slow = $clientA->sendAsync(new Request('GET', self::url('/slow?ms=400')));
        $fast = $clientB->sendAsync(new Request('GET', self::url('/hello')));

        self::assertSame($fast, PendingRequest::waitAny([$slow, $fast]));
        self::assertSame('slow', (string) $slow->response()->getBody());
    }

    #[Test]
    public function waitAny_returns_a_failed_transfer_as_completed(): void
    {
        $client = new Client();

        $failing = $client->sendAsync(
            new Request('GET', sprintf('http://127.0.0.1:%d/', self::findFreePort())),
        );
        $slow = $client->sendAsync(new Request('GET', self::url('/slow?ms=200')));

        $winner = PendingRequest::waitAny([$failing, $slow]);
        self::assertSame($failing, $winner);
        $this->expectException(NetworkException::class);
        $winner->response();
    }

    #[Test]
    public function discarded_pending_request_does_not_disturb_others(): void
    {
        $client = new Client();

        $discarded = $client->sendAsync(new Request('GET', self::url('/slow?ms=200')));
        $kept = $client->sendAsync(new Request('GET', self::url('/hello')));

        unset($discarded);

        $start = microtime(true);
        self::assertSame('Hello, world!', (string) $kept->response()->getBody());
        self::assertLessThan(0.5, microtime(true) - $start);
    }
}
