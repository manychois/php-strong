<?php

declare(strict_types=1);

namespace Manychois\PhpStrongTests\Http\Internal;

use Manychois\PhpStrong\Http\Internal\CurlTransport;
use Manychois\PhpStrong\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for {@see CurlTransport}.
 */
final class CurlTransportTest extends TestCase
{
    #[Test]
    public function buildOptions_maps_url_method_timeout_and_headers(): void
    {
        $request = new Request(
            'POST',
            'http://example.com/submit?x=1',
            ['X-Custom' => ['a', 'b'], 'X-Empty' => ''],
            'payload',
        );

        $options = CurlTransport::buildOptions($request, 2.5);

        self::assertSame('http://example.com/submit?x=1', $options[\CURLOPT_URL]);
        self::assertSame('POST', $options[\CURLOPT_CUSTOMREQUEST]);
        self::assertTrue($options[\CURLOPT_RETURNTRANSFER]);
        self::assertFalse($options[\CURLOPT_FOLLOWLOCATION]);
        self::assertSame(2500, $options[\CURLOPT_CONNECTTIMEOUT_MS]);
        self::assertSame(2500, $options[\CURLOPT_TIMEOUT_MS]);
        self::assertSame(\CURL_HTTP_VERSION_1_1, $options[\CURLOPT_HTTP_VERSION]);
        self::assertSame('payload', $options[\CURLOPT_POSTFIELDS]);
        self::assertSame(
            ['X-Custom: a', 'X-Custom: b', 'X-Empty;', 'Host: example.com'],
            $options[\CURLOPT_HTTPHEADER],
        );
        self::assertArrayNotHasKey(\CURLOPT_NOBODY, $options);
    }

    #[Test]
    public function buildOptions_omits_body_and_header_options_when_absent(): void
    {
        $request = new Request('GET', 'http://a.example/')->withoutHeader('Host');

        $options = CurlTransport::buildOptions($request, 1.0);

        self::assertArrayNotHasKey(\CURLOPT_POSTFIELDS, $options);
        self::assertArrayNotHasKey(\CURLOPT_HTTPHEADER, $options);
    }

    #[Test]
    public function buildOptions_sets_nobody_for_head_requests(): void
    {
        $request = new Request('HEAD', 'http://example.com/');

        $options = CurlTransport::buildOptions($request, 1.0);

        self::assertTrue($options[\CURLOPT_NOBODY]);
    }

    #[Test]
    public function buildOptions_maps_protocol_versions(): void
    {
        $request = new Request('GET', 'http://example.com/', protocolVersion: '1.0');
        $options = CurlTransport::buildOptions($request, 1.0);
        self::assertSame(\CURL_HTTP_VERSION_1_0, $options[\CURLOPT_HTTP_VERSION]);

        $request = new Request('GET', 'http://example.com/', protocolVersion: '2');
        $options = CurlTransport::buildOptions($request, 1.0);
        self::assertSame(\CURL_HTTP_VERSION_2_0, $options[\CURLOPT_HTTP_VERSION]);

        $request = new Request('GET', 'http://example.com/', protocolVersion: '2.0');
        $options = CurlTransport::buildOptions($request, 1.0);
        self::assertSame(\CURL_HTTP_VERSION_2_0, $options[\CURLOPT_HTTP_VERSION]);
    }
}
