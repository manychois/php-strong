<?php

declare(strict_types=1);

namespace Manychois\PhpStrongTests;

use Manychois\PhpStrong\ArrayAccessor;
use OutOfBoundsException;
use PHPUnit\Framework\TestCase;
use TypeError;

class ArrayAccessorTest extends TestCase
{
    public function testAsBool(): void
    {
        $data = [
            'false' => false,
            'int_0' => 0,
            'int_1' => 1,
            'invalid' => 'invalid',
            'string_false' => 'false',
            'string_true' => 'true',
            'true' => true,
        ];
        $accessor = new ArrayAccessor($data);

        self::assertTrue($accessor->asBool('true'));
        self::assertFalse($accessor->asBool('false'));
        self::assertTrue($accessor->asBool('string_true'));
        self::assertFalse($accessor->asBool('string_false'));
        self::assertTrue($accessor->asBool('int_1'));
        self::assertFalse($accessor->asBool('int_0'));
        self::assertTrue($accessor->asBool('missing', true));
        self::assertFalse($accessor->asBool('missing'));

        $this->expectException(TypeError::class);
        $accessor->asBool('invalid');
    }

    public function testBool(): void
    {
        $data = ['key' => true];
        $accessor = new ArrayAccessor($data);

        self::assertTrue($accessor->bool('key'));

        $this->expectException(OutOfBoundsException::class);
        $accessor->bool('missing');
    }

    public function testNullableBool(): void
    {
        $data = ['key' => true, 'invalid' => 'invalid'];
        $accessor = new ArrayAccessor($data);

        self::assertTrue($accessor->nullableBool('key'));
        self::assertNull($accessor->nullableBool('missing'));

        $this->expectException(TypeError::class);
        $accessor->nullableBool('invalid');
    }

    public function testAsInt(): void
    {
        $data = [
            'float' => 7.89,
            'int' => 123,
            'invalid' => [],
            'string_int' => '456',
        ];
        $accessor = new ArrayAccessor($data);

        self::assertSame(123, $accessor->asInt('int'));
        self::assertSame(456, $accessor->asInt('string_int'));
        // intval() truncates to 7
        self::assertSame(7, $accessor->asInt('float'));
        self::assertSame(999, $accessor->asInt('missing', 999));
        self::assertSame(0, $accessor->asInt('missing'));

        $this->expectException(TypeError::class);
        $accessor->asInt('invalid');
    }

    public function testInt(): void
    {
        $data = ['key' => 123];
        $accessor = new ArrayAccessor($data);

        self::assertSame(123, $accessor->int('key'));

        $this->expectException(OutOfBoundsException::class);
        $accessor->int('missing');
    }

    public function testNullableInt(): void
    {
        $data = ['key' => 123, 'invalid' => 'invalid'];
        $accessor = new ArrayAccessor($data);

        self::assertSame(123, $accessor->nullableInt('key'));
        self::assertNull($accessor->nullableInt('missing'));

        $this->expectException(TypeError::class);
        $accessor->nullableInt('invalid');
    }

    public function testAsFloat(): void
    {
        $data = [
            'float' => 7.89,
            'int' => 123,
            'invalid' => [],
            'string_float' => '3.14',
        ];
        $accessor = new ArrayAccessor($data);

        self::assertSame(7.89, $accessor->asFloat('float'));
        self::assertSame(123.0, $accessor->asFloat('int'));
        self::assertSame(3.14, $accessor->asFloat('string_float'));
        self::assertSame(9.99, $accessor->asFloat('missing', 9.99));
        self::assertSame(0.0, $accessor->asFloat('missing'));

        $this->expectException(TypeError::class);
        $accessor->asFloat('invalid');
    }

    public function testFloat(): void
    {
        $data = ['key' => 3.14];
        $accessor = new ArrayAccessor($data);

        self::assertSame(3.14, $accessor->float('key'));

        $this->expectException(OutOfBoundsException::class);
        $accessor->float('missing');
    }

    public function testNullableFloat(): void
    {
        $data = ['key' => 3.14, 'invalid' => 'invalid'];
        $accessor = new ArrayAccessor($data);

        self::assertSame(3.14, $accessor->nullableFloat('key'));
        self::assertNull($accessor->nullableFloat('missing'));

        $this->expectException(TypeError::class);
        $accessor->nullableFloat('invalid');
    }

    public function testAsString(): void
    {
        $data = [
            'bool' => true,
            'float' => 7.89,
            'int' => 123,
            'invalid' => [],
            'string' => 'abc',
        ];
        $accessor = new ArrayAccessor($data);

        self::assertSame('abc', $accessor->asString('string'));
        self::assertSame('123', $accessor->asString('int'));
        self::assertSame('7.89', $accessor->asString('float'));
        self::assertSame('1', $accessor->asString('bool'));
        self::assertSame('default', $accessor->asString('missing', 'default'));
        self::assertSame('', $accessor->asString('missing'));

        $this->expectException(TypeError::class);
        $accessor->asString('invalid');
    }

    public function testString(): void
    {
        $data = ['key' => 'abc'];
        $accessor = new ArrayAccessor($data);

        self::assertSame('abc', $accessor->string('key'));

        $this->expectException(OutOfBoundsException::class);
        $accessor->string('missing');
    }

    public function testNullableString(): void
    {
        $data = ['key' => 'abc', 'invalid' => 123];
        $accessor = new ArrayAccessor($data);

        self::assertSame('abc', $accessor->nullableString('key'));
        self::assertNull($accessor->nullableString('missing'));

        $this->expectException(TypeError::class);
        $accessor->nullableString('invalid');
    }

    public function testCallable(): void
    {
        $fn = static fn () => null;
        $data = ['key' => $fn];
        $accessor = new ArrayAccessor($data);

        self::assertSame($fn, $accessor->callable('key'));

        $this->expectException(OutOfBoundsException::class);
        $accessor->callable('missing');
    }

    public function testNullableCallable(): void
    {
        $fn = static fn () => null;
        $data = ['key' => $fn, 'invalid' => 123];
        $accessor = new ArrayAccessor($data);

        self::assertSame($fn, $accessor->nullableCallable('key'));
        self::assertNull($accessor->nullableCallable('missing'));

        $this->expectException(TypeError::class);
        $accessor->nullableCallable('invalid');
    }

    public function testAccessor(): void
    {
        $data = [
            'user' => [
                'age' => 30,
                'name' => 'John',
            ],
        ];
        $accessor = new ArrayAccessor($data);

        $userAccessor = $accessor->accessor('user');
        self::assertSame('John', $userAccessor->string('name'));
        self::assertSame(30, $userAccessor->int('age'));

        $this->expectException(OutOfBoundsException::class);
        $accessor->accessor('missing');
    }

    public function testAccessorWithInvalidType(): void
    {
        $data = ['key' => 'not an array'];
        $accessor = new ArrayAccessor($data);

        $this->expectException(TypeError::class);
        $accessor->accessor('key');
    }

    public function testAccessorWithArrayAccessorValue(): void
    {
        $innerAccessor = new ArrayAccessor(['name' => 'Jane']);
        $data = ['user' => $innerAccessor];
        $accessor = new ArrayAccessor($data);

        $result = $accessor->accessor('user');
        self::assertSame($innerAccessor, $result);
        self::assertSame('Jane', $result->string('name'));
    }

    public function testAccessorWithDeepNesting(): void
    {
        $data = [
            'company' => [
                'department' => [
                    'team' => [
                        'lead' => 'Alice',
                        'members' => 5,
                    ],
                ],
            ],
        ];
        $accessor = new ArrayAccessor($data);

        $lead = $accessor->accessor('company')
            ->accessor('department')
            ->accessor('team')
            ->string('lead');

        self::assertSame('Alice', $lead);

        $members = $accessor->accessor('company')
            ->accessor('department')
            ->accessor('team')
            ->int('members');

        self::assertSame(5, $members);
    }

    public function testObject(): void
    {
        $obj = new \stdClass();
        $obj->name = 'test';
        $data = ['key' => $obj];
        $accessor = new ArrayAccessor($data);

        self::assertSame($obj, $accessor->object('key', \stdClass::class));

        $this->expectException(OutOfBoundsException::class);
        $accessor->object('missing', \stdClass::class);
    }

    public function testNullableObject(): void
    {
        $obj = new \stdClass();
        $data = ['key' => $obj, 'invalid' => 123];
        $accessor = new ArrayAccessor($data);

        self::assertSame($obj, $accessor->nullableObject('key', \stdClass::class));
        self::assertNull($accessor->nullableObject('missing', \stdClass::class));

        $this->expectException(TypeError::class);
        $accessor->nullableObject('invalid', \stdClass::class);
    }

    public function testIntList(): void
    {
        $data = ['numbers' => [1, 2, 3, 4, 5]];
        $accessor = new ArrayAccessor($data);

        $numbers = $accessor->intList('numbers');
        self::assertSame([1, 2, 3, 4, 5], $numbers);

        // Missing key returns empty array
        self::assertSame([], $accessor->intList('missing'));
    }

    public function testIntListWithInvalidType(): void
    {
        $data = ['key' => 'not an array'];
        $accessor = new ArrayAccessor($data);

        $this->expectException(TypeError::class);
        $accessor->intList('key');
    }

    public function testStringList(): void
    {
        $data = ['tags' => ['php', 'strong', 'types']];
        $accessor = new ArrayAccessor($data);

        $tags = $accessor->stringList('tags');
        self::assertSame(['php', 'strong', 'types'], $tags);

        // Missing key returns empty array
        self::assertSame([], $accessor->stringList('missing'));
    }

    public function testStringListWithInvalidType(): void
    {
        $data = ['key' => 123];
        $accessor = new ArrayAccessor($data);

        $this->expectException(TypeError::class);
        $accessor->stringList('key');
    }

    public function testObjectList(): void
    {
        $obj1 = new \stdClass();
        $obj1->id = 1;
        $obj2 = new \stdClass();
        $obj2->id = 2;
        $data = ['objects' => [$obj1, $obj2]];
        $accessor = new ArrayAccessor($data);

        $objects = $accessor->objectList('objects', \stdClass::class);
        self::assertSame([$obj1, $obj2], $objects);

        // Missing key returns empty array
        self::assertSame([], $accessor->objectList('missing', \stdClass::class));
    }

    public function testObjectListWithInvalidType(): void
    {
        $data = ['key' => 'not an array'];
        $accessor = new ArrayAccessor($data);

        $this->expectException(TypeError::class);
        $accessor->objectList('key', \stdClass::class);
    }
}
