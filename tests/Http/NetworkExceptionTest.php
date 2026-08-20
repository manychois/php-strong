<?php

declare(strict_types=1);

namespace Manychois\PhpStrongTests\Http;

use Manychois\PhpStrong\Http\ClientException;
use Manychois\PhpStrong\Http\NetworkException;
use Manychois\PhpStrong\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\NetworkExceptionInterface;

/**
 * Unit tests for {@see NetworkException}.
 */
final class NetworkExceptionTest extends TestCase
{
    #[Test]
    public function it_exposes_message_and_request(): void
    {
        $request = new Request('GET', 'http://example.com/');

        $ex = new NetworkException('Connection refused.', $request);

        self::assertInstanceOf(ClientException::class, $ex);
        self::assertInstanceOf(NetworkExceptionInterface::class, $ex);
        self::assertSame('Connection refused.', $ex->getMessage());
        self::assertSame($request, $ex->getRequest());
        self::assertNull($ex->getPrevious());
    }
}
