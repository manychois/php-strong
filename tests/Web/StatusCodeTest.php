<?php

declare(strict_types=1);

namespace Manychois\PhpStrongTests\Web;

use InvalidArgumentException;
use Manychois\PhpStrong\Web\StatusCode;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for {@see StatusCode}.
 */
final class StatusCodeTest extends TestCase
{
    #[Test]
    public function backed_int_values_are_unique_and_fromCode_round_trips_each_case(): void
    {
        $seen = [];
        foreach (StatusCode::cases() as $case) {
            self::assertNotContains($case->value, $seen, 'Duplicate status code value');
            $seen[] = $case->value;
            self::assertSame($case, StatusCode::tryFrom($case->value));
            self::assertSame($case, StatusCode::fromCode($case->value));
        }
    }

    #[Test]
    public function fromCode_throws_InvalidArgumentException_for_unregistered_code(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Not a registered HTTP status code in ' . StatusCode::class . ': 599.',
        );
        StatusCode::fromCode(599);
    }

    #[Test]
    public function tryFrom_returns_null_for_code_not_in_enum(): void
    {
        self::assertNull(StatusCode::tryFrom(599));
        self::assertNull(StatusCode::tryFrom(199));
    }

    #[Test]
    public function reasonPhrase_matches_IANA_style_default_for_every_case(): void
    {
        $expectedByCode = self::expectedReasonPhrasesByCode();
        self::assertCount(count(StatusCode::cases()), $expectedByCode);
        foreach (StatusCode::cases() as $case) {
            self::assertArrayHasKey($case->value, $expectedByCode);
            self::assertSame(
                $expectedByCode[$case->value],
                $case->reasonPhrase(),
                'status ' . (string) $case->value,
            );
        }
    }

    /**
     * Parity with {@see StatusCode::reasonPhrase()} (update when adding enum cases).
     *
     * @return array<int, string>
     */
    private static function expectedReasonPhrasesByCode(): array
    {
        return [
            100 => 'Continue',
            101 => 'Switching Protocols',
            102 => 'Processing',
            103 => 'Early Hints',
            200 => 'OK',
            201 => 'Created',
            202 => 'Accepted',
            203 => 'Non-Authoritative Information',
            204 => 'No Content',
            205 => 'Reset Content',
            206 => 'Partial Content',
            207 => 'Multi-Status',
            208 => 'Already Reported',
            226 => 'IM Used',
            300 => 'Multiple Choices',
            301 => 'Moved Permanently',
            302 => 'Found',
            303 => 'See Other',
            304 => 'Not Modified',
            305 => 'Use Proxy',
            307 => 'Temporary Redirect',
            308 => 'Permanent Redirect',
            400 => 'Bad Request',
            401 => 'Unauthorized',
            402 => 'Payment Required',
            403 => 'Forbidden',
            404 => 'Not Found',
            405 => 'Method Not Allowed',
            406 => 'Not Acceptable',
            407 => 'Proxy Authentication Required',
            408 => 'Request Timeout',
            409 => 'Conflict',
            410 => 'Gone',
            411 => 'Length Required',
            412 => 'Precondition Failed',
            413 => 'Payload Too Large',
            414 => 'URI Too Long',
            415 => 'Unsupported Media Type',
            416 => 'Range Not Satisfiable',
            417 => 'Expectation Failed',
            418 => "I'm a teapot",
            421 => 'Misdirected Request',
            422 => 'Unprocessable Content',
            423 => 'Locked',
            424 => 'Failed Dependency',
            425 => 'Too Early',
            426 => 'Upgrade Required',
            428 => 'Precondition Required',
            429 => 'Too Many Requests',
            431 => 'Request Header Fields Too Large',
            451 => 'Unavailable For Legal Reasons',
            500 => 'Internal Server Error',
            501 => 'Not Implemented',
            502 => 'Bad Gateway',
            503 => 'Service Unavailable',
            504 => 'Gateway Timeout',
            505 => 'HTTP Version Not Supported',
            506 => 'Variant Also Negotiates',
            507 => 'Insufficient Storage',
            508 => 'Loop Detected',
            510 => 'Not Extended',
            511 => 'Network Authentication Required',
        ];
    }
}
