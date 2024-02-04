<?php

declare(strict_types=1);

namespace Manychois\PhpStrongTests\Collections;

use DateTime;
use Exception;
use Generator;
use InvalidArgumentException;
use Manychois\PhpStrong\Collections\Map;
use PHPUnit\Framework\TestCase;

class MapTest extends TestCase
{
    public function testFactoryMethod(): void
    {
        $keyTypes = [
            'int',
            'object',
            'string',
        ];
        $valueTypes = [
            'bool',
            'float',
            'int',
            'object',
            'string',
        ];
        foreach ($keyTypes as $keyType) {
            foreach ($valueTypes as $valueType) {
                $methodName = \sprintf('%sTo%s', $keyType, \ucfirst($valueType));
                $args = [];
                $keyConstraint = $keyType;
                if ($keyType === 'object') {
                    $keyConstraint = DateTime::class;
                    $args[] = DateTime::class;
                }
                $valueConstraint = $valueType;
                if ($valueType === 'object') {
                    $valueConstraint = Exception::class;
                    $args[] = Exception::class;
                }
                $map = \call_user_func([Map::class, $methodName], ...$args);
                assert($map instanceof Map);
                static::assertSame($keyConstraint, $map->keyConstraint);
                static::assertSame($valueConstraint, $map->valueConstraint);
            }
        }
    }

    public function testOffsetExists(): void
    {
        $map = Map::intToObject(DateTime::class);
        static::assertFalse(isset($map[0]));

        $map->add(20201213, new DateTime('2020-12-13'));
        static::assertCount(1, $map);
        static::assertTrue(isset($map[20201213]));
        static::assertFalse(isset($map['20201213']));

        $map = Map::objectToInt(DateTime::class);
        $date = new DateTime('2020-12-13');
        static::assertFalse(isset($map[$date]));
        static::assertFalse(isset($map['2020-12-13']));

        $map->add($date, 20201213);
        static::assertTrue(isset($map[$date]));
    }

    public function testOffsetGetSet(): void
    {
        $map = Map::intToObject(DateTime::class);
        $map[20201213] = new DateTime('2020-12-13');
        static::assertInstanceOf(DateTime::class, $map[20201213]);
        static::assertEquals('2020-12-13', $map[20201213]->format('Y-m-d'));

        $date = new DateTime('2020-12-13');
        $map = Map::objectToInt(DateTime::class);
        $map[$date] = 20201213;
        static::assertEquals(20201213, $map[$date]);
    }

    public function testOffsetUnset(): void
    {
        $map = Map::intToObject(DateTime::class);
        $map[20201213] = new DateTime('2020-12-13');
        unset($map['0']); // @phpstan-ignore-line
        static::assertTrue(isset($map[20201213]));
        unset($map[0]);
        static::assertTrue(isset($map[20201213]));
        unset($map[20201213]);
        static::assertFalse(isset($map[20201213]));

        $map = Map::objectToInt(DateTime::class);
        $date = new DateTime('2020-12-13');
        $map[$date] = 20201213;
        unset($map['0']); // @phpstan-ignore-line
        static::assertTrue(isset($map[$date]));
        unset($map[new DateTime('2020-12-13')]);
        static::assertTrue(isset($map[$date]));
        unset($map[$date]);
        static::assertFalse(isset($map[$date]));
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
            $map = Map::intToBool();
            echo $map['a']; // @phpstan-ignore-line
        }, 'The offset must be of type int.', ];
        ++$i;
        yield "offsetGet #$i" => [static function (): void {
            $map = Map::objectToString(DateTime::class);
            echo $map[new DateTime()];
        }, 'The offset does not exist in the map.', ];
        ++$i;
        yield "offsetGet #$i" => [static function (): void {
            $map = Map::intToFloat();
            echo $map[-1];
        }, 'The offset does not exist in the map.', ];

        $i = 0;
        yield "offsetSet #$i" => [static function (): void {
            $map = Map::intToBool();
            $map[] = true;
        }, 'The offset must be of type int.', ];
        ++$i;
        yield "offsetSet #$i" => [static function (): void {
            $map = Map::intToObject(DateTime::class);
            $map[3] = 4; // @phpstan-ignore-line
        }, 'The value must be of type DateTime.', ];

        $i = 0;
        yield "add #$i" => [static function (): void {
            $map = Map::intToBool();
            $map->add('a', true); // @phpstan-ignore-line
        }, 'The key must be of type int.', ];
        ++$i;
        yield "add #$i" => [static function (): void {
            $map = Map::intToBool();
            $map->add(1, 2); // @phpstan-ignore-line
        }, 'The value must be of type bool.', ];

        $i = 0;
        yield "find #$i" => [static function (): void {
            $map = Map::intToBool();
            $map->find('a'); // @phpstan-ignore-line
        }, 'The value must be of type bool.', ];
        ++$i;
        yield "find #$i" => [static function (): void {
            $map = Map::intToBool();
            $map->find(true);
        }, 'The value does not exist in the map.', ];

        $i = 0;
        yield "get #$i" => [static function (): void {
            $map = Map::intToBool();
            $map->get('a'); // @phpstan-ignore-line
        }, 'The key must be of type int.', ];
        ++$i;
        yield "get #$i" => [static function (): void {
            $map = Map::intToBool();
            $map->get(1);
        }, 'The key does not exist in the map.', ];
        ++$i;
        yield "get #$i" => [static function (): void {
            $map = Map::objectToString(DateTime::class);
            $map->get(new DateTime());
        }, 'The key does not exist in the map.', ];

        $i = 0;
        yield "remove #$i" => [static function (): void {
            $map = Map::intToBool();
            $map->remove('a'); // @phpstan-ignore-line
        }, 'The key must be of type int.', ];

        $i = 0;
        yield "tryFind #$i" => [static function (): void {
            $map = Map::intToBool();
            $map->tryFind('a'); // @phpstan-ignore-line
        }, 'The value must be of type bool.', ];

        $i = 0;
        yield "tryGet #$i" => [static function (): void {
            $map = Map::intToBool();
            $map->tryGet('a'); // @phpstan-ignore-line
        }, 'The key must be of type int.', ];
    }

    public function testGetIterator(): void
    {
        $map = Map::intToBool();
        $map->add(1, true);
        $map->add(2, false);
        $map->add(3, true);

        $keys = [];
        $values = [];
        foreach ($map as $key => $value) {
            $keys[] = $key;
            $values[] = $value;
        }
        static::assertSame([1, 2, 3], $keys);
        static::assertSame([true, false, true], $values);

        $map = Map::objectToInt(DateTime::class);
        $map->add(new DateTime('2020-12-13'), 20201213);
        $map->add(new DateTime('2020-12-14'), 20201214);
        $map->add(new DateTime('2020-12-15'), 20201215);

        $keys = [];
        $values = [];
        foreach ($map as $key => $value) {
            $keys[] = $key->format('Y-m-d');
            $values[] = $value;
        }

        static::assertSame(['2020-12-13', '2020-12-14', '2020-12-15'], $keys);
        static::assertSame([20201213, 20201214, 20201215], $values);
    }

    public function testFind(): void
    {
        $map = Map::intToString();
        $map->add(10, 'a');
        $map->add(9, 'b');
        $map->add(8, 'c');
        $map->add(7, 'a');
        $map->add(6, 'b');
        $map->add(5, 'c');

        static::assertSame(10, $map->find('a'));
        static::assertSame(9, $map->find('b'));
        static::assertSame(8, $map->find('c'));
    }

    public function testGet(): void
    {
        $map = Map::intToString();
        $map->add(10, 'a');
        $map->add(9, 'b');
        $map->add(8, 'c');

        static::assertSame('a', $map->get(10));
        static::assertSame('b', $map->get(9));
        static::assertSame('c', $map->get(8));

        $map = Map::objectToBool(DateTime::class);
        $date1 = new DateTime('2020-12-13');
        $date2 = new DateTime('2020-12-14');
        $date3 = new DateTime('2020-12-15');
        $map->add($date1, true);
        $map->add($date2, false);
        $map->add($date3, true);

        static::assertTrue($map->get($date1));
        static::assertFalse($map->get($date2));
        static::assertTrue($map->get($date3));
    }

    public function testRemove(): void
    {
        $map = Map::intToInt();
        $map->add(1, 2);
        $map->add(2, 4);
        $map->add(3, 6);

        static::assertTrue($map->remove(2));
        static::assertCount(2, $map);
        static::assertFalse($map->remove(2));
        static::assertCount(2, $map);
        static::assertNull($map->tryGet(2));

        $map = Map::objectToInt(DateTime::class);
        $date1 = new DateTime('2020-12-13');
        $date2 = new DateTime('2020-12-14');
        $date3 = new DateTime('2020-12-15');
        $map->add($date1, 20201213);
        $map->add($date2, 20201214);
        $map->add($date3, 20201215);

        static::assertTrue($map->remove($date2));
        static::assertCount(2, $map);
        static::assertFalse($map->remove($date2));
        static::assertCount(2, $map);
        static::assertNull($map->tryGet($date2));
    }

    public function testTryFind(): void
    {
        $map = Map::intToString();
        $map->add(10, 'a');
        $map->add(9, 'b');
        $map->add(8, 'c');

        static::assertSame(10, $map->tryFind('a'));
        static::assertSame(9, $map->tryFind('b'));
        static::assertSame(8, $map->tryFind('c'));
        static::assertNull($map->tryFind('d'));
    }

    public function testTryGet(): void
    {
        $map = Map::intToString();
        $map->add(10, 'a');
        $map->add(9, 'b');
        $map->add(8, 'c');

        static::assertSame('a', $map->tryGet(10));
        static::assertSame('b', $map->tryGet(9));
        static::assertSame('c', $map->tryGet(8));
        static::assertNull($map->tryGet(123));

        $map = Map::objectToInt(DateTime::class);
        $date1 = new DateTime('2020-12-13');
        $date2 = new DateTime('2020-12-14');
        $date3 = new DateTime('2020-12-15');
        $map->add($date1, 20201213);
        $map->add($date2, 20201214);
        $map->add($date3, 20201215);

        static::assertSame(20201213, $map->tryGet($date1));
        static::assertSame(20201214, $map->tryGet($date2));
        static::assertSame(20201215, $map->tryGet($date3));
        static::assertNull($map->tryGet(new DateTime('2020-12-13')));
    }
}
