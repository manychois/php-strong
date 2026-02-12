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

        self::assertTrue($accessor->strictBool('key'));

        $this->expectException(OutOfBoundsException::class);
        $accessor->strictBool('missing');
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

        self::assertSame(123, $accessor->strictInt('key'));

        $this->expectException(OutOfBoundsException::class);
        $accessor->strictInt('missing');
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

        self::assertSame(3.14, $accessor->strictFloat('key'));

        $this->expectException(OutOfBoundsException::class);
        $accessor->strictFloat('missing');
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

        self::assertSame('abc', $accessor->strictString('key'));

        $this->expectException(OutOfBoundsException::class);
        $accessor->strictString('missing');
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
        self::assertSame('John', $userAccessor->strictString('name'));
        self::assertSame(30, $userAccessor->strictInt('age'));

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
        $innerData = ['name' => 'Jane'];
        $innerAccessor = new ArrayAccessor($innerData);
        $data = ['user' => $innerAccessor];
        $accessor = new ArrayAccessor($data);

        $result = $accessor->accessor('user');
        self::assertSame($innerAccessor, $result);
        self::assertSame('Jane', $result->strictString('name'));
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
            ->strictString('lead');

        self::assertSame('Alice', $lead);

        $members = $accessor->accessor('company')
            ->accessor('department')
            ->accessor('team')
            ->strictInt('members');

        self::assertSame(5, $members);
    }

    public function testObject(): void
    {
        $obj = new \stdClass();
        $obj->name = 'test';
        $data = ['key' => $obj];
        $accessor = new ArrayAccessor($data);

        self::assertSame($obj, $accessor->strictObject('key', \stdClass::class));

        $this->expectException(OutOfBoundsException::class);
        $accessor->strictObject('missing', \stdClass::class);
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

    public function testSet(): void
    {
        $data = ['existing' => 'old value'];
        $accessor = new ArrayAccessor($data);

        // Test setting a new key
        $accessor->set('new_key', 'new value');
        self::assertSame('new value', $accessor->strictString('new_key'));

        // Test overwriting an existing key
        $accessor->set('existing', 'updated value');
        self::assertSame('updated value', $accessor->strictString('existing'));

        // Test setting different types
        $accessor->set('number', 42);
        self::assertSame(42, $accessor->strictInt('number'));

        $accessor->set('flag', true);
        self::assertTrue($accessor->strictBool('flag'));

        $accessor->set('price', 19.99);
        self::assertSame(19.99, $accessor->strictFloat('price'));

        // Test that the source array is also updated (reference behavior)
        self::assertSame('new value', $data['new_key']);
        self::assertSame('updated value', $data['existing']);
        self::assertSame(42, $data['number']);
    }

    public function testSetWithArray(): void
    {
        $data = [];
        $accessor = new ArrayAccessor($data);

        // Test setting an array value
        $accessor->set('user', ['name' => 'John', 'age' => 30]);

        $userAccessor = $accessor->accessor('user');
        self::assertSame('John', $userAccessor->strictString('name'));
        self::assertSame(30, $userAccessor->strictInt('age'));

        // Verify source array is updated
        self::assertSame(['name' => 'John', 'age' => 30], $data['user']);
    }

    public function testSetWithObject(): void
    {
        $data = [];
        $accessor = new ArrayAccessor($data);

        $obj = new \stdClass();
        $obj->id = 123;

        $accessor->set('object', $obj);
        self::assertSame($obj, $accessor->strictObject('object', \stdClass::class));
        self::assertSame($obj, $data['object']);
    }

    public function testSetWithNull(): void
    {
        $data = ['key' => 'value'];
        $accessor = new ArrayAccessor($data);

        $accessor->set('key', null);
        self::assertNull($data['key']);
        self::assertTrue(\array_key_exists('key', $data));

        // Verify the key exists but value is null
        $this->expectException(TypeError::class);
        $accessor->strictString('key');
    }

    public function testHas(): void
    {
        $data = ['existing' => 'value', 'null_value' => null];
        $accessor = new ArrayAccessor($data);

        // Test with existing key
        self::assertTrue($accessor->has('existing'));

        // Test with missing key
        self::assertFalse($accessor->has('missing'));

        // Test with null value (key exists but value is null)
        self::assertTrue($accessor->has('null_value'));

        // Test after adding a key via set()
        $accessor->set('new_key', 'new value');
        self::assertTrue($accessor->has('new_key'));

        // Test after deleting a key
        $accessor->delete('existing');
        self::assertFalse($accessor->has('existing'));
    }

    public function testDelete(): void
    {
        $data = ['key1' => 'value1', 'key2' => 'value2', 'key3' => 'value3'];
        $accessor = new ArrayAccessor($data);

        // Test deleting an existing key
        $accessor->delete('key2');
        self::assertFalse($accessor->has('key2'));
        self::assertFalse(\array_key_exists('key2', $data));

        // Other keys should still exist
        self::assertTrue($accessor->has('key1'));
        self::assertTrue($accessor->has('key3'));

        // Test deleting a non-existent key (should not throw)
        $accessor->delete('non_existent');
        self::assertFalse($accessor->has('non_existent'));

        // Test deleting all remaining keys
        $accessor->delete('key1');
        $accessor->delete('key3');
        self::assertFalse($accessor->has('key1'));
        self::assertFalse($accessor->has('key3'));
        self::assertEmpty($data);
    }

    public function testDeleteVerifiesSourceArrayUpdate(): void
    {
        $data = ['key' => 'value'];
        $accessor = new ArrayAccessor($data);

        self::assertTrue(isset($data['key']));

        $accessor->delete('key');

        // Verify the key is deleted from the source array
        self::assertFalse(isset($data['key']));
        self::assertArrayNotHasKey('key', $data);
    }

    public function testHasAndDeleteIntegration(): void
    {
        $data = [];
        $accessor = new ArrayAccessor($data);

        // Add keys
        $accessor->set('name', 'Alice');
        $accessor->set('age', 30);
        $accessor->set('active', true);

        // Verify all exist
        self::assertTrue($accessor->has('name'));
        self::assertTrue($accessor->has('age'));
        self::assertTrue($accessor->has('active'));

        // Delete one
        $accessor->delete('age');

        // Verify only that one is gone
        self::assertTrue($accessor->has('name'));
        self::assertFalse($accessor->has('age'));
        self::assertTrue($accessor->has('active'));

        // Verify we can still access remaining values
        self::assertSame('Alice', $accessor->strictString('name'));
        self::assertTrue($accessor->strictBool('active'));

        // Verify age throws OutOfBoundsException
        $this->expectException(OutOfBoundsException::class);
        $accessor->strictInt('age');
    }
}
