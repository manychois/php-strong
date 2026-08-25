<?php

declare(strict_types=1);

namespace Manychois\PhpStrongTests\Http;

use InvalidArgumentException;
use Manychois\PhpStrong\Http\ServerRequest;
use Manychois\PhpStrong\Http\StreamFactory;
use Manychois\PhpStrong\Http\UploadedFile;
use Manychois\PhpStrong\Http\Uri;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for {@see ServerRequest}.
 */
final class ServerRequestTest extends TestCase
{
    #[Test]
    public function constructor_exposes_server_cookie_query_uploaded_and_attributes(): void
    {
        $streams = new StreamFactory();
        $file = new UploadedFile(
            $streams->createStream('x'),
            size: 1,
            error: \UPLOAD_ERR_OK,
            clientFilename: 'f.txt',
            clientMediaType: 'text/plain',
        );

        $request = new ServerRequest(
            method: 'PATCH',
            uri: 'https://app.test/update',
            headers: ['X-Trace' => '1'],
            body: '',
            protocolVersion: '1.1',
            requestTarget: null,
            serverParams: ['REMOTE_ADDR' => '127.0.0.1'],
            cookieParams: ['c' => 'v'],
            queryParams: ['q' => 'search'],
            uploadedFiles: ['doc' => $file],
            parsedBody: ['json' => true],
            attributes: ['route' => 'api.update'],
        );

        self::assertSame(['REMOTE_ADDR' => '127.0.0.1'], $request->getServerParams());
        self::assertSame(['c' => 'v'], $request->getCookieParams());
        self::assertSame(['q' => 'search'], $request->getQueryParams());
        self::assertSame(['json' => true], $request->getParsedBody());
        self::assertSame(['route' => 'api.update'], $request->getAttributes());
        $uploaded = $request->getUploadedFiles();
        self::assertArrayHasKey('doc', $uploaded);
        self::assertSame($file, $uploaded['doc']);
    }

    #[Test]
    public function constructor_normalizes_nested_uploaded_files(): void
    {
        $streams = new StreamFactory();
        $inner = new UploadedFile(
            $streams->createStream(''),
            size: 0,
            error: \UPLOAD_ERR_OK,
            clientFilename: null,
            clientMediaType: null,
        );

        $request = new ServerRequest(uploadedFiles: ['outer' => ['inner' => $inner]]);
        $files = $request->getUploadedFiles();

        self::assertSame($inner, $files['outer']['inner']);
    }

    #[Test]
    public function constructor_rejects_invalid_uploaded_file_entry(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Uploaded files must contain');

        new ServerRequest(uploadedFiles: ['bad' => 'not-a-file']);
    }

    #[Test]
    public function getAttribute_returns_default_when_missing(): void
    {
        $request = new ServerRequest();

        self::assertNull($request->getAttribute('missing'));
        self::assertSame('fallback', $request->getAttribute('missing', 'fallback'));
    }

    #[Test]
    public function withAttribute_is_immutable(): void
    {
        $original = new ServerRequest(attributes: ['a' => 1]);
        $next = $original->withAttribute('b', 2);

        self::assertSame(['a' => 1], $original->getAttributes());
        self::assertSame(1, $original->getAttribute('a'));
        self::assertNull($original->getAttribute('b'));

        self::assertSame(['a' => 1, 'b' => 2], $next->getAttributes());
        self::assertSame(2, $next->getAttribute('b'));
    }

    #[Test]
    public function withoutAttribute_returns_same_instance_when_missing(): void
    {
        $request = new ServerRequest();
        $same = $request->withoutAttribute('nope');

        self::assertSame($request, $same);
    }

    #[Test]
    public function withoutAttribute_removes_attribute_on_clone(): void
    {
        $original = new ServerRequest(attributes: ['x' => 'y']);
        $next = $original->withoutAttribute('x');

        self::assertArrayHasKey('x', $original->getAttributes());
        self::assertArrayNotHasKey('x', $next->getAttributes());
    }

    #[Test]
    public function withCookieParams_replaces_cookies(): void
    {
        $original = new ServerRequest(cookieParams: ['a' => '1']);
        $next = $original->withCookieParams(['b' => '2']);

        self::assertSame(['a' => '1'], $original->getCookieParams());
        self::assertSame(['b' => '2'], $next->getCookieParams());
    }

    #[Test]
    public function withQueryParams_replaces_query(): void
    {
        $original = new ServerRequest(queryParams: ['old' => '']);
        $next = $original->withQueryParams(['new' => 'x']);

        self::assertSame(['old' => ''], $original->getQueryParams());
        self::assertSame(['new' => 'x'], $next->getQueryParams());
    }

    #[Test]
    public function withParsedBody_accepts_null_array_and_object(): void
    {
        $base = new ServerRequest(parsedBody: ['a' => 1]);
        $asNull = $base->withParsedBody(null);
        $asArray = $base->withParsedBody(['z' => 9]);
        $obj = new \stdClass();
        $obj->k = 'v';
        $asObject = $base->withParsedBody($obj);

        self::assertNull($asNull->getParsedBody());
        self::assertSame(['z' => 9], $asArray->getParsedBody());
        self::assertSame($obj, $asObject->getParsedBody());
    }

    #[Test]
    public function withParsedBody_rejects_scalar(): void
    {
        $request = new ServerRequest();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Parsed body must be null, array, or object');

        $request->withParsedBody(3);
    }

    #[Test]
    public function withUploadedFiles_normalizes_tree(): void
    {
        $streams = new StreamFactory();
        $file = new UploadedFile(
            $streams->createStream(''),
            size: 0,
            error: \UPLOAD_ERR_OK,
            clientFilename: null,
            clientMediaType: null,
        );
        $original = new ServerRequest();
        $next = $original->withUploadedFiles(['k' => ['nested' => $file]]);

        self::assertSame([], $original->getUploadedFiles());
        self::assertSame($file, $next->getUploadedFiles()['k']['nested']);
    }

    #[Test]
    public function inherits_out_request_with_uri_behavior(): void
    {
        $original = new ServerRequest(
            uri: 'https://one.example/',
            headers: ['Host' => 'one.example'],
        );
        $two = Uri::fromString('https://two.example/path');
        $next = $original->withUri($two);

        self::assertSame('https://two.example/path', (string) $next->getUri());
        self::assertSame(['two.example'], $next->getHeader('Host'));
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

            $request = ServerRequest::fromGlobals();

            self::assertInstanceOf(ServerRequest::class, $request);
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

            $request = ServerRequest::fromGlobals();

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

            $request = ServerRequest::fromGlobals();

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

                $request = ServerRequest::fromGlobals();

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

            $request = ServerRequest::fromGlobals();

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

            $request = ServerRequest::fromGlobals();

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

            $request = ServerRequest::fromGlobals();

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

            self::assertStringStartsWith('https://', (string) ServerRequest::fromGlobals()->getUri());

            $_SERVER = [
                'REQUEST_METHOD' => 'GET',
                'REQUEST_URI' => '/z',
                'HTTP_HOST' => 'scheme.test',
                'REQUEST_SCHEME' => 'HTTP',
            ];

            self::assertSame('http://scheme.test/z', (string) ServerRequest::fromGlobals()->getUri());
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

            $request = ServerRequest::fromGlobals();

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

            self::assertSame('http://plain.example/', (string) ServerRequest::fromGlobals()->getUri());
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

            self::assertSame('https://tls.example/', (string) ServerRequest::fromGlobals()->getUri());
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

            self::assertSame('http://10.0.0.5/x', (string) ServerRequest::fromGlobals()->getUri());
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

            self::assertSame('http://localhost/minimal', (string) ServerRequest::fromGlobals()->getUri());
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

            self::assertSame('1.1', ServerRequest::fromGlobals()->getProtocolVersion());
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

            $request = ServerRequest::fromGlobals();

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
                ServerRequest::fromGlobals()->getHeader('Content-Md5'),
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

            self::assertFalse(ServerRequest::fromGlobals()->hasHeader('X-Skip'));
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

            $request = ServerRequest::fromGlobals();

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
