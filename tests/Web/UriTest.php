<?php

declare(strict_types=1);

namespace Manychois\PhpStrongTests\Web;

use InvalidArgumentException;
use Manychois\PhpStrong\Web\Uri;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Unit tests for {@see Uri}.
 */
final class UriTest extends TestCase
{
    #[Test]
    public function constructor_defaults_to_empty_components(): void
    {
        $uri = new Uri();

        self::assertSame('', (string) $uri);
        self::assertSame('', $uri->getScheme());
        self::assertSame('', $uri->getAuthority());
        self::assertSame('', $uri->getUserInfo());
        self::assertSame('', $uri->getHost());
        self::assertNull($uri->getPort());
        self::assertSame('', $uri->getPath());
        self::assertSame('', $uri->getQuery());
        self::assertSame('', $uri->getFragment());
    }

    #[Test]
    public function fromString_parses_full_http_uri(): void
    {
        $uri = Uri::fromString('http://user:secret@Example.COM:8080/p/q?x=1&y=2#here');

        self::assertSame('http', $uri->getScheme());
        self::assertSame('user:secret', $uri->getUserInfo());
        self::assertSame('Example.COM', $uri->getHost());
        self::assertSame(8080, $uri->getPort());
        self::assertSame('/p/q', $uri->getPath());
        self::assertSame('x=1&y=2', $uri->getQuery());
        self::assertSame('here', $uri->getFragment());
        self::assertSame('user:secret@Example.COM:8080', $uri->getAuthority());
    }

    #[Test]
    public function fromString_empty_string_yields_empty_uri(): void
    {
        $uri = Uri::fromString('');

        self::assertSame('', (string) $uri);
    }

    #[Test]
    public function fromString_user_without_password_omits_colon(): void
    {
        $uri = Uri::fromString('http://guest@host/');

        self::assertSame('guest', $uri->getUserInfo());
    }

    #[Test]
    public function fromString_throws_when_malformed(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Malformed URI:');
        Uri::fromString(':');
    }

    #[Test]
    public function toString_round_trips_typical_https_uri(): void
    {
        $original = 'https://api.example.org/v1/items?sort=desc#top';
        $uri = Uri::fromString($original);

        self::assertSame($original, (string) $uri);
    }

    #[Test]
    public function getPort_and_authority_omit_default_http_port(): void
    {
        $uri = Uri::fromString('http://example.com:80/path');

        self::assertNull($uri->getPort());
        self::assertSame('example.com', $uri->getAuthority());
        self::assertSame('http://example.com/path', (string) $uri);
    }

    #[Test]
    public function getPort_and_authority_omit_default_https_port(): void
    {
        $uri = Uri::fromString('https://example.com:443/secure');

        self::assertNull($uri->getPort());
        self::assertSame('example.com', $uri->getAuthority());
        self::assertSame('https://example.com/secure', (string) $uri);
    }

    #[Test]
    public function withScheme_normalizes_case_and_leaves_other_parts_unchanged(): void
    {
        $base = Uri::fromString('http://a/b');
        $next = $base->withScheme('HTTPS');

        self::assertSame('http', $base->getScheme());
        self::assertSame('https', $next->getScheme());
        self::assertSame('a', $next->getHost());
        self::assertSame('/b', $next->getPath());
    }

    #[Test]
    public function withHost_normalizes_case(): void
    {
        $uri = (new Uri())->withHost('Sub.DOMAIN.EXAMPLE');

        self::assertSame('sub.domain.example', $uri->getHost());
    }

    #[Test]
    public function withPath_replaces_path(): void
    {
        $uri = Uri::fromString('http://h/old')->withPath('/new');

        self::assertSame('/new', $uri->getPath());
        self::assertSame('http://h/new', (string) $uri);
    }

    #[Test]
    public function withQuery_strips_leading_question_mark(): void
    {
        $uri = (new Uri(path: '/p'))->withQuery('?a=b');

        self::assertSame('a=b', $uri->getQuery());
    }

    #[Test]
    public function withFragment_strips_leading_hash(): void
    {
        $uri = (new Uri(path: '/p'))->withFragment('#sec');

        self::assertSame('sec', $uri->getFragment());
    }

    #[Test]
    public function withUserInfo_encodes_password_when_present(): void
    {
        $uri = (new Uri(host: 'h'))->withUserInfo('u', 'p:w');

        self::assertSame('u:p:w', $uri->getUserInfo());
        self::assertSame('u:p:w@h', $uri->getAuthority());
    }

    #[Test]
    public function withUserInfo_without_password_uses_username_only(): void
    {
        $uri = (new Uri(host: 'h'))->withUserInfo('guest');

        self::assertSame('guest', $uri->getUserInfo());
    }

    #[Test]
    public function withPort_null_clears_explicit_port(): void
    {
        $uri = Uri::fromString('http://example.com:8080/')->withPort(null);

        self::assertNull($uri->getPort());
        self::assertSame('http://example.com/', (string) $uri);
    }

    #[Test]
    public function withPort_accepts_boundary_values(): void
    {
        $lo = (new Uri(scheme: 'http', host: 'h'))->withPort(1);
        $hi = (new Uri(scheme: 'http', host: 'h'))->withPort(65535);

        self::assertSame(1, $lo->getPort());
        self::assertSame(65535, $hi->getPort());
    }

    #[Test]
    public function withPort_rejects_out_of_range(): void
    {
        $uri = new Uri(host: 'h');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid port number: 0');
        $uri->withPort(0);
    }

    #[Test]
    public function withPort_rejects_above_65535(): void
    {
        $uri = new Uri(host: 'h');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid port number: 65536');
        $uri->withPort(65536);
    }

    #[Test]
    public function with_methods_return_new_instance_and_preserve_original(): void
    {
        $original = Uri::fromString('http://orig/path?x=1#f');
        $derived = $original->withPath('/other')->withQuery('y=2')->withFragment('g');

        self::assertSame('/path', $original->getPath());
        self::assertSame('x=1', $original->getQuery());
        self::assertSame('f', $original->getFragment());
        self::assertSame('/other', $derived->getPath());
        self::assertSame('y=2', $derived->getQuery());
        self::assertSame('g', $derived->getFragment());
    }

    #[Test]
    public function getPort_for_non_http_scheme_treats_80_as_explicit(): void
    {
        $uri = (new Uri(scheme: 'ftp', host: 'files.example', port: 80, path: '/'));

        self::assertSame(80, $uri->getPort());
        self::assertStringContainsString(':80', $uri->getAuthority());
    }
}
