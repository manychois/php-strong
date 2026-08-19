<?php

declare(strict_types=1);

namespace Manychois\PhpStrongTests\Http;

use Manychois\PhpStrong\Http\Request;
use Manychois\PhpStrong\Http\StreamFactory;
use Manychois\PhpStrong\Http\Uri;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for {@see Request}.
 */
final class RequestTest extends TestCase
{
    #[Test]
    public function constructor_defaults_to_get_slash_and_1_1(): void
    {
        $request = new Request();

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

        $request = new Request(
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
        $request = new Request(
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
        $request = new Request(uri: 'https://example.test/path');

        self::assertTrue($request->hasHeader('Host'));
        self::assertSame(['example.test'], $request->getHeader('Host'));
    }

    #[Test]
    public function constructor_adds_host_with_non_default_port(): void
    {
        $request = new Request(uri: 'http://example.test:8080/');

        self::assertSame(['example.test:8080'], $request->getHeader('Host'));
    }

    #[Test]
    public function constructor_does_not_override_existing_host_header(): void
    {
        $request = new Request(
            uri: 'https://example.test/',
            headers: ['Host' => 'upstream.internal'],
        );

        self::assertSame(['upstream.internal'], $request->getHeader('Host'));
    }

    #[Test]
    public function getRequestTarget_returns_explicit_value_when_set(): void
    {
        $request = new Request(requestTarget: '/proxy?x=1');

        self::assertSame('/proxy?x=1', $request->getRequestTarget());
    }

    #[Test]
    public function getRequestTarget_builds_path_and_query_from_uri(): void
    {
        $request = new Request(uri: 'http://h.example/foo?bar=baz', requestTarget: null);

        self::assertSame('/foo?bar=baz', $request->getRequestTarget());
    }

    #[Test]
    public function getRequestTarget_returns_path_only_when_query_empty(): void
    {
        $request = new Request(uri: 'http://h.example/foo');

        self::assertSame('/foo', $request->getRequestTarget());
    }

    #[Test]
    public function getRequestTarget_uses_slash_when_uri_path_empty(): void
    {
        $request = new Request(uri: 'http://h.example');

        self::assertSame('/', $request->getRequestTarget());
    }

    #[Test]
    public function withMethod_returns_clone_with_new_method(): void
    {
        $original = new Request('GET');
        $next = $original->withMethod('DELETE');

        self::assertSame('GET', $original->getMethod());
        self::assertSame('DELETE', $next->getMethod());
    }

    #[Test]
    public function withRequestTarget_returns_clone(): void
    {
        $original = new Request(uri: '/a');
        $next = $original->withRequestTarget('*');

        self::assertSame('/a', $original->getRequestTarget());
        self::assertSame('*', $next->getRequestTarget());
    }

    #[Test]
    public function withUri_updates_host_header_when_not_preserve_host(): void
    {
        $original = new Request(
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
        $original = new Request(
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
        $original = new Request(
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
        $original = new Request(headers: ['Host' => 'app.test']);
        $relative = new Uri(path: '/only-path');
        $next = $original->withUri($relative);

        self::assertSame(['app.test'], $next->getHeader('Host'));
    }
}
