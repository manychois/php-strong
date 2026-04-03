<?php

declare(strict_types=1);

namespace Manychois\PhpStrongTests\Web;

use InvalidArgumentException;
use Manychois\PhpStrong\Web\Uri;
use Manychois\PhpStrong\Web\UriFactory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Unit tests for {@see UriFactory}.
 */
final class UriFactoryTest extends TestCase
{
    #[Test]
    public function createUri_builds_uri_from_string(): void
    {
        $factory = new UriFactory();
        $uri = $factory->createUri('https://example.com/a?b=1#frag');

        self::assertInstanceOf(Uri::class, $uri);
        self::assertSame('https', $uri->getScheme());
        self::assertSame('example.com', $uri->getHost());
        self::assertSame('/a', $uri->getPath());
        self::assertSame('b=1', $uri->getQuery());
        self::assertSame('frag', $uri->getFragment());
    }

    #[Test]
    public function createUri_accepts_empty_string(): void
    {
        $factory = new UriFactory();
        $uri = $factory->createUri('');

        self::assertSame('', (string) $uri);
    }

    #[Test]
    public function createUri_wraps_parse_failures_as_invalid_argument(): void
    {
        $factory = new UriFactory();

        $this->expectException(InvalidArgumentException::class);
        $factory->createUri(':');
    }

    #[Test]
    public function createUri_chains_previous_exception_from_uri_parse(): void
    {
        $factory = new UriFactory();
        $caught = null;
        try {
            $factory->createUri(':');
        } catch (InvalidArgumentException $ex) {
            $caught = $ex;
        }

        self::assertInstanceOf(InvalidArgumentException::class, $caught);
        self::assertInstanceOf(RuntimeException::class, $caught->getPrevious());
    }
}
