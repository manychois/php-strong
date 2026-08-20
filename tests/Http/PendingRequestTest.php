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

/**
 * Unit tests for {@see PendingRequest}. Transfer-level behaviour is covered by
 * feature tests; here settle() is driven directly for branches a well-behaved
 * HTTP server cannot produce.
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

    private function makePending(): PendingRequest
    {
        $handle = curl_init();
        assert($handle !== false);

        return new PendingRequest(new CurlMultiExecutor(), $handle, new Request('GET', 'http://example.com/'));
    }
}
