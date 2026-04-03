<?php

declare(strict_types=1);

namespace Manychois\PhpStrongTests\Web;

use InvalidArgumentException;
use Manychois\PhpStrong\Web\Response;
use Manychois\PhpStrong\Web\StreamFactory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for {@see Response}.
 */
final class ResponseTest extends TestCase
{
    #[Test]
    public function constructor_accepts_headers_body_and_protocol(): void
    {
        $streams = new StreamFactory();
        $body = $streams->createStream('ok');

        $response = new Response(
            statusCode: 201,
            reasonPhrase: 'Stored',
            headers: ['X-Trace' => '1'],
            body: $body,
            protocolVersion: '2',
        );

        self::assertSame(201, $response->getStatusCode());
        self::assertSame('Stored', $response->getReasonPhrase());
        self::assertSame(['1'], $response->getHeader('X-Trace'));
        self::assertSame($body, $response->getBody());
        self::assertSame('2', $response->getProtocolVersion());
    }

    #[Test]
    public function constructor_string_body_is_wrapped_as_stream(): void
    {
        $response = new Response(body: '<html/>');

        self::assertSame('<html/>', (string) $response->getBody());
    }

    #[Test]
    public function constructor_uses_empty_reason_when_code_not_in_StatusCode_enum(): void
    {
        $response = new Response(230);

        self::assertSame(230, $response->getStatusCode());
        self::assertSame('', $response->getReasonPhrase());
    }

    #[Test]
    public function constructor_throws_when_status_below_100(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid HTTP status code 99');
        new Response(99);
    }

    #[Test]
    public function constructor_throws_when_status_above_599(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid HTTP status code 600');
        new Response(600);
    }

    #[Test]
    public function withStatus_updates_code_and_default_reason(): void
    {
        $original = new Response(200, 'OK');
        $next = $original->withStatus(404);

        self::assertSame(200, $original->getStatusCode());
        self::assertSame('Not Found', $next->getReasonPhrase());
        self::assertSame(404, $next->getStatusCode());
    }

    #[Test]
    public function withStatus_honors_explicit_reason_phrase(): void
    {
        $next = (new Response())->withStatus(503, 'Back Soon');

        self::assertSame(503, $next->getStatusCode());
        self::assertSame('Back Soon', $next->getReasonPhrase());
    }

    #[Test]
    public function withStatus_throws_for_invalid_code(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid HTTP status code 0');
        (new Response())->withStatus(0);
    }
}
