<?php

declare(strict_types=1);

namespace Manychois\PhpStrongTests;

use ArrayObject;
use Manychois\PhpStrong\ArrayReader;
use Manychois\PhpStrong\Collections\StringMap;
use OutOfBoundsException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use stdClass;
use UnexpectedValueException;

/**
 * Unit tests for {@see ArrayReader}.
 */
final class ArrayReaderTest extends TestCase
{
    #[Test]
    public function at_returns_ArrayReader_for_nested_array_or_object(): void
    {
        $reader = new ArrayReader(['a' => ['b' => 2]]);
        $inner = $reader->at('a');
        self::assertInstanceOf(ArrayReader::class, $inner);
        self::assertSame(2, $inner->get('b'));
    }

    #[Test]
    public function at_throws_when_value_is_scalar(): void
    {
        $reader = new ArrayReader(['x' => 1]);
        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('The path "x" is not an array or object');
        $reader->at('x');
    }

    #[Test]
    public function constructor_accepts_object_root(): void
    {
        $obj = new stdClass();
        $obj->name = 'n';
        $reader = new ArrayReader($obj);
        self::assertSame('n', $reader->get('name'));
    }

    #[Test]
    public function with_plain_array_root_throws(): void
    {
        $reader = new ArrayReader(['k' => 1]);
        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('The root is not an array or Traversable object');
        $reader->with(['k' => 2]);
    }

    #[Test]
    public function with_traversable_builds_merged_array_reader(): void
    {
        $root = new ArrayObject(['a' => 1], ArrayObject::ARRAY_AS_PROPS);
        $reader = new ArrayReader($root);
        $next = $reader->with(['b' => 2]);
        self::assertInstanceOf(ArrayReader::class, $next);
        self::assertSame(1, $next->get('a'));
        self::assertSame(2, $next->get('b'));
    }

    #[Test]
    public function with_accepts_StringMap(): void
    {
        $root = new ArrayObject(['x' => 0]);
        $reader = new ArrayReader($root);
        $map = new StringMap([]);
        $map->add('y', 5);
        $next = $reader->with($map);
        self::assertSame(5, $next->get('y'));
    }

    #[Test]
    public function with_stdClass_root_throws(): void
    {
        $reader = new ArrayReader(new stdClass());
        $this->expectException(UnexpectedValueException::class);
        $reader->with(['a' => 1]);
    }

    #[Test]
    public function inherited_read_methods_work_like_AbstractArrayReader(): void
    {
        $reader = new ArrayReader(['p' => ['q' => 99]]);
        self::assertSame(99, $reader->get('p.q'));
        $this->expectException(OutOfBoundsException::class);
        $reader->get('p.missing');
    }
}
