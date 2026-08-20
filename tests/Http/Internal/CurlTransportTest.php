<?php

declare(strict_types=1);

namespace Manychois\PhpStrongTests\Http\Internal;

use Manychois\PhpStrong\Http\Internal\CurlTransport;
use Manychois\PhpStrong\Http\Request;
use Manychois\PhpStrong\Http\RequestOptions;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for {@see CurlTransport}.
 */
final class CurlTransportTest extends TestCase
{
    #[Test]
    public function buildOptions_maps_url_method_timeouts_and_headers(): void
    {
        $request = new Request(
            'POST',
            'http://example.com/submit?x=1',
            ['X-Custom' => ['a', 'b'], 'X-Empty' => ''],
            'payload',
        );

        $options = CurlTransport::buildOptions($request, new RequestOptions(timeout: 2.5, connectTimeout: 1.25));

        self::assertSame('http://example.com/submit?x=1', $options[\CURLOPT_URL]);
        self::assertSame('POST', $options[\CURLOPT_CUSTOMREQUEST]);
        self::assertTrue($options[\CURLOPT_RETURNTRANSFER]);
        self::assertFalse($options[\CURLOPT_FOLLOWLOCATION]);
        self::assertSame(1250, $options[\CURLOPT_CONNECTTIMEOUT_MS]);
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
    public function buildOptions_uses_default_options_mappings(): void
    {
        $request = new Request('GET', 'http://a.example/')->withoutHeader('Host');

        $options = CurlTransport::buildOptions($request, new RequestOptions());

        self::assertArrayNotHasKey(\CURLOPT_POSTFIELDS, $options);
        self::assertArrayNotHasKey(\CURLOPT_HTTPHEADER, $options);
        self::assertArrayNotHasKey(\CURLOPT_MAXREDIRS, $options);
        self::assertArrayNotHasKey(\CURLOPT_PROXY, $options);
        self::assertArrayNotHasKey(\CURLOPT_USERAGENT, $options);
        self::assertArrayNotHasKey(\CURLOPT_CAINFO, $options);
        self::assertArrayNotHasKey(\CURLOPT_CAPATH, $options);
        self::assertTrue($options[\CURLOPT_SSL_VERIFYPEER]);
        self::assertSame(2, $options[\CURLOPT_SSL_VERIFYHOST]);
    }

    #[Test]
    public function buildOptions_maps_redirect_options(): void
    {
        $request = new Request('GET', 'http://example.com/');

        $options = CurlTransport::buildOptions(
            $request,
            new RequestOptions(followRedirects: true, maxRedirects: 4),
        );

        self::assertTrue($options[\CURLOPT_FOLLOWLOCATION]);
        self::assertSame(4, $options[\CURLOPT_MAXREDIRS]);
    }

    #[Test]
    public function buildOptions_maps_tls_proxy_and_ca_options(): void
    {
        $request = new Request('GET', 'https://example.com/');

        $options = CurlTransport::buildOptions(
            $request,
            new RequestOptions(
                verifyTls: false,
                proxy: 'http://proxy.local:8080',
                caFile: '/etc/ssl/ca.pem',
                caPath: '/etc/ssl/certs',
            ),
        );

        self::assertFalse($options[\CURLOPT_SSL_VERIFYPEER]);
        self::assertSame(0, $options[\CURLOPT_SSL_VERIFYHOST]);
        self::assertSame('http://proxy.local:8080', $options[\CURLOPT_PROXY]);
        self::assertSame('/etc/ssl/ca.pem', $options[\CURLOPT_CAINFO]);
        self::assertSame('/etc/ssl/certs', $options[\CURLOPT_CAPATH]);
    }

    #[Test]
    public function buildOptions_applies_user_agent_only_when_request_has_none(): void
    {
        $requestOptions = new RequestOptions(userAgent: 'php-strong/1.0');

        $bare = new Request('GET', 'http://example.com/');
        $options = CurlTransport::buildOptions($bare, $requestOptions);
        self::assertSame('php-strong/1.0', $options[\CURLOPT_USERAGENT]);

        $withHeader = $bare->withHeader('User-Agent', 'custom/2.0');
        $options = CurlTransport::buildOptions($withHeader, $requestOptions);
        self::assertArrayNotHasKey(\CURLOPT_USERAGENT, $options);
    }

    #[Test]
    public function buildOptions_sets_nobody_for_head_requests(): void
    {
        $request = new Request('HEAD', 'http://example.com/');

        $options = CurlTransport::buildOptions($request, new RequestOptions());

        self::assertTrue($options[\CURLOPT_NOBODY]);
    }

    #[Test]
    public function buildOptions_maps_protocol_versions(): void
    {
        $requestOptions = new RequestOptions();

        $request = new Request('GET', 'http://example.com/', protocolVersion: '1.0');
        $options = CurlTransport::buildOptions($request, $requestOptions);
        self::assertSame(\CURL_HTTP_VERSION_1_0, $options[\CURLOPT_HTTP_VERSION]);

        $request = new Request('GET', 'http://example.com/', protocolVersion: '2');
        $options = CurlTransport::buildOptions($request, $requestOptions);
        self::assertSame(\CURL_HTTP_VERSION_2_0, $options[\CURLOPT_HTTP_VERSION]);

        $request = new Request('GET', 'http://example.com/', protocolVersion: '2.0');
        $options = CurlTransport::buildOptions($request, $requestOptions);
        self::assertSame(\CURL_HTTP_VERSION_2_0, $options[\CURLOPT_HTTP_VERSION]);
    }
}
