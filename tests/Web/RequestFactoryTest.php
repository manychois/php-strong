<?php

declare(strict_types=1);

namespace Manychois\PhpStrongTests\Web;

use Manychois\PhpStrong\Web\InRequest;
use Manychois\PhpStrong\Web\OutRequest;
use Manychois\PhpStrong\Web\RequestFactory;
use Manychois\PhpStrong\Web\Uri;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for {@see RequestFactory}.
 */
final class RequestFactoryTest extends TestCase
{
    #[Test]
    public function createRequest_builds_out_request_with_string_uri(): void
    {
        $factory = new RequestFactory();
        $request = $factory->createRequest('PATCH', 'https://example.test/api');

        self::assertInstanceOf(OutRequest::class, $request);
        self::assertSame('PATCH', $request->getMethod());
        self::assertSame('https://example.test/api', (string) $request->getUri());
    }

    #[Test]
    public function createRequest_accepts_uri_instance(): void
    {
        $factory = new RequestFactory();
        $uri = Uri::fromString('http://localhost/foo');
        $request = $factory->createRequest('GET', $uri);

        self::assertSame($uri, $request->getUri());
    }

    #[Test]
    public function createServerRequest_builds_in_request_with_server_params(): void
    {
        $factory = new RequestFactory();
        $server = ['REQUEST_TIME_FLOAT' => 1.0, 'HTTP_HOST' => 'app.test'];
        $request = $factory->createServerRequest('POST', '/handle', $server);

        self::assertInstanceOf(InRequest::class, $request);
        self::assertSame('POST', $request->getMethod());
        self::assertSame('/handle', (string) $request->getUri());
        self::assertSame($server, $request->getServerParams());
        self::assertSame('1.1', $request->getProtocolVersion());
    }

    #[Test]
    public function createServerRequest_defaults_empty_server_params(): void
    {
        $factory = new RequestFactory();
        $request = $factory->createServerRequest('GET', 'https://example.test/');

        self::assertSame([], $request->getServerParams());
    }

    #[Test]
    public function createServerRequest_accepts_server_params_with_string_keys_only_per_psr17(): void
    {
        $factory = new RequestFactory();
        $server = ['REQUEST_TIME_FLOAT' => 1.0, 99 => 'ignored-non-string-key'];

        $request = $factory->createServerRequest('HEAD', '/', $server);

        self::assertSame($server, $request->getServerParams());
    }
}
