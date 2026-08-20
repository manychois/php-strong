<?php

declare(strict_types=1);

namespace Manychois\PhpStrongTests\Http;

use InvalidArgumentException;
use Manychois\PhpStrong\Http\ClientException;
use Manychois\PhpStrong\Http\Internal\CurlMultiExecutor;
use Manychois\PhpStrong\Http\PendingRequest;
use Manychois\PhpStrong\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

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

        $pending->settle(\CURLE_OK, '', ['not a status line'], 'body');

        $this->expectException(ClientException::class);
        $this->expectExceptionMessage('Malformed HTTP response');
        $pending->response();
    }

    #[Test]
    public function settled_response_is_returned_without_pumping(): void
    {
        $pending = $this->makePending();

        $pending->settle(\CURLE_OK, '', ['HTTP/1.1 204 No Content'], '');

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
        $pending->settle(\CURLE_OK, '', ['HTTP/1.1 200 OK'], 'ok');

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
        $pending->settle(\CURLE_OK, '', ['HTTP/1.1 200 OK'], 'ok');
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

    private function makePending(): PendingRequest
    {
        $handle = curl_init();
        assert($handle !== false);

        return new PendingRequest(new CurlMultiExecutor(), $handle, new Request('GET', 'http://example.com/'));
    }
}
