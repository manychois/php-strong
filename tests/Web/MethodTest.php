<?php

declare(strict_types=1);

namespace Manychois\PhpStrongTests\Web;

use InvalidArgumentException;
use Manychois\PhpStrong\Web\Method;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for {@see Method}.
 */
final class MethodTest extends TestCase
{
    #[Test]
    public function backed_string_values_are_uppercase_http_tokens(): void
    {
        foreach (Method::cases() as $case) {
            self::assertSame(strtoupper($case->value), $case->value);
            self::assertSame($case, Method::tryFrom($case->value));
        }
    }

    #[Test]
    public function fromString_accepts_canonical_uppercase_for_each_case(): void
    {
        foreach (Method::cases() as $case) {
            self::assertSame($case, Method::fromString($case->value));
        }
    }

    #[Test]
    public function fromString_normalizes_case(): void
    {
        self::assertSame(Method::Get, Method::fromString('get'));
        self::assertSame(Method::Get, Method::fromString('GeT'));
        self::assertSame(Method::Patch, Method::fromString('patch'));
    }

    #[Test]
    public function fromString_throws_InvalidArgumentException_for_unknown_method(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown HTTP method: CHEESE');
        Method::fromString('CHEESE');
    }

    #[Test]
    public function fromString_exception_preserves_original_casing_in_message(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown HTTP method: FoO');
        Method::fromString('FoO');
    }

    #[Test]
    public function tryFrom_matches_enum_values(): void
    {
        self::assertSame(Method::Post, Method::tryFrom('POST'));
        self::assertNull(Method::tryFrom('MERGE'));
    }
}
