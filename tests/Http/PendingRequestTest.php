<?php

declare(strict_types=1);

namespace Manychois\PhpStrongTests\Http;

use InvalidArgumentException;
use Manychois\PhpStrong\Http\ClientException;
use Manychois\PhpStrong\Http\Internal\CurlMultiExecutor;
use Manychois\PhpStrong\Http\PendingRequest;
use Manychois\PhpStrong\Http\Request;
use Manychois\PhpStrong\Http\Response;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

require_once __DIR__ . '/Internal/CurlMultiSelectStub.php';

/**
 * Unit tests for {@see PendingRequest} and its {@see CurlMultiExecutor}. Transfer-level
 * behaviour is covered by feature tests; here settle()/pump()/remove() are driven
 * directly for branches a well-behaved HTTP server cannot produce.
 */
final class PendingRequestTest extends TestCase
{
    #[Test]
    public function unparseable_response_throws_ClientException(): void
    {
        $pending = $this->makePending();

        $this->settle($pending, \CURLE_OK, '', ['not a status line'], 'body');

        $this->expectException(ClientException::class);
        $this->expectExceptionMessage('Malformed HTTP response');
        $pending->response();
    }

    #[Test]
    public function settled_response_is_returned_without_pumping(): void
    {
        $pending = $this->makePending();

        $this->settle($pending, \CURLE_OK, '', ['HTTP/1.1 204 No Content'], '');

        $response = $pending->response();
        self::assertSame(204, $response->getStatusCode());
        self::assertSame($response, $pending->response());
    }

    #[Test]
    public function waitAny_rejects_empty_input(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('at least one');

        PendingRequest::waitAny([]);
    }

    #[Test]
    public function waitAny_rejects_non_PendingRequest_items(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('PendingRequest');

        PendingRequest::waitAny(['not a pending request']);
    }

    #[Test]
    public function waitAny_returns_an_already_settled_request_immediately(): void
    {
        $pending = $this->makePending();
        $this->settle($pending, \CURLE_OK, '', ['HTTP/1.1 200 OK'], 'ok');

        self::assertSame($pending, PendingRequest::waitAny([$pending]));
    }

    #[Test]
    public function destructor_aborts_unsettled_transfer(): void
    {
        $executor = new CurlMultiExecutor();
        $handle = curl_init();
        assert($handle !== false);

        $pending = new PendingRequest($executor, $handle, new Request('GET', 'http://example.com/'));
        self::assertSame(1, $executor->activeCount());

        unset($pending);

        self::assertSame(0, $executor->activeCount());
    }

    #[Test]
    public function destructor_preserves_settled_transfer_registration(): void
    {
        $executor = new CurlMultiExecutor();
        $handle = curl_init();
        assert($handle !== false);

        $pending = new PendingRequest($executor, $handle, new Request('GET', 'http://example.com/'));
        $this->settle($pending, \CURLE_OK, '', ['HTTP/1.1 200 OK'], 'ok');
        self::assertSame(1, $executor->activeCount());

        unset($pending);

        self::assertSame(1, $executor->activeCount());
    }

    #[Test]
    public function executor_pump_with_no_transfers_does_nothing(): void
    {
        $executor = new CurlMultiExecutor();

        $executor->pump(0.0);

        self::assertSame(0, $executor->activeCount());
    }

    #[Test]
    public function executor_remove_is_safe_to_call_twice(): void
    {
        $executor = new CurlMultiExecutor();
        $handle = curl_init();
        assert($handle !== false);
        $executor->add($handle, static function (): void {
        });
        self::assertSame(1, $executor->activeCount());

        $executor->remove($handle);
        self::assertSame(0, $executor->activeCount());

        $executor->remove($handle);
        self::assertSame(0, $executor->activeCount());
    }

    #[Test]
    public function executor_add_throws_ClientException_when_multi_add_handle_fails(): void
    {
        $executor = new CurlMultiExecutor();
        $handle = curl_init();
        assert($handle !== false);
        $first = new PendingRequest($executor, $handle, new Request('GET', 'http://example.com/'));

        $this->expectException(ClientException::class);
        $this->expectExceptionMessage('cURL multi error');
        try {
            new PendingRequest($executor, $handle, new Request('GET', 'http://example.com/'));
        } finally {
            unset($first);
        }
    }

    // The guards below are phpstan-required defensive narrowing that a well-behaved
    // curl_multi_info_read() entry can never trigger. Deleting a guard makes the next
    // line throw a TypeError/Error, so the trivial activeCount assertion is sufficient
    // to prove each guard short-circuits without crashing.

    #[Test]
    public function executor_deliverCompletion_falls_back_to_curl_strerror_when_curl_error_is_empty(): void
    {
        $executor = new CurlMultiExecutor();
        $handle = curl_init();
        assert($handle !== false);
        /** @var list<array{int,string,list<string>,string}> $delivered */
        $delivered = [];
        $executor->add($handle, static function (int $errno, string $error, array $headerLines, string $body) use (&$delivered): void {
            $delivered[] = [$errno, $error, $headerLines, $body];
        });
        $method = new ReflectionMethod($executor, 'deliverCompletion');

        // The handle was never executed, so curl_error($handle) is '' and the
        // implementation must fall back to curl_strerror($errno).
        $method->invoke($executor, ['handle' => $handle, 'result' => \CURLE_COULDNT_CONNECT]);

        self::assertCount(1, $delivered);
        self::assertStringContainsString(curl_strerror(\CURLE_COULDNT_CONNECT) ?? '', $delivered[0][1]);
    }

    #[Test]
    public function executor_pump_falls_back_to_a_short_sleep_when_select_reports_no_descriptors(): void
    {
        $server = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
        assert($server !== false);
        $address = stream_socket_get_name($server, false);
        assert($address !== false);
        [$host, $port] = explode(':', $address);

        $executor = new CurlMultiExecutor();
        $handle = curl_init(sprintf('http://%s:%s/', $host, $port));
        assert($handle !== false);
        curl_setopt($handle, \CURLOPT_TIMEOUT_MS, 2000);
        $executor->add($handle, static function (): void {
        });

        $GLOBALS['__phpStrongForceCurlMultiSelectNegOne'] = true;
        try {
            // The connection is accepted at the TCP level but never answered, so the
            // transfer is still running and curl_multi_select() is invoked; the stub
            // forces it to report -1, exercising the usleep() fallback branch.
            $executor->pump(0.01);
        } finally {
            $GLOBALS['__phpStrongForceCurlMultiSelectNegOne'] = false;
            $executor->remove($handle);
            fclose($server);
        }

        self::assertSame(0, $executor->activeCount());
    }

    #[Test]
    public function executor_deliverCompletion_ignores_a_non_CurlHandle_entry(): void
    {
        $executor = new CurlMultiExecutor();
        $method = new ReflectionMethod($executor, 'deliverCompletion');

        $method->invoke($executor, ['handle' => 'not a handle', 'result' => \CURLE_OK]);

        self::assertSame(0, $executor->activeCount());
    }

    #[Test]
    public function executor_deliverCompletion_ignores_a_non_int_result(): void
    {
        $executor = new CurlMultiExecutor();
        $handle = curl_init();
        assert($handle !== false);
        $method = new ReflectionMethod($executor, 'deliverCompletion');

        $method->invoke($executor, ['handle' => $handle, 'result' => 'not an int']);

        self::assertSame(0, $executor->activeCount());
    }

    #[Test]
    public function executor_deliverCompletion_ignores_an_untracked_handle(): void
    {
        $executor = new CurlMultiExecutor();
        $handle = curl_init();
        assert($handle !== false);
        $method = new ReflectionMethod($executor, 'deliverCompletion');

        $method->invoke($executor, ['handle' => $handle, 'result' => \CURLE_OK]);

        self::assertSame(0, $executor->activeCount());
    }

    #[Test]
    public function onResponse_fires_when_the_transfer_succeeds(): void
    {
        $pending = $this->makePending();
        $seen = [];
        $pending->onResponse(static function (Response $response) use (&$seen): void {
            $seen[] = $response->getStatusCode();
        });

        $this->settle($pending, \CURLE_OK, '', ['HTTP/1.1 200 OK'], 'body');

        self::assertSame([200], $seen);
    }

    #[Test]
    public function onResponse_does_not_fire_when_the_transfer_fails(): void
    {
        $pending = $this->makePending();
        $fired = false;
        $pending->onResponse(static function () use (&$fired): void {
            $fired = true;
        });

        $this->settle($pending, \CURLE_COULDNT_CONNECT, 'connection refused', [], '');

        self::assertFalse($fired);
    }

    #[Test]
    public function onResponse_does_not_fire_when_the_response_cannot_be_parsed(): void
    {
        $pending = $this->makePending();
        $fired = false;
        $pending->onResponse(static function () use (&$fired): void {
            $fired = true;
        });

        $this->settle($pending, \CURLE_OK, '', ['not a status line'], 'body');

        self::assertFalse($fired);
    }

    #[Test]
    public function onResponse_fires_immediately_when_registered_after_settlement(): void
    {
        $pending = $this->makePending();
        $this->settle($pending, \CURLE_OK, '', ['HTTP/1.1 201 Created'], '');

        $seen = [];
        $pending->onResponse(static function (Response $response) use (&$seen): void {
            $seen[] = $response->getStatusCode();
        });

        self::assertSame([201], $seen);
    }

    private function makePending(): PendingRequest
    {
        $handle = curl_init();
        assert($handle !== false);

        return new PendingRequest(new CurlMultiExecutor(), $handle, new Request('GET', 'http://example.com/'));
    }

    /**
     * @param list<string> $headerLines
     */
    private function settle(
        PendingRequest $pending,
        int $errno,
        string $errorMessage,
        array $headerLines,
        string $body,
    ): void {
        $method = new ReflectionMethod($pending, 'settle');
        $method->invoke($pending, $errno, $errorMessage, $headerLines, $body);
    }
}
