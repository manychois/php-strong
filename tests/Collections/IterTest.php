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

    #[Test]
    public function flatMapConcatenatesMappedIterablesWithReindexedKeys(): void
    {
        $source = [1, 2];
        $result = Iter::flatMap($source, static fn (int $v): array => [$v, $v * 10]);

        static::assertSame([1, 10, 2, 20], iterator_to_array($result));
    }

    #[Test]
    public function flatMapIsLazy(): void
    {
        $calls = 0;
        $source = [1, 2];
        $result = Iter::flatMap($source, static function (int $v) use (&$calls): array {
            $calls++;

            return [$v];
        });

        static::assertSame(0, $calls);
        iterator_to_array($result);
        static::assertSame(2, $calls);
    }

    #[Test]
    public function takeYieldsAtMostCountElementsPreservingKeys(): void
    {
        $source = ['a' => 1, 'b' => 2, 'c' => 3];
        $result = Iter::take($source, 2);

        static::assertSame(['a' => 1, 'b' => 2], iterator_to_array($result));
    }

    #[Test]
    public function takeYieldsFewerElementsIfSourceIsShorter(): void
    {
        $source = [1, 2];
        $result = Iter::take($source, 5);

        static::assertSame([1, 2], iterator_to_array($result));
    }

    #[Test]
    public function takeThrowsForNegativeCount(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        iterator_to_array(Iter::take([1, 2], -1));
    }

    #[Test]
    public function takeDoesNotIterateSourceBeyondCount(): void
    {
        $calls = 0;
        $source = (static function () use (&$calls): \Generator {
            while (true) {
                $calls++;

                yield $calls;
            }
        })();

        $result = iterator_to_array(Iter::take($source, 3));

        static::assertSame([1, 2, 3], $result);
        static::assertSame(3, $calls);
    }

    #[Test]
    public function skipOmitsFirstCountElementsPreservingKeys(): void
    {
        $source = ['a' => 1, 'b' => 2, 'c' => 3];
        $result = Iter::skip($source, 1);

        static::assertSame(['b' => 2, 'c' => 3], iterator_to_array($result));
    }

    #[Test]
    public function skipYieldsNothingIfCountExceedsSourceLength(): void
    {
        $source = [1, 2];
        $result = Iter::skip($source, 5);

        static::assertSame([], iterator_to_array($result));
    }

    #[Test]
    public function skipThrowsForNegativeCount(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        iterator_to_array(Iter::skip([1, 2], -1));
    }

    #[Test]
    public function takeWhileStopsBeforeFirstNonMatchingElement(): void
    {
        $source = ['a' => 1, 'b' => 2, 'c' => 5, 'd' => 1];
        $result = Iter::takeWhile($source, static fn (int $v): bool => $v < 3);

        static::assertSame(['a' => 1, 'b' => 2], iterator_to_array($result));
    }

    #[Test]
    public function takeWhileYieldsNothingIfFirstElementFails(): void
    {
        $source = [5, 1, 2];
        $result = Iter::takeWhile($source, static fn (int $v): bool => $v < 3);

        static::assertSame([], iterator_to_array($result));
    }

    #[Test]
    public function skipWhileSkipsLeadingMatchesThenYieldsRemainder(): void
    {
        $source = ['a' => 1, 'b' => 2, 'c' => 5, 'd' => 1];
        $result = Iter::skipWhile($source, static fn (int $v): bool => $v < 3);

        static::assertSame(['c' => 5, 'd' => 1], iterator_to_array($result));
    }

    #[Test]
    public function skipWhileYieldsEverythingIfFirstElementFails(): void
    {
        $source = [5, 1, 2];
        $result = Iter::skipWhile($source, static fn (int $v): bool => $v < 3);

        static::assertSame([5, 1, 2], iterator_to_array($result));
    }

    #[Test]
    public function chunkGroupsElementsIntoListsOfGivenSize(): void
    {
        $source = [1, 2, 3, 4, 5];
        $result = Iter::chunk($source, 2);

        static::assertSame([[1, 2], [3, 4], [5]], iterator_to_array($result));
    }

    #[Test]
    public function chunkThrowsForNonPositiveSize(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        iterator_to_array(Iter::chunk([1, 2], 0));
    }

    #[Test]
    public function flattenConcatenatesOneLevelWithReindexedKeys(): void
    {
        $source = [[1, 2], ['a' => 3], [4]];
        $result = Iter::flatten($source);

        static::assertSame([1, 2, 3, 4], iterator_to_array($result));
    }

    #[Test]
    public function zipYieldsTuplesStoppingAtShortestSource(): void
    {
        $result = Iter::zip([1, 2, 3], ['a', 'b']);

        static::assertSame([[1, 'a'], [2, 'b']], iterator_to_array($result));
    }

    #[Test]
    public function zipYieldsNothingWithNoSources(): void
    {
        $result = Iter::zip();

        static::assertSame([], iterator_to_array($result));
    }

    #[Test]
    public function uniqueYieldsFirstOccurrenceOfEachElementByDefault(): void
    {
        $source = ['a' => 1, 'b' => 2, 'c' => 1, 'd' => 3];
        $result = Iter::unique($source);

        static::assertSame(['a' => 1, 'b' => 2, 'd' => 3], iterator_to_array($result));
    }

    #[Test]
    public function uniqueYieldsFirstOccurrenceByKeySelector(): void
    {
        $source = ['a' => [1, 'x'], 'b' => [2, 'x'], 'c' => [3, 'y']];
        $result = Iter::unique($source, static fn (array $v): string => $v[1]);

        static::assertSame(['a' => [1, 'x'], 'c' => [3, 'y']], iterator_to_array($result));
    }

    #[Test]
    public function toArrayPreservesKeysWithLastWriteWinningOnCollision(): void
    {
        $source = (static function (): \Generator {
            yield 'a' => 1;
            yield 'a' => 2;
            yield 'b' => 3;
        })();

        static::assertSame(['a' => 2, 'b' => 3], Iter::toArray($source));
    }

    #[Test]
    public function toListReindexesElements(): void
    {
        $source = ['a' => 1, 'b' => 2, 'c' => 3];

        static::assertSame([1, 2, 3], Iter::toList($source));
    }
}
