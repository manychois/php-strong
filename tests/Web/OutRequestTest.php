<?php

declare(strict_types=1);

namespace Manychois\PhpStrongTests\Web;

use Manychois\PhpStrong\Web\InRequest;
use Manychois\PhpStrong\Web\OutRequest;
use Manychois\PhpStrong\Web\StreamFactory;
use Manychois\PhpStrong\Web\Uri;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for {@see OutRequest}.
 */
final class OutRequestTest extends TestCase
{
    #[Test]
    public function constructor_defaults_to_get_slash_and_1_1(): void
    {
        $request = new OutRequest();

        self::assertSame('GET', $request->getMethod());
        self::assertSame('/', $request->getUri()->getPath());
        self::assertSame('1.1', $request->getProtocolVersion());
        self::assertSame('', (string) $request->getBody());
    }

    #[Test]
    public function constructor_sets_method_uri_headers_body_and_protocol(): void
    {
        $streams = new StreamFactory();
        $body = $streams->createStream('payload');
        $uri = Uri::fromString('https://api.example/v1?q=1');

        $request = new OutRequest(
            method: 'POST',
            uri: $uri,
            headers: ['X-Test' => 'a'],
            body: $body,
            protocolVersion: '2',
        );

        self::assertSame('POST', $request->getMethod());
        self::assertSame($uri, $request->getUri());
        self::assertSame(['a'], $request->getHeader('X-Test'));
        self::assertSame($body, $request->getBody());
        self::assertSame('2', $request->getProtocolVersion());
    }

    #[Test]
    public function constructor_accepts_string_uri_and_body(): void
    {
        $request = new OutRequest(
            method: 'PUT',
            uri: '/items/3',
            headers: [],
            body: 'raw',
        );

        self::assertSame('/items/3', $request->getUri()->getPath());
        self::assertSame('raw', (string) $request->getBody());
    }

    #[Test]
    public function constructor_adds_host_header_from_uri_when_missing(): void
    {
        $request = new OutRequest(uri: 'https://example.test/path');

        self::assertTrue($request->hasHeader('Host'));
        self::assertSame(['example.test'], $request->getHeader('Host'));
    }

    #[Test]
    public function constructor_adds_host_with_non_default_port(): void
    {
        $request = new OutRequest(uri: 'http://example.test:8080/');

        self::assertSame(['example.test:8080'], $request->getHeader('Host'));
    }

    #[Test]
    public function constructor_does_not_override_existing_host_header(): void
    {
        $request = new OutRequest(
            uri: 'https://example.test/',
            headers: ['Host' => 'upstream.internal'],
        );

        self::assertSame(['upstream.internal'], $request->getHeader('Host'));
    }

    #[Test]
    public function getRequestTarget_returns_explicit_value_when_set(): void
    {
        $request = new OutRequest(requestTarget: '/proxy?x=1');

        self::assertSame('/proxy?x=1', $request->getRequestTarget());
    }

    #[Test]
    public function getRequestTarget_builds_path_and_query_from_uri(): void
    {
        $request = new OutRequest(uri: 'http://h.example/foo?bar=baz', requestTarget: null);

        self::assertSame('/foo?bar=baz', $request->getRequestTarget());
    }

    #[Test]
    public function getRequestTarget_returns_path_only_when_query_empty(): void
    {
        $request = new OutRequest(uri: 'http://h.example/foo');

        self::assertSame('/foo', $request->getRequestTarget());
    }

    #[Test]
    public function getRequestTarget_uses_slash_when_uri_path_empty(): void
    {
        $request = new OutRequest(uri: 'http://h.example');

        self::assertSame('/', $request->getRequestTarget());
    }

    #[Test]
    public function withMethod_returns_clone_with_new_method(): void
    {
        $original = new OutRequest('GET');
        $next = $original->withMethod('DELETE');

        self::assertSame('GET', $original->getMethod());
        self::assertSame('DELETE', $next->getMethod());
    }

    #[Test]
    public function withRequestTarget_returns_clone(): void
    {
        $original = new OutRequest(uri: '/a');
        $next = $original->withRequestTarget('*');

        self::assertSame('/a', $original->getRequestTarget());
        self::assertSame('*', $next->getRequestTarget());
    }

    #[Test]
    public function withUri_updates_host_header_when_not_preserve_host(): void
    {
        $original = new OutRequest(
            uri: 'https://old.example/',
            headers: ['Host' => 'old.example'],
        );
        $newUri = Uri::fromString('https://new.example/y');
        $next = $original->withUri($newUri);

        self::assertSame(['old.example'], $original->getHeader('Host'));
        self::assertSame(['new.example'], $next->getHeader('Host'));
        self::assertSame($newUri, $next->getUri());
    }

    #[Test]
    public function withUri_preserve_host_keeps_existing_host_when_present(): void
    {
        $original = new OutRequest(
            uri: 'https://old.example/',
            headers: ['Host' => 'preserve.me'],
        );
        $newUri = Uri::fromString('https://new.example/y');
        $next = $original->withUri($newUri, preserveHost: true);

        self::assertSame(['preserve.me'], $next->getHeader('Host'));
        self::assertSame('https://new.example/y', (string) $next->getUri());
    }

    #[Test]
    public function withUri_preserve_host_replaces_host_when_header_line_empty(): void
    {
        $original = new OutRequest(
            uri: 'https://old.example/',
            headers: ['Host' => ''],
        );
        $newUri = Uri::fromString('https://new.example/y');
        $next = $original->withUri($newUri, preserveHost: true);

        self::assertSame(['new.example'], $next->getHeader('Host'));
    }

    #[Test]
    public function withUri_does_not_set_host_when_uri_has_no_host(): void
    {
        $original = new OutRequest(headers: ['Host' => 'app.test']);
        $relative = new Uri(path: '/only-path');
        $next = $original->withUri($relative);

        self::assertSame(['app.test'], $next->getHeader('Host'));
    }

    #[Test]
    public function fromGlobals_builds_in_request_from_superglobals(): void
    {
        $serverBackup = $_SERVER;
        $getBackup = $_GET;
        $postBackup = $_POST;
        $cookieBackup = $_COOKIE;

        try {
            $_SERVER = [
                'REQUEST_METHOD' => 'POST',
                'REQUEST_URI' => '/api/hook?sig=1',
                'HTTP_HOST' => 'svc.local',
                'HTTP_X_API_KEY' => 'k',
                'CONTENT_TYPE' => 'application/json',
                'SERVER_PROTOCOL' => 'HTTP/1.0',
                'HTTPS' => 'on',
            ];
            $_GET = ['sig' => '1'];
            $_POST = [];
            $_COOKIE = ['session' => 'abc'];

            $request = OutRequest::fromGlobals();

            self::assertInstanceOf(InRequest::class, $request);
            self::assertSame('POST', $request->getMethod());
            self::assertSame('/api/hook?sig=1', $request->getRequestTarget());
            self::assertSame('1.0', $request->getProtocolVersion());
            self::assertSame(['k'], $request->getHeader('X-Api-Key'));
            self::assertSame(['application/json'], $request->getHeader('Content-Type'));
            self::assertSame('https://svc.local/api/hook?sig=1', (string) $request->getUri());
            self::assertSame(['session' => 'abc'], $request->getCookieParams());
            self::assertSame(['sig' => '1'], $request->getQueryParams());
            self::assertNull($request->getParsedBody());
        } finally {
            $_SERVER = $serverBackup;
            $_GET = $getBackup;
            $_POST = $postBackup;
            $_COOKIE = $cookieBackup;
        }
    }

    #[Test]
    public function fromGlobals_maps_redirect_authorization_header(): void
    {
        $serverBackup = $_SERVER;
        $getBackup = $_GET;
        $postBackup = $_POST;
        $cookieBackup = $_COOKIE;

        try {
            $_SERVER = [
                'REQUEST_METHOD' => 'GET',
                'REQUEST_URI' => '/',
                'HTTP_HOST' => 'h.test',
                'REDIRECT_HTTP_AUTHORIZATION' => 'Bearer t',
            ];
            $_GET = [];
            $_POST = [];
            $_COOKIE = [];

            $request = OutRequest::fromGlobals();

            self::assertSame(['Bearer t'], $request->getHeader('Authorization'));
        } finally {
            $_SERVER = $serverBackup;
            $_GET = $getBackup;
            $_POST = $postBackup;
            $_COOKIE = $cookieBackup;
        }
    }

    #[Test]
    public function fromGlobals_uses_post_as_parsed_body_when_present(): void
    {
        $serverBackup = $_SERVER;
        $getBackup = $_GET;
        $postBackup = $_POST;
        $cookieBackup = $_COOKIE;

        try {
            $_SERVER = [
                'REQUEST_METHOD' => 'POST',
                'REQUEST_URI' => '/submit',
                'HTTP_HOST' => 'h.test',
            ];
            $_GET = [];
            $_POST = ['field' => 'value'];
            $_COOKIE = [];

            $request = OutRequest::fromGlobals();

            self::assertSame(['field' => 'value'], $request->getParsedBody());
        } finally {
            $_SERVER = $serverBackup;
            $_GET = $getBackup;
            $_POST = $postBackup;
            $_COOKIE = $cookieBackup;
        }
    }

    #[Test]
    public function fromGlobals_normalizes_empty_or_star_request_uri_to_slash(): void
    {
        $serverBackup = $_SERVER;
        $getBackup = $_GET;
        $postBackup = $_POST;
        $cookieBackup = $_COOKIE;

        try {
            foreach (['', '*'] as $requestUri) {
                $_SERVER = [
                    'REQUEST_METHOD' => 'GET',
                    'REQUEST_URI' => $requestUri,
                    'HTTP_HOST' => 'empty-star.test',
                ];
                $_GET = [];
                $_POST = [];
                $_COOKIE = [];

                $request = OutRequest::fromGlobals();

                self::assertSame('http://empty-star.test/', (string) $request->getUri());
            }
        } finally {
            $_SERVER = $serverBackup;
            $_GET = $getBackup;
            $_POST = $postBackup;
            $_COOKIE = $cookieBackup;
        }
    }

    #[Test]
    public function fromGlobals_accepts_absolute_request_uri(): void
    {
        $serverBackup = $_SERVER;
        $getBackup = $_GET;
        $postBackup = $_POST;
        $cookieBackup = $_COOKIE;

        try {
            $_SERVER = [
                'REQUEST_METHOD' => 'GET',
                'REQUEST_URI' => 'http://absolute.other/full?q=1',
                'HTTP_HOST' => 'proxy.test',
            ];
            $_GET = [];
            $_POST = [];
            $_COOKIE = [];

            $request = OutRequest::fromGlobals();

            self::assertSame('http://absolute.other/full?q=1', (string) $request->getUri());
        } finally {
            $_SERVER = $serverBackup;
            $_GET = $getBackup;
            $_POST = $postBackup;
            $_COOKIE = $cookieBackup;
        }
    }

    #[Test]
    public function fromGlobals_prefixes_slash_when_request_path_has_none(): void
    {
        $serverBackup = $_SERVER;
        $getBackup = $_GET;
        $postBackup = $_POST;
        $cookieBackup = $_COOKIE;

        try {
            $_SERVER = [
                'REQUEST_METHOD' => 'GET',
                'REQUEST_URI' => 'relative/bit',
                'HTTP_HOST' => 'rel.test',
            ];
            $_GET = [];
            $_POST = [];
            $_COOKIE = [];

            $request = OutRequest::fromGlobals();

            self::assertSame('http://rel.test/relative/bit', (string) $request->getUri());
        } finally {
            $_SERVER = $serverBackup;
            $_GET = $getBackup;
            $_POST = $postBackup;
            $_COOKIE = $cookieBackup;
        }
    }

    #[Test]
    public function fromGlobals_treats_non_string_request_uri_like_missing(): void
    {
        $serverBackup = $_SERVER;
        $getBackup = $_GET;
        $postBackup = $_POST;
        $cookieBackup = $_COOKIE;

        try {
            $_SERVER = [
                'REQUEST_METHOD' => 'GET',
                'REQUEST_URI' => ['not-a-string'],
                'HTTP_HOST' => 't.test',
            ];
            $_GET = [];
            $_POST = [];
            $_COOKIE = [];

            $request = OutRequest::fromGlobals();

            self::assertSame('http://t.test/', (string) $request->getUri());
        } finally {
            $_SERVER = $serverBackup;
            $_GET = $getBackup;
            $_POST = $postBackup;
            $_COOKIE = $cookieBackup;
        }
    }

    #[Test]
    public function fromGlobals_https_via_numeric_one_and_request_scheme(): void
    {
        $serverBackup = $_SERVER;
        $getBackup = $_GET;
        $postBackup = $_POST;
        $cookieBackup = $_COOKIE;

        try {
            $_SERVER = [
                'REQUEST_METHOD' => 'GET',
                'REQUEST_URI' => '/',
                'HTTP_HOST' => 'https-one.test',
                'HTTPS' => '1',
            ];
            $_GET = [];
            $_POST = [];
            $_COOKIE = [];

            self::assertStringStartsWith('https://', (string) OutRequest::fromGlobals()->getUri());

            $_SERVER = [
                'REQUEST_METHOD' => 'GET',
                'REQUEST_URI' => '/z',
                'HTTP_HOST' => 'scheme.test',
                'REQUEST_SCHEME' => 'HTTP',
            ];

            self::assertSame('http://scheme.test/z', (string) OutRequest::fromGlobals()->getUri());
        } finally {
            $_SERVER = $serverBackup;
            $_GET = $getBackup;
            $_POST = $postBackup;
            $_COOKIE = $cookieBackup;
        }
    }

    #[Test]
    public function fromGlobals_host_from_server_name_with_non_default_port(): void
    {
        $serverBackup = $_SERVER;
        $getBackup = $_GET;
        $postBackup = $_POST;
        $cookieBackup = $_COOKIE;

        try {
            $_SERVER = [
                'REQUEST_METHOD' => 'GET',
                'REQUEST_URI' => '/app',
                'SERVER_NAME' => 'sn.example',
                'SERVER_PORT' => 8080,
            ];
            $_GET = [];
            $_POST = [];
            $_COOKIE = [];

            $request = OutRequest::fromGlobals();

            self::assertSame('http://sn.example:8080/app', (string) $request->getUri());
        } finally {
            $_SERVER = $serverBackup;
            $_GET = $getBackup;
            $_POST = $postBackup;
            $_COOKIE = $cookieBackup;
        }
    }

    #[Test]
    public function fromGlobals_host_from_server_name_omits_default_http_port(): void
    {
        $serverBackup = $_SERVER;
        $getBackup = $_GET;
        $postBackup = $_POST;
        $cookieBackup = $_COOKIE;

        try {
            $_SERVER = [
                'REQUEST_METHOD' => 'GET',
                'REQUEST_URI' => '/',
                'SERVER_NAME' => 'plain.example',
                'SERVER_PORT' => 80,
            ];
            $_GET = [];
            $_POST = [];
            $_COOKIE = [];

            self::assertSame('http://plain.example/', (string) OutRequest::fromGlobals()->getUri());
        } finally {
            $_SERVER = $serverBackup;
            $_GET = $getBackup;
            $_POST = $postBackup;
            $_COOKIE = $cookieBackup;
        }
    }

    #[Test]
    public function fromGlobals_host_from_server_name_omits_default_https_port(): void
    {
        $serverBackup = $_SERVER;
        $getBackup = $_GET;
        $postBackup = $_POST;
        $cookieBackup = $_COOKIE;

        try {
            $_SERVER = [
                'REQUEST_METHOD' => 'GET',
                'REQUEST_URI' => '/',
                'SERVER_NAME' => 'tls.example',
                'SERVER_PORT' => 443,
                'HTTPS' => 'on',
            ];
            $_GET = [];
            $_POST = [];
            $_COOKIE = [];

            self::assertSame('https://tls.example/', (string) OutRequest::fromGlobals()->getUri());
        } finally {
            $_SERVER = $serverBackup;
            $_GET = $getBackup;
            $_POST = $postBackup;
            $_COOKIE = $cookieBackup;
        }
    }

    #[Test]
    public function fromGlobals_host_from_server_addr_when_name_missing(): void
    {
        $serverBackup = $_SERVER;
        $getBackup = $_GET;
        $postBackup = $_POST;
        $cookieBackup = $_COOKIE;

        try {
            $_SERVER = [
                'REQUEST_METHOD' => 'GET',
                'REQUEST_URI' => '/x',
                'SERVER_ADDR' => '10.0.0.5',
            ];
            $_GET = [];
            $_POST = [];
            $_COOKIE = [];

            self::assertSame('http://10.0.0.5/x', (string) OutRequest::fromGlobals()->getUri());
        } finally {
            $_SERVER = $serverBackup;
            $_GET = $getBackup;
            $_POST = $postBackup;
            $_COOKIE = $cookieBackup;
        }
    }

    #[Test]
    public function fromGlobals_host_defaults_to_localhost(): void
    {
        $serverBackup = $_SERVER;
        $getBackup = $_GET;
        $postBackup = $_POST;
        $cookieBackup = $_COOKIE;

        try {
            $_SERVER = [
                'REQUEST_METHOD' => 'GET',
                'REQUEST_URI' => '/minimal',
            ];
            $_GET = [];
            $_POST = [];
            $_COOKIE = [];

            self::assertSame('http://localhost/minimal', (string) OutRequest::fromGlobals()->getUri());
        } finally {
            $_SERVER = $serverBackup;
            $_GET = $getBackup;
            $_POST = $postBackup;
            $_COOKIE = $cookieBackup;
        }
    }

    #[Test]
    public function fromGlobals_protocol_version_defaults_when_server_protocol_unrecognized(): void
    {
        $serverBackup = $_SERVER;
        $getBackup = $_GET;
        $postBackup = $_POST;
        $cookieBackup = $_COOKIE;

        try {
            $_SERVER = [
                'REQUEST_METHOD' => 'GET',
                'REQUEST_URI' => '/',
                'HTTP_HOST' => 'pv.test',
                'SERVER_PROTOCOL' => 'nonsense/2',
            ];
            $_GET = [];
            $_POST = [];
            $_COOKIE = [];

            self::assertSame('1.1', OutRequest::fromGlobals()->getProtocolVersion());
        } finally {
            $_SERVER = $serverBackup;
            $_GET = $getBackup;
            $_POST = $postBackup;
            $_COOKIE = $cookieBackup;
        }
    }

    #[Test]
    public function fromGlobals_maps_content_length_and_skips_bad_server_entries(): void
    {
        $serverBackup = $_SERVER;
        $getBackup = $_GET;
        $postBackup = $_POST;
        $cookieBackup = $_COOKIE;

        try {
            $_SERVER = [
                'REQUEST_METHOD' => 'GET',
                'REQUEST_URI' => '/',
                'HTTP_HOST' => 'hdr.test',
                'CONTENT_LENGTH' => '99',
                0 => 'ignored-int-key',
                'SKIP_ME' => ['nested' => true],
            ];
            $_GET = [];
            $_POST = [];
            $_COOKIE = [];

            $request = OutRequest::fromGlobals();

            self::assertSame(['99'], $request->getHeader('Content-Length'));
        } finally {
            $_SERVER = $serverBackup;
            $_GET = $getBackup;
            $_POST = $postBackup;
            $_COOKIE = $cookieBackup;
        }
    }

    #[Test]
    public function fromGlobals_maps_content_md5_header(): void
    {
        $serverBackup = $_SERVER;
        $getBackup = $_GET;
        $postBackup = $_POST;
        $cookieBackup = $_COOKIE;

        try {
            $_SERVER = [
                'REQUEST_METHOD' => 'GET',
                'REQUEST_URI' => '/',
                'HTTP_HOST' => 'md5.test',
                'CONTENT_MD5' => 'Q2hlY2sgSW50ZWdyaXR5IQ==',
            ];
            $_GET = [];
            $_POST = [];
            $_COOKIE = [];

            self::assertSame(
                ['Q2hlY2sgSW50ZWdyaXR5IQ=='],
                OutRequest::fromGlobals()->getHeader('Content-Md5'),
            );
        } finally {
            $_SERVER = $serverBackup;
            $_GET = $getBackup;
            $_POST = $postBackup;
            $_COOKIE = $cookieBackup;
        }
    }

    #[Test]
    public function fromGlobals_skips_http_entries_with_non_scalar_values(): void
    {
        $serverBackup = $_SERVER;
        $getBackup = $_GET;
        $postBackup = $_POST;
        $cookieBackup = $_COOKIE;

        try {
            $_SERVER = [
                'REQUEST_METHOD' => 'GET',
                'REQUEST_URI' => '/',
                'HTTP_HOST' => 'scalar.test',
                'HTTP_X_SKIP' => ['not' => 'scalar'],
            ];
            $_GET = [];
            $_POST = [];
            $_COOKIE = [];

            self::assertFalse(OutRequest::fromGlobals()->hasHeader('X-Skip'));
        } finally {
            $_SERVER = $serverBackup;
            $_GET = $getBackup;
            $_POST = $postBackup;
            $_COOKIE = $cookieBackup;
        }
    }

    #[Test]
    public function fromGlobals_drops_server_entries_with_non_string_names(): void
    {
        $serverBackup = $_SERVER;
        $getBackup = $_GET;
        $postBackup = $_POST;
        $cookieBackup = $_COOKIE;

        try {
            $_SERVER = [
                'REQUEST_METHOD' => 'GET',
                'REQUEST_URI' => '/',
                'HTTP_HOST' => 'keys.test',
                404 => 'ignored-non-string-key',
            ];
            $_GET = [];
            $_POST = [];
            $_COOKIE = [];

            $request = OutRequest::fromGlobals();

            self::assertSame('http://keys.test/', (string) $request->getUri());
            self::assertArrayNotHasKey('404', $request->getServerParams());
            self::assertArrayNotHasKey(404, $request->getServerParams());
        } finally {
            $_SERVER = $serverBackup;
            $_GET = $getBackup;
            $_POST = $postBackup;
            $_COOKIE = $cookieBackup;
        }
    }
}
