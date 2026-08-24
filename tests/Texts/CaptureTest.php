<?php

declare(strict_types=1);

namespace Manychois\PhpStrongTests\Texts;

use InvalidArgumentException;
use Manychois\PhpStrong\Texts\Capture;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CaptureTest extends TestCase
{
    #[Test]
    public function constructor_defaultsIndexToNull(): void
    {
        $c = new Capture('x');

        self::assertSame('x', $c->value);
        self::assertNull($c->index);
    }

    #[Test]
    public function constructor_setsValueAndIndex(): void
    {
        $c = new Capture('hello', 3);

        self::assertSame('hello', $c->value);
        self::assertSame(3, $c->index);
    }

    #[Test]
    public function constructor_throwsWhenIndexIsNegative(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Capture('x', -1);
    }
}
