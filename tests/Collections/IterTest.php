<?php

declare(strict_types=1);

namespace Manychois\PhpStrongTests\Collections;

use Manychois\PhpStrong\Collections\Iter;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class IterTest extends TestCase
{
    #[Test]
    public function mapTransformsEachElementPreservingKeys(): void
    {
        $source = ['a' => 1, 'b' => 2, 'c' => 3];
        $result = Iter::map($source, static fn (int $v): int => $v * 10);

        self::assertSame(['a' => 10, 'b' => 20, 'c' => 30], iterator_to_array($result));
    }

    #[Test]
    public function mapIsLazy(): void
    {
        $calls = 0;
        $source = [1, 2, 3];
        $result = Iter::map($source, static function (int $v) use (&$calls): int {
            $calls++;

            return $v;
        });

        self::assertSame(0, $calls);
        iterator_to_array($result);
        self::assertSame(3, $calls);
    }

    #[Test]
    public function filterKeepsOnlyMatchingElementsPreservingKeys(): void
    {
        $source = ['a' => 1, 'b' => 2, 'c' => 3, 'd' => 4];
        $result = Iter::filter($source, static fn (int $v): bool => $v % 2 === 0);

        self::assertSame(['b' => 2, 'd' => 4], iterator_to_array($result));
    }

    #[Test]
    public function filterIsLazy(): void
    {
        $calls = 0;
        $source = [1, 2, 3];
        $result = Iter::filter($source, static function (int $v) use (&$calls): bool {
            $calls++;

            return true;
        });

        self::assertSame(0, $calls);
        iterator_to_array($result);
        self::assertSame(3, $calls);
    }

    #[Test]
    public function tapCallsSideEffectAndYieldsElementsUnchanged(): void
    {
        $seen = [];
        $source = ['a' => 1, 'b' => 2];
        $result = Iter::tap($source, static function (int $v, int|string $k) use (&$seen): void {
            $seen[] = [$k, $v];
        });

        self::assertSame(['a' => 1, 'b' => 2], iterator_to_array($result));
        self::assertSame([['a', 1], ['b', 2]], $seen);
    }

    #[Test]
    public function tapIsLazy(): void
    {
        $calls = 0;
        $source = [1, 2];
        $result = Iter::tap($source, static function () use (&$calls): void {
            $calls++;
        });

        self::assertSame(0, $calls);
        iterator_to_array($result);
        self::assertSame(2, $calls);
    }
}
