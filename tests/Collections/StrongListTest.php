<?php

declare(strict_types=1);

namespace Manychois\PhpStrongTests\Collections;

use DateTime;
use Generator;
use InvalidArgumentException;
use Manychois\PhpStrong\Collections\StrongList;
use PHPUnit\Framework\TestCase;

class StrongListTest extends TestCase
{
    public function testArrayAccess(): void
    {
        $list = StrongList::ofString();
        static::assertFalse(isset($list[0]));
        static::assertFalse(isset($list[new DateTime()])); // @phpstan-ignore-line
        $list[] = 'a';
        $list[] = 'b';
        $list[] = 'c';
        static::assertSame('a', $list[0]);
        static::assertSame('b', $list[1]);
        static::assertSame('c', $list[2]);
        static::assertCount(3, $list);
        static::assertTrue(isset($list[0]));
        static::assertTrue(isset($list[1]));
        static::assertTrue(isset($list[2]));

        $list[1] = 'd';
        static::assertSame('a', $list[0]);
        static::assertSame('d', $list[1]);
        static::assertSame('c', $list[2]);
        static::assertCount(3, $list);

        unset($list[0]);
        static::assertSame('d', $list[0]);
        static::assertSame('c', $list[1]);
        static::assertCount(2, $list);
    }

    /**
     * @dataProvider provideInvalidArgumentExceptionCheck
     */
    public function testInvalidArgumentExceptionCheck(callable $action, string $errMsg): void
    {
        static::expectException(InvalidArgumentException::class);
        static::expectExceptionMessage($errMsg);
        $action();
    }

    public static function provideInvalidArgumentExceptionCheck(): Generator
    {
        $i = 0;
        yield "offsetGet #$i" => [static function (): void {
            $list = StrongList::ofBool();
            echo $list['a']; // @phpstan-ignore-line
        }, 'The offset must be an integer.', ];
        ++$i;
        yield "offsetGet #$i" => [static function (): void {
            $list = StrongList::ofFloat();
            echo $list[-1];
        }, 'The offset must be greater than or equal to 0.', ];
        ++$i;
        yield "offsetGet #$i" => [static function (): void {
            $list = StrongList::ofInt();
            $list[0] = -100;
            echo $list[2];
        }, 'The offset cannot be greater than or equal to 1.', ];

        $i = 0;
        yield "offsetSet #$i" => [static function (): void {
            $list = StrongList::ofFloat();
            $list[] = true; // @phpstan-ignore-line
        }, 'The value must be of type float.', ];
        ++$i;
        yield "offsetSet #$i" => [static function (): void {
            $list = StrongList::ofFloat();
            $list['a'] = 1.0; // @phpstan-ignore-line
        }, 'The offset must be an integer.', ];
        ++$i;
        yield "offsetSet #$i" => [static function (): void {
            $list = StrongList::ofFloat();
            $list[-1] = 1.0;
        }, 'The offset must be greater than or equal to 0.', ];
        ++$i;
        yield "offsetSet #$i" => [static function (): void {
            $list = StrongList::ofFloat();
            $list[0] = 1.0;
            $list[2] = 2.0;
        }, 'The offset cannot be greater than 1.', ];

        $i = 0;
        yield "add #$i" => [static function (): void {
            $list = StrongList::ofFloat();
            $list->add('a'); // @phpstan-ignore-line
        }, 'The item must be of type float.', ];

        $i = 0;
        yield "contains #$i" => [static function (): void {
            $list = StrongList::ofFloat();
            $list->contains('a'); // @phpstan-ignore-line
        }, 'The item must be of type float.', ];

        $i = 0;
        yield "indexOf #$i" => [static function (): void {
            $list = StrongList::ofFloat();
            $list->indexOf('a'); // @phpstan-ignore-line
        }, 'The item must be of type float.', ];

        $i = 0;
        yield "insert #$i" => [static function (): void {
            $list = StrongList::ofBool();
            $list->insert(-1, true);
        }, 'The index must be greater than or equal to zero.', ];
        ++$i;
        yield "insert #$i" => [static function (): void {
            $list = StrongList::ofBool();
            $list->insert(1, true);
        }, 'The index cannot be greater than 0.', ];
        ++$i;
        yield "insert #$i" => [static function (): void {
            $list = StrongList::ofBool();
            $list->insert(0, 1); // @phpstan-ignore-line
        }, 'The item must be of type bool.', ];

        $i = 0;
        yield "item #$i" => [static function (): void {
            $list = StrongList::ofString();
            $list->item(-1);
        }, 'The index must be greater than or equal to zero.', ];
        ++$i;
        yield "item #$i" => [static function (): void {
            $list = StrongList::ofString();
            $list->item(1);
        }, 'The index cannot be greater than or equal to 0.', ];

        $i = 0;
        yield "remove #$i" => [static function (): void {
            $list = StrongList::ofString();
            $list->remove(1); // @phpstan-ignore-line
        }, 'The item must be of type string.', ];

        $i = 0;
        yield "removeAt #$i" => [static function (): void {
            $list = StrongList::ofString();
            $list->removeAt(-1);
        }, 'The index must be greater than or equal to zero.', ];
        ++$i;
        yield "removeAt #$i" => [static function (): void {
            $list = StrongList::ofString();
            $list->removeAt(1);
        }, 'The index cannot be greater than or equal to 0.', ];
    }

    public function testGetIterator(): void
    {
        $list = StrongList::ofObject(DateTime::class);
        $list->add(new DateTime('2021-01-01'));
        $list->add(new DateTime('2021-01-02'));
        $list->add(new DateTime('2021-01-03'));
        $s = [];
        foreach ($list as $i => $item) {
            static::assertTrue(\is_int($i)); // @phpstan-ignore-line
            $s[] = $i . ':' . $item->format('Y-m-d');
        }

        static::assertSame(['0:2021-01-01', '1:2021-01-02', '2:2021-01-03'], $s);
    }

    public function testClear(): void
    {
        $list = StrongList::ofBool();
        $list->add(true);
        $list->add(false);
        $list->add(true);
        static::assertCount(3, $list);
        $list->clear();
        static::assertCount(0, $list);
    }

    public function testContains(): void
    {
        $list = StrongList::ofString();
        $list->add('a');
        $list->add('b');
        $list->add('c');
        static::assertTrue($list->contains('a'));
        static::assertTrue($list->contains('b'));
        static::assertTrue($list->contains('c'));
        static::assertFalse($list->contains('d'));
    }

    public function testIndexOf(): void
    {
        $list = StrongList::ofString();
        $list->add('a');
        $list->add('b');
        $list->add('c');
        static::assertSame(0, $list->indexOf('a'));
        static::assertSame(1, $list->indexOf('b'));
        static::assertSame(2, $list->indexOf('c'));
        static::assertSame(-1, $list->indexOf('d'));
    }

    public function testInsert(): void
    {
        $list = StrongList::ofString();
        $list->add('a');
        $list->add('b');
        $list->add('c');
        $list->insert(1, 'd');
        $list->insert(4, 'e');
        static::assertSame('a', $list->item(0));
        static::assertSame('d', $list->item(1));
        static::assertSame('b', $list->item(2));
        static::assertSame('c', $list->item(3));
        static::assertSame('e', $list->item(4));
        static::assertCount(5, $list);
    }

    public function testRemove(): void
    {
        $list = StrongList::ofString();
        $list->add('a');
        $list->add('b');
        $list->add('c');
        static::assertTrue($list->remove('b'));
        static::assertFalse($list->remove('d'));
        static::assertSame('a', $list->item(0));
        static::assertSame('c', $list->item(1));
        static::assertCount(2, $list);
    }

    public function testRemoveAt(): void
    {
        $list = StrongList::ofString();
        $list->add('a');
        $list->add('b');
        $list->add('c');
        static::assertSame('b', $list->removeAt(1));
        static::assertSame('a', $list->item(0));
        static::assertSame('c', $list->item(1));
        static::assertCount(2, $list);
    }

    public function testToArray(): void
    {
        $list = StrongList::ofString();
        $list->add('a');
        $list->add('b');
        $list->add('c');
        static::assertSame(['a', 'b', 'c'], $list->toArray());
    }
}
