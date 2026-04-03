<?php

declare(strict_types=1);

namespace Manychois\PhpStrongTests\Texts;

use Manychois\PhpStrong\Texts\Capture;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Capture.
 */
final class CaptureTest extends TestCase
{
    #[Test]
    public function constructor_sets_value_and_index(): void
    {
        $c = new Capture('hello', 3);

        self::assertSame('hello', $c->value);
        self::assertSame(3, $c->index);
    }

    #[Test]
    public function constructor_omits_index_when_null(): void
    {
        $c = new Capture('x');

        self::assertSame('x', $c->value);
        self::assertNull($c->index);
    }
}
