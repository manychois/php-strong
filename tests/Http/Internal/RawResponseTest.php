<?php

declare(strict_types=1);

namespace Manychois\PhpStrongTests\Http\Internal;

use Manychois\PhpStrong\Http\ClientException;
use Manychois\PhpStrong\Http\Internal\RawResponse;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for {@see RawResponse}.
 */
final class RawResponseTest extends TestCase
{
    #[Test]
    public function fromHeaderLines_parses_status_line_and_headers(): void
    {
        $raw = RawResponse::fromHeaderLines(
            [
                'HTTP/1.1 404 Not Found',
                'Content-Type: text/plain',
                'X-Trace: 1',
            ],
            'missing',
        );

        self::assertSame('1.1', $raw->protocolVersion);
        self::assertSame(404, $raw->statusCode);
        self::assertSame('Not Found', $raw->reasonPhrase);
        self::assertSame(
            ['Content-Type' => ['text/plain'], 'X-Trace' => ['1']],
            $raw->headers,
        );
        self::assertSame('missing', $raw->body);
    }

    #[Test]
    public function fromHeaderLines_keeps_only_the_final_header_block(): void
    {
        $raw = RawResponse::fromHeaderLines(
            [
                'HTTP/1.1 100 Continue',
                'X-Interim: yes',
                'HTTP/1.1 200 OK',
                'X-Final: yes',
            ],
            'done',
        );

        self::assertSame(200, $raw->statusCode);
        self::assertSame(['X-Final' => ['yes']], $raw->headers);
    }

    #[Test]
    public function fromHeaderLines_groups_repeated_headers(): void
    {
        $raw = RawResponse::fromHeaderLines(
            [
                'HTTP/1.1 200 OK',
                'Set-Cookie: a=1',
                'Set-Cookie: b=2',
            ],
            '',
        );

        self::assertSame(['Set-Cookie' => ['a=1', 'b=2']], $raw->headers);
    }

    #[Test]
    public function fromHeaderLines_supports_http2_status_line_without_reason(): void
    {
        $raw = RawResponse::fromHeaderLines(['HTTP/2 204'], '');

        self::assertSame('2', $raw->protocolVersion);
        self::assertSame(204, $raw->statusCode);
        self::assertSame('', $raw->reasonPhrase);
    }

    #[Test]
    public function fromHeaderLines_ignores_lines_without_a_colon(): void
    {
        $raw = RawResponse::fromHeaderLines(
            [
                'HTTP/1.1 200 OK',
                'garbage-line',
                'X-Ok: 1',
            ],
            '',
        );

        self::assertSame(['X-Ok' => ['1']], $raw->headers);
    }

    #[Test]
    public function fromHeaderLines_throws_when_no_status_line_is_present(): void
    {
        $this->expectException(ClientException::class);
        $this->expectExceptionMessage('Malformed HTTP response');

        RawResponse::fromHeaderLines(['Content-Type: text/plain'], '');
    }
}
