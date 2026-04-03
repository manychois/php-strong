<?php

declare(strict_types=1);

namespace Manychois\PhpStrongTests\Internal;

use ArrayObject;
use DateTime;
use Manychois\PhpStrong\ArrayReaderInterface as IArrayReader;
use Manychois\PhpStrong\Collections\MapInterface as IMap;
use Manychois\PhpStrong\Internal\AbstractArrayReader;
use OutOfBoundsException;
use Override;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use stdClass;
use Stringable;
use Traversable;
use UnexpectedValueException;

/**
 * Unit tests for {@see AbstractArrayReader} via {@see TestAbstractArrayReader}.
 */
final class AbstractArrayReaderTest extends TestCase
{
    #[Test]
    public function array_returns_the_nested_array(): void
    {
        $reader = new TestAbstractArrayReader(['a' => ['b' => [1, 2]]]);
        self::assertSame([1, 2], $reader->array('a.b'));
    }

    #[Test]
    public function array_throws_when_path_missing(): void
    {
        $reader = new TestAbstractArrayReader([]);
        $this->expectException(OutOfBoundsException::class);
        $reader->array('x');
    }

    #[Test]
    public function array_throws_UnexpectedValueException_when_not_array(): void
    {
        $reader = new TestAbstractArrayReader(['a' => 'text']);
        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('The path "a" is not an array');
        $reader->array('a');
    }

    #[Test]
    public function arrayOrNull_returns_nested_array_or_null(): void
    {
        $reader = new TestAbstractArrayReader(['a' => ['b' => [3]], 'x' => 1]);
        self::assertSame([3], $reader->arrayOrNull('a.b'));
        self::assertNull($reader->arrayOrNull('missing'));
        self::assertNull($reader->arrayOrNull('x'));
    }

    #[Test]
    public function asBool_coerces_and_uses_default(): void
    {
        $reader = new TestAbstractArrayReader([
            't' => true,
            'f' => false,
            'on' => 'on',
            'off' => 'off',
            'bad_bool' => 'n/a',
        ]);
        self::assertTrue($reader->asBool('t'));
        self::assertFalse($reader->asBool('f'));
        self::assertTrue($reader->asBool('on'));
        self::assertFalse($reader->asBool('off'));
        self::assertTrue($reader->asBool('bad_bool', true));
        self::assertTrue($reader->asBool('truly_missing', true));
    }

    #[Test]
    public function asFloat_coerces_and_uses_default(): void
    {
        $reader = new TestAbstractArrayReader(['x' => 1.5, 'bad' => 'nope']);
        self::assertSame(1.5, $reader->asFloat('x'));
        self::assertSame(0.0, $reader->asFloat('missing'));
        self::assertSame(9.0, $reader->asFloat('bad', 9.0));
    }

    #[Test]
    public function asInt_coerces_and_uses_default(): void
    {
        $reader = new TestAbstractArrayReader(['n' => 7, 'bad' => 'x']);
        self::assertSame(7, $reader->asInt('n'));
        self::assertSame(0, $reader->asInt('missing'));
        self::assertSame(3, $reader->asInt('bad', 3));
    }

    #[Test]
    public function asString_coerces_scalar_stringable_and_uses_default(): void
    {
        $stringable = new class () implements Stringable {
            #[Override]
            public function __toString(): string
            {
                return 's';
            }
        };
        $reader = new TestAbstractArrayReader([
            'str' => 'hello',
            'int' => 42,
            'obj' => $stringable,
            'nil' => null,
            'bad' => ['a'],
        ]);
        self::assertSame('hello', $reader->asString('str'));
        self::assertSame('42', $reader->asString('int'));
        self::assertSame('s', $reader->asString('obj'));
        self::assertSame('nil-default', $reader->asString('nil', 'nil-default'));
        self::assertSame('', $reader->asString('bad'));
        self::assertSame('d', $reader->asString('missing', 'd'));
    }

    #[Test]
    public function bool_and_boolOrNull_are_strict_bool(): void
    {
        $reader = new TestAbstractArrayReader(['b' => true, 's' => 'true']);
        self::assertTrue($reader->bool('b'));
        self::assertTrue($reader->boolOrNull('b'));
        self::assertNull($reader->boolOrNull('s'));
        self::assertNull($reader->boolOrNull('missing'));
        $this->expectException(UnexpectedValueException::class);
        $reader->bool('s');
    }

    #[Test]
    public function callable_returns_callable_and_OrNull_handles_missing(): void
    {
        $fn = static fn (): int => 1;
        $reader = new TestAbstractArrayReader(['c' => $fn]);
        self::assertSame($fn, $reader->callable('c'));
        self::assertSame($fn, $reader->callableOrNull('c'));
        self::assertNull($reader->callableOrNull('missing'));
        $this->expectException(OutOfBoundsException::class);
        $reader->callable('missing');
    }

    #[Test]
    public function callable_throws_when_value_is_not_callable(): void
    {
        $reader = new TestAbstractArrayReader(['c' => 'not-callable']);
        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('The path "c" is not a callable');
        $reader->callable('c');
    }

    #[Test]
    public function callableOrNull_returns_null_when_value_is_not_callable(): void
    {
        $reader = new TestAbstractArrayReader(['c' => 1]);
        self::assertNull($reader->callableOrNull('c'));
    }

    #[Test]
    public function float_int_string_object_instanceOf_match_strict_expectations(): void
    {
        $dt = new DateTime('@0');
        $reader = new TestAbstractArrayReader([
            'f' => 1.0,
            'i' => 1,
            's' => 'x',
            'o' => $dt,
        ]);
        self::assertSame(1.0, $reader->float('f'));
        self::assertSame(1.0, $reader->floatOrNull('f'));
        self::assertNull($reader->floatOrNull('i'));
        self::assertSame(1, $reader->int('i'));
        self::assertSame(1, $reader->intOrNull('i'));
        self::assertNull($reader->intOrNull('f'));
        self::assertSame('x', $reader->string('s'));
        self::assertSame($dt, $reader->object('o'));
        self::assertSame($dt, $reader->instanceOf('o', DateTime::class));
        self::assertNull($reader->instanceOfOrNull('f', DateTime::class));
        $this->expectException(UnexpectedValueException::class);
        $reader->float('i');
    }

    #[Test]
    public function int_throws_when_value_is_not_integer(): void
    {
        $reader = new TestAbstractArrayReader(['f' => 1.0]);
        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('The path "f" is not an integer');
        $reader->int('f');
    }

    #[Test]
    public function instanceOf_throws_when_not_object_or_wrong_class(): void
    {
        $reader = new TestAbstractArrayReader(['v' => 1]);
        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('The path "v" is not an object');
        $reader->instanceOf('v', stdClass::class);
    }

    #[Test]
    public function instanceOf_throws_when_object_is_wrong_class(): void
    {
        $reader = new TestAbstractArrayReader(['o' => new stdClass()]);
        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('The path "o" is not an instance of ' . DateTime::class);
        $reader->instanceOf('o', DateTime::class);
    }

    #[Test]
    public function instanceOfOrNull_returns_object_when_instance_matches(): void
    {
        $dt = new DateTime('@0');
        $reader = new TestAbstractArrayReader(['o' => $dt]);
        self::assertSame($dt, $reader->instanceOfOrNull('o', DateTime::class));
        self::assertNull($reader->instanceOfOrNull('o', stdClass::class));
    }

    #[Test]
    public function get_empty_path_returns_root(): void
    {
        $root = ['k' => 1];
        $reader = new TestAbstractArrayReader($root);
        self::assertSame($root, $reader->get(''));
    }

    #[Test]
    public function get_skips_empty_dot_segments(): void
    {
        $reader = new TestAbstractArrayReader(['a' => ['b' => 2]]);
        self::assertSame(2, $reader->get('a..b'));
    }

    #[Test]
    public function get_resolves_ArrayAccess_and_object_properties(): void
    {
        $obj = new stdClass();
        $obj->nested = ['q' => 9];
        $reader = new TestAbstractArrayReader([
            'ao' => new ArrayObject(['z' => 3]),
            'ob' => $obj,
        ]);
        self::assertSame(3, $reader->get('ao.z'));
        self::assertSame(9, $reader->get('ob.nested.q'));
    }

    #[Test]
    public function get_returns_missing_when_ArrayAccess_key_absent(): void
    {
        $reader = new TestAbstractArrayReader(['ao' => new ArrayObject(['z' => 3])]);
        $this->expectException(OutOfBoundsException::class);
        $reader->get('ao.absent');
    }

    #[Test]
    public function get_returns_missing_when_object_property_absent(): void
    {
        $obj = new stdClass();
        $reader = new TestAbstractArrayReader(['ob' => $obj]);
        $this->expectException(OutOfBoundsException::class);
        $reader->get('ob.noSuchProp');
    }

    #[Test]
    public function get_throws_when_scalar_blocks_deeper_path(): void
    {
        $reader = new TestAbstractArrayReader(['a' => ['b' => 1]]);
        $this->expectException(OutOfBoundsException::class);
        $reader->get('a.b.c');
    }

    #[Test]
    public function get_throws_OutOfBoundsException_with_message(): void
    {
        $reader = new TestAbstractArrayReader([]);
        $this->expectException(OutOfBoundsException::class);
        $this->expectExceptionMessage('Path "nope" not found');
        $reader->get('nope');
    }

    #[Test]
    public function ROOT_KEY_as_first_segment_is_ignored(): void
    {
        $reader = new TestAbstractArrayReader(['x' => 5]);
        self::assertSame(5, $reader->get(IArrayReader::ROOT_KEY . '.x'));
        self::assertSame(['x' => 5], $reader->get(IArrayReader::ROOT_KEY));
    }

    #[Test]
    public function getOrNull_returns_null_for_missing_path(): void
    {
        $reader = new TestAbstractArrayReader([]);
        self::assertNull($reader->getOrNull('a'));
    }

    #[Test]
    public function has_is_false_for_missing_null_or_unresolved(): void
    {
        $reader = new TestAbstractArrayReader(['a' => 1, 'b' => null]);
        self::assertTrue($reader->has('a'));
        self::assertFalse($reader->has('b'));
        self::assertFalse($reader->has('missing'));
    }

    #[Test]
    public function strict_type_mismatch_uses_root_message_when_path_empty(): void
    {
        $reader = new TestAbstractArrayReader(['not' => 'string']);
        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('The root is not a string');
        $reader->string('');
    }

    #[Test]
    public function object_throws_when_value_is_not_object(): void
    {
        $reader = new TestAbstractArrayReader(['n' => 1]);
        try {
            $reader->object('n');
            self::fail('Expected exception');
        } catch (UnexpectedValueException $e) {
            self::assertStringContainsString('is not an object', $e->getMessage());
        }
    }

    #[Test]
    public function objectOrNull_returns_object_or_null(): void
    {
        $dt = new DateTime('@0');
        $reader = new TestAbstractArrayReader(['o' => $dt, 'n' => 1]);
        self::assertSame($dt, $reader->objectOrNull('o'));
        self::assertNull($reader->objectOrNull('n'));
        self::assertNull($reader->objectOrNull('missing'));
    }

    #[Test]
    public function stringOrNull_returns_string_or_null(): void
    {
        $reader = new TestAbstractArrayReader(['s' => 'ok', 'n' => 1]);
        self::assertSame('ok', $reader->stringOrNull('s'));
        self::assertNull($reader->stringOrNull('n'));
        self::assertNull($reader->stringOrNull('missing'));
    }
}

/**
 * Minimal {@see AbstractArrayReader} for tests; mirrors {@see \Manychois\PhpStrong\ArrayReader} `at` / `with`.
 */
final class TestAbstractArrayReader extends AbstractArrayReader
{
    /**
     * @var array<string, mixed>|object
     */
    private readonly array|object $root;

    /**
     * @param array<string, mixed>|object $root
     */
    public function __construct(array|object $root)
    {
        parent::__construct();
        $this->root = $root;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function at(string $path): IArrayReader
    {
        $value = $this->get($path);
        if (is_array($value) || is_object($value)) {
            // @phpstan-ignore argument.type
            return new self($value);
        }
        throw $this->createMismatchException($path, 'array or object', $value);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    protected function getRoot(): array|object
    {
        return $this->root;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function with(array|IMap $overrides): IArrayReader
    {
        if ($overrides instanceof IMap) {
            $overrides = $overrides->asArray();
        }
        if ($this->root instanceof Traversable) {
            /** @var array<string, mixed> $newRoot */
            $newRoot = [];
            foreach ($this->root as $key => $value) {
                if (is_int($key) || is_string($key)) {
                    $newRoot[$key] = $value;
                }
            }
            foreach ($overrides as $key => $value) {
                $newRoot[$key] = $value;
            }
            // @phpstan-ignore argument.type
            return new self($newRoot);
        }

        throw $this->createMismatchException('', 'array or Traversable object', $this->root);
    }
}
