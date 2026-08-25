<?php

declare(strict_types=1);

namespace Manychois\PhpStrongTests\Http\Internal;

use InvalidArgumentException;
use Manychois\PhpStrong\Http\Internal\AbstractMessage;
use Manychois\PhpStrong\Http\StreamFactory;
use PHPUnit\Framework\Attributes\DataProvider;
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

    #[Test]
    public function constructor_accepts_a_numeric_header_name(): void
    {
        $message = self::message(['123' => 'x']);
        self::assertSame(['x'], $message->getHeader('123'));
    }

    #[Test]
    public function constructor_rejects_an_invalid_header_name(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('"X Y" is not a valid header name.');

        self::message(['X Y' => 'v']);
    }

    #[Test]
    public function getHeaders_keys_a_numeric_header_name_as_an_int(): void
    {
        $message = self::message([])->withHeader('123', 'x');
        $names = array_keys($message->getHeaders());

        // PHP coerces a numeric string array key to an integer on write, so no implementation can hand this back
        // as a string. The name still round-trips through getHeader(); a consumer using the key as a string casts.
        self::assertSame([123], $names);
        self::assertSame(['x'], $message->getHeader('123'));
    }

    #[Test]
    public function withHeader_rejects_an_empty_header_name(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('"" is not a valid header name.');

        self::message([])->withHeader('', 'v');
    }

    #[Test]
    public function withHeader_rejects_an_empty_value_list(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Header "Vary" needs at least one value; an empty array was given.');

        self::message([])->withHeader('Vary', []);
    }

    #[Test]
    public function withHeader_rejects_an_invalid_header_name(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('"X Y" is not a valid header name.');

        self::message([])->withHeader('X Y', 'v');
    }

    #[Test]
    #[DataProvider('provideValuesWithForbiddenCharacters')]
    public function withHeader_rejects_a_value_carrying_a_forbidden_character(string $value): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Header "X-Bad" has a value containing a carriage return, line feed or NUL.');

        self::message([])->withHeader('X-Bad', $value);
    }

    /**
     * @return iterable<string,array{string}>
     */
    public static function provideValuesWithForbiddenCharacters(): iterable
    {
        yield 'carriage return' => ["a\rB"];
        yield 'line feed' => ["a\nB"];
        yield 'header injection attempt' => ["a\r\nX-Injected: 1"];
        yield 'obsolete line folding' => ["a\r\n b"];
        yield 'NUL' => ["a\0b"];
    }

    #[Test]
    public function withAddedHeader_rejects_a_value_carrying_a_forbidden_character(): void
    {
        $this->expectException(InvalidArgumentException::class);

        self::message(['Vary' => 'Accept'])->withAddedHeader('Vary', "Origin\r\nX: 1");
    }

    #[Test]
    public function constructor_rejects_a_value_carrying_a_forbidden_character(): void
    {
        $this->expectException(InvalidArgumentException::class);

        self::message(['X-Bad' => "a\r\nX-Injected: 1"]);
    }

    #[Test]
    public function a_value_keeps_its_surrounding_whitespace(): void
    {
        $message = self::message(['X-Pad' => ' a, b ']);
        self::assertSame([' a, b '], $message->getHeader('X-Pad'));
    }

    #[Test]
    public function an_empty_string_stays_a_valid_header_value(): void
    {
        $message = self::message([])->withHeader('X-Empty', '');
        self::assertSame([''], $message->getHeader('X-Empty'));
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
