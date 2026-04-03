<?php

declare(strict_types=1);

namespace Manychois\PhpStrongTests\Web\Internal;

use Manychois\PhpStrong\Web\Internal\AbstractMessage;
use Manychois\PhpStrong\Web\StreamFactory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\StreamInterface as IStream;

/**
 * Unit tests for {@see AbstractMessage}.
 */
final class AbstractMessageTest extends TestCase
{
    #[Test]
    public function constructor_casts_header_values_to_strings(): void
    {
        $message = self::message(['X-Num' => [1, '2', 3.5]]);
        self::assertSame(['1', '2', '3.5'], $message->getHeader('x-num'));
    }

    #[Test]
    public function getBody_returns_attached_stream(): void
    {
        $factory = new StreamFactory();
        $stream = $factory->createStream('body');
        $message = self::message([], $stream);
        self::assertSame($stream, $message->getBody());
    }

    #[Test]
    public function getHeader_is_case_insensitive(): void
    {
        $message = self::message(['Accept-Language' => 'en']);
        self::assertSame(['en'], $message->getHeader('accept-language'));
        self::assertSame(['en'], $message->getHeader('ACCEPT-LANGUAGE'));
    }

    #[Test]
    public function getHeader_returns_empty_list_for_unknown_name(): void
    {
        $message = self::message([]);
        self::assertSame([], $message->getHeader('Missing'));
    }

    #[Test]
    public function getHeaderLine_joins_values_with_comma_space(): void
    {
        $message = self::message(['Vary' => ['Accept', 'Accept-Encoding']]);
        self::assertSame('Accept, Accept-Encoding', $message->getHeaderLine('vary'));
    }

    #[Test]
    public function getHeaders_preserves_constructor_header_name_casing(): void
    {
        $message = self::message(['X-Custom' => 'yes']);
        $all = $message->getHeaders();
        self::assertArrayHasKey('X-Custom', $all);
        self::assertSame(['yes'], $all['X-Custom']);
    }

    #[Test]
    public function getProtocolVersion_reflects_constructor(): void
    {
        $message = self::message([], null, '2.0');
        self::assertSame('2.0', $message->getProtocolVersion());
    }

    #[Test]
    public function hasHeader_is_case_insensitive(): void
    {
        $message = self::message(['ETag' => '"x"']);
        self::assertTrue($message->hasHeader('etag'));
        self::assertFalse($message->hasHeader('Link'));
    }

    #[Test]
    public function initial_headers_with_same_normalized_name_last_name_wins_for_getHeader(): void
    {
        $message = self::message([
            'X-A' => 'first',
            'x-a' => 'second',
        ]);
        self::assertSame(['second'], $message->getHeader('X-a'));
        $all = $message->getHeaders();
        self::assertCount(2, $all);
    }

    #[Test]
    public function withAddedHeader_appends_values_under_canonical_existing_name(): void
    {
        $original = self::message(['Cache-Control' => 'private']);
        $next = $original->withAddedHeader('cache-control', 'max-age=60');
        self::assertSame(['private'], $original->getHeader('Cache-Control'));
        self::assertSame(['private', 'max-age=60'], $next->getHeader('Cache-Control'));
    }

    #[Test]
    public function withBody_replaces_stream_on_clone_only(): void
    {
        $factory = new StreamFactory();
        $first = $factory->createStream('a');
        $second = $factory->createStream('b');
        $original = self::message([], $first);
        $next = $original->withBody($second);
        self::assertSame($first, $original->getBody());
        self::assertSame($second, $next->getBody());
    }

    #[Test]
    public function withHeader_replaces_existing_header_case_insensitively(): void
    {
        $original = self::message(['Content-Type' => 'text/plain']);
        $next = $original->withHeader('content-type', 'application/json');
        self::assertSame(['text/plain'], $original->getHeader('Content-Type'));
        self::assertSame(['application/json'], $next->getHeader('Content-Type'));
        self::assertArrayHasKey('content-type', $next->getHeaders());
    }

    #[Test]
    public function withProtocolVersion_leaves_original_unchanged(): void
    {
        $original = self::message([], null, '1.1');
        $next = $original->withProtocolVersion('1.0');
        self::assertSame('1.1', $original->getProtocolVersion());
        self::assertSame('1.0', $next->getProtocolVersion());
    }

    #[Test]
    public function withoutHeader_is_noop_when_header_missing_returns_same_instance(): void
    {
        $message = self::message(['A' => '1']);
        $same = $message->withoutHeader('b');
        self::assertSame($message, $same);
    }

    #[Test]
    public function withoutHeader_removes_case_insensitively(): void
    {
        $message = self::message(['X-Remove' => '1']);
        $next = $message->withoutHeader('x-remove');
        self::assertTrue($message->hasHeader('X-Remove'));
        self::assertFalse($next->hasHeader('X-Remove'));
    }

    /**
     * @param array<string,string|string[]> $headers
     */
    private static function message(
        array $headers = [],
        ?IStream $body = null,
        string $protocolVersion = '1.1',
    ): AbstractMessage {
        $body ??= (new StreamFactory())->createStream();
        return new class ($headers, $body, $protocolVersion) extends AbstractMessage {
            /**
             * @param array<string,string|string[]> $headers
             */
            public function __construct(array $headers, IStream $body, string $protocolVersion = '1.1')
            {
                parent::__construct($headers, $body, $protocolVersion);
            }
        };
    }
}
