<?php

declare(strict_types=1);

namespace Manychois\PhpStrongTests\Http;

use Manychois\PhpStrong\Http\ClientException;
use Manychois\PhpStrong\Http\Request;
use Manychois\PhpStrong\Http\RequestException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\RequestExceptionInterface;
use RuntimeException;

/**
 * Unit tests for {@see RequestException}.
 */
final class RequestExceptionTest extends TestCase
{
    #[Test]
    public function it_exposes_message_request_and_previous(): void
    {
        $request = new Request('GET', 'http://example.com/');
        $previous = new RuntimeException('boom');

        $ex = new RequestException('Bad request.', $request, $previous);

        self::assertInstanceOf(ClientException::class, $ex);
        self::assertInstanceOf(RequestExceptionInterface::class, $ex);
        self::assertSame('Bad request.', $ex->getMessage());
        self::assertSame($request, $ex->getRequest());
        self::assertSame($previous, $ex->getPrevious());
    }
}
