<?php

declare(strict_types=1);

namespace Manychois\PhpStrongTests\Collections;

use ArrayAccess;
use BadMethodCallException;
use DateTime;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use Manychois\PhpStrong\Collections\DataReader;
use OutOfBoundsException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class DataReaderTest extends TestCase
{
    #[Test]
    public function constructorRejectsNonStringKeys(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new DataReader([0 => 'a']);
    }

    #[Test]
    public function hasReportsWhetherTheKeyExists(): void
    {
        $reader = new DataReader(['a' => 1, 'b' => null]);

        static::assertTrue($reader->has('a'));
        static::assertTrue($reader->has('b'));
        static::assertFalse($reader->has('c'));
    }

    #[Test]
    public function keysReturnsTheKeysInSourceOrder(): void
    {
        static::assertSame(['b', 'a'], (new DataReader(['b' => 1, 'a' => 2]))->keys());
    }

    #[Test]
    public function entriesReturnsTheUnderlyingArray(): void
    {
        static::assertSame(['a' => 1], (new DataReader(['a' => 1]))->entries());
    }

    #[Test]
    public function countReturnsTheNumberOfEntries(): void
    {
        static::assertCount(2, new DataReader(['a' => 1, 'b' => 2]));
    }

    #[Test]
    public function constructorAcceptsAnObject(): void
    {
        $reader = new DataReader(new Sample());

        static::assertSame('x', $reader->string('name'));
    }

    #[Test]
    public function keysCountAndEntriesCoverThePublicPropertiesOfAnObject(): void
    {
        $reader = new DataReader(new Sample());

        static::assertSame(['name', 'nested'], $reader->keys());
        static::assertCount(2, $reader);
        static::assertSame(['name' => 'x', 'nested' => ['b' => 'y']], $reader->entries());
    }

    #[Test]
    public function aNumericPropertyNameIsRejectedByTheEntrySetMethods(): void
    {
        $reader = new DataReader((object) ['0' => 'x']);

        $this->expectException(InvalidArgumentException::class);

        $reader->keys();
    }

    #[Test]
    public function dotNotationDescendsIntoAnObjectProperty(): void
    {
        $reader = new DataReader(['a' => new Sample()]);

        static::assertSame('x', $reader->string('a.name'));
        static::assertSame('y', $reader->string('a.nested.b'));
    }

    #[Test]
    public function nonPublicPropertiesAreAbsent(): void
    {
        $reader = new DataReader(new Sample());

        static::assertFalse($reader->has('hidden'));
    }

    #[Test]
    public function anUninitializedPropertyIsAbsent(): void
    {
        $reader = new DataReader(new Sample());

        static::assertFalse($reader->has('unset'));
    }

    #[Test]
    public function anArrayAccessOffsetIsPreferredOverAProperty(): void
    {
        $reader = new DataReader(['a' => new Offsets(['b' => 'x'])]);

        static::assertSame('x', $reader->string('a.b'));
        static::assertFalse($reader->has('a.c'));
    }

    #[Test]
    public function aMagicPropertyIsResolved(): void
    {
        $reader = new DataReader(new Magic());

        static::assertSame('x', $reader->string('magic'));
        static::assertFalse($reader->has('other'));
    }

    #[Test]
    public function readerWrapsANestedObject(): void
    {
        $reader = new DataReader(['a' => new Sample()]);

        static::assertSame('x', $reader->reader('a')->string('name'));
        static::assertSame('x', $reader->nullReader('a')?->string('name'));
    }

    #[Test]
    public function anObjectWithoutRealPropertiesIsALeaf(): void
    {
        $reader = new DataReader(['a' => new DateTimeImmutable('2026-08-24')]);

        static::assertFalse($reader->has('a.date'));
    }

    #[Test]
    public function dotNotationReachesANestedValue(): void
    {
        $reader = new DataReader(['a' => ['b' => ['c' => 'x']]]);

        static::assertSame('x', $reader->string('a.b.c'));
    }

    #[Test]
    public function dotNotationIndexesASequentialArray(): void
    {
        $reader = new DataReader(['a' => [['b' => 'x'], ['b' => 'y']]]);

        static::assertSame('y', $reader->string('a.1.b'));
    }

    #[Test]
    public function anExactKeyWinsOverDotNotation(): void
    {
        $reader = new DataReader(['a.b' => 'x', 'a' => ['b' => 'y']]);

        static::assertSame('x', $reader->string('a.b'));
    }

    #[Test]
    public function dotNotationThrowsWhenASegmentIsMissing(): void
    {
        $this->expectException(OutOfBoundsException::class);

        (new DataReader(['a' => ['b' => 'x']]))->string('a.c');
    }

    #[Test]
    public function dotNotationThrowsWhenASegmentIsNotAnArray(): void
    {
        $this->expectException(OutOfBoundsException::class);

        (new DataReader(['a' => 'x']))->string('a.b');
    }

    #[Test]
    public function hasFollowsDotNotation(): void
    {
        $reader = new DataReader(['a' => ['b' => null]]);

        static::assertTrue($reader->has('a.b'));
        static::assertFalse($reader->has('a.c'));
    }

    #[Test]
    public function nullGetReturnsTheValueOrNullWhenTheKeyIsMissing(): void
    {
        $reader = new DataReader(['a' => ['b' => 'x']]);

        static::assertSame('x', $reader->nullGet('a.b'));
        static::assertNull($reader->nullGet('a.c'));
        static::assertNull($reader->nullGet('z'));
    }

    #[Test]
    public function nullVariantsFollowDotNotation(): void
    {
        $reader = new DataReader(['a' => ['b' => 'x']]);

        static::assertSame('x', $reader->nullString('a.b'));
        static::assertNull($reader->nullString('a.c'));
    }

    #[Test]
    public function stringReturnsTheValueWhenItIsAString(): void
    {
        static::assertSame('x', (new DataReader(['a' => 'x']))->string('a'));
    }

    #[Test]
    public function stringThrowsWhenTheKeyIsMissing(): void
    {
        $this->expectException(OutOfBoundsException::class);

        (new DataReader([]))->string('a');
    }

    #[Test]
    public function stringThrowsWhenTheValueIsNotAString(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new DataReader(['a' => 1]))->string('a');
    }

    #[Test]
    public function asStringConvertsScalarsAndStringables(): void
    {
        $reader = new DataReader([
            'a' => 'x',
            'b' => 1,
            'c' => 1.5,
            'd' => true,
            'e' => false,
            'f' => new class {
                public function __toString(): string
                {
                    return 'z';
                }
            },
            'g' => null,
        ]);

        static::assertSame('x', $reader->asString('a'));
        static::assertSame('1', $reader->asString('b'));
        static::assertSame('1.5', $reader->asString('c'));
        static::assertSame('true', $reader->asString('d'));
        static::assertSame('false', $reader->asString('e'));
        static::assertSame('z', $reader->asString('f'));
        static::assertSame('', $reader->asString('g'));
    }

    #[Test]
    public function asStringThrowsWhenTheValueIsNotConvertible(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new DataReader(['a' => [1]]))->asString('a');
    }

    #[Test]
    public function nullStringReturnsNullWhenMissingOrMismatched(): void
    {
        $reader = new DataReader(['a' => 'x', 'b' => 1, 'c' => null]);

        static::assertSame('x', $reader->nullString('a'));
        static::assertNull($reader->nullString('b'));
        static::assertNull($reader->nullString('c'));
        static::assertNull($reader->nullString('d'));
    }

    #[Test]
    public function intReturnsTheValueWhenItIsAnInt(): void
    {
        static::assertSame(3, (new DataReader(['a' => 3]))->int('a'));
    }

    #[Test]
    public function intThrowsWhenTheValueIsNotAnInt(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new DataReader(['a' => '3']))->int('a');
    }

    #[Test]
    public function asIntConvertsIntegralValues(): void
    {
        $reader = new DataReader(['a' => 3, 'b' => '3', 'c' => 3.0, 'd' => true, 'e' => false]);

        static::assertSame(3, $reader->asInt('a'));
        static::assertSame(3, $reader->asInt('b'));
        static::assertSame(3, $reader->asInt('c'));
        static::assertSame(1, $reader->asInt('d'));
        static::assertSame(0, $reader->asInt('e'));
    }

    #[Test]
    public function asIntDiscardsTheFractionalPart(): void
    {
        $reader = new DataReader(['a' => 3.5, 'b' => -3.5, 'c' => '3.7']);

        static::assertSame(3, $reader->asInt('a'));
        static::assertSame(-3, $reader->asInt('b'));
        static::assertSame(3, $reader->asInt('c'));
    }

    #[Test]
    public function asIntThrowsWhenTheFloatIsNotFinite(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new DataReader(['a' => \INF]))->asInt('a');
    }

    #[Test]
    public function asIntThrowsWhenTheStringIsNotNumeric(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new DataReader(['a' => 'x']))->asInt('a');
    }

    #[Test]
    public function asIntThrowsWhenTheFloatIsOutOfIntRange(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new DataReader(['a' => 1.0e30]))->asInt('a');
    }

    #[Test]
    public function asIntThrowsWhenTheValueIsNotScalar(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new DataReader(['a' => []]))->asInt('a');
    }

    #[Test]
    public function asStringReadsAMissingKeyAsNull(): void
    {
        static::assertSame('', (new DataReader([]))->asString('a'));
        static::assertSame('d', (new DataReader([]))->asString('a', 'd'));
        static::assertSame('d', (new DataReader(['a' => null]))->asString('a', 'd'));
        static::assertSame('', (new DataReader(['a' => '']))->asString('a', 'd'));
        static::assertSame('', (new DataReader(['a' => ['b' => 1]]))->asString('a.c'));
    }

    #[Test]
    public function asTrimmedStringTrimsTheConvertedValue(): void
    {
        $reader = new DataReader(['a' => "  x y \n", 'b' => 12, 'c' => true]);

        static::assertSame('x y', $reader->asTrimmedString('a'));
        static::assertSame('12', $reader->asTrimmedString('b'));
        static::assertSame('true', $reader->asTrimmedString('c'));
    }

    #[Test]
    public function asTrimmedStringReturnsTheDefaultWhenTheResultIsEmpty(): void
    {
        $reader = new DataReader(['a' => "  \t", 'b' => null, 'c' => '']);

        static::assertSame('', $reader->asTrimmedString('a'));
        static::assertSame('d', $reader->asTrimmedString('a', 'd'));
        static::assertSame('d', $reader->asTrimmedString('b', 'd'));
        static::assertSame('d', $reader->asTrimmedString('c', 'd'));
        static::assertSame('d', $reader->asTrimmedString('missing', 'd'));
    }

    #[Test]
    public function asTrimmedStringThrowsWhenTheValueIsNotConvertible(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new DataReader(['a' => []]))->asTrimmedString('a', 'd');
    }

    #[Test]
    public function asVariantsReturnTheDefaultWhenTheValueIsNull(): void
    {
        $reader = new DataReader(['a' => null]);
        $date = new DateTimeImmutable('2024-01-01');

        static::assertFalse($reader->asBool('a'));
        static::assertTrue($reader->asBool('a', true));
        static::assertTrue($reader->asBool('missing', true));
        static::assertSame(0, $reader->asInt('a'));
        static::assertSame(7, $reader->asInt('a', 7));
        static::assertSame(0.0, $reader->asFloat('a'));
        static::assertSame(1.5, $reader->asFloat('missing', 1.5));
        static::assertSame($date, $reader->asDateTime('a', $date));
        static::assertSame($date, $reader->asDateTime('missing', $date));
    }

    #[Test]
    public function asDateTimeThrowsWhenTheValueIsNullAndNoDefaultIsGiven(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new DataReader(['a' => null]))->asDateTime('a');
    }

    #[Test]
    public function asVariantsThrowInvalidArgumentWhenTheKeyIsMissingAndNullIsNotConvertible(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Value of key "a" is expected to be convertible to a date and time, null given.');

        (new DataReader([]))->asDateTime('a');
    }

    #[Test]
    public function nullIntReturnsNullWhenMissingOrMismatched(): void
    {
        $reader = new DataReader(['a' => 3, 'b' => '3']);

        static::assertSame(3, $reader->nullInt('a'));
        static::assertNull($reader->nullInt('b'));
        static::assertNull($reader->nullInt('c'));
    }

    #[Test]
    public function floatReturnsTheValueWhenItIsAFloat(): void
    {
        static::assertSame(1.5, (new DataReader(['a' => 1.5]))->float('a'));
    }

    #[Test]
    public function floatAcceptsAnIntAndWidensIt(): void
    {
        static::assertSame(2.0, (new DataReader(['a' => 2]))->float('a'));
    }

    #[Test]
    public function floatThrowsWhenTheValueIsNotANumber(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new DataReader(['a' => '1.5']))->float('a');
    }

    #[Test]
    public function asFloatConvertsNumericValues(): void
    {
        $reader = new DataReader(['a' => 1.5, 'b' => 2, 'c' => '1.5', 'd' => true, 'e' => false]);

        static::assertSame(1.5, $reader->asFloat('a'));
        static::assertSame(2.0, $reader->asFloat('b'));
        static::assertSame(1.5, $reader->asFloat('c'));
        static::assertSame(1.0, $reader->asFloat('d'));
        static::assertSame(0.0, $reader->asFloat('e'));
    }

    #[Test]
    public function asFloatThrowsWhenTheStringIsNotNumeric(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new DataReader(['a' => 'x']))->asFloat('a');
    }

    #[Test]
    public function nullFloatReturnsNullWhenMissingOrMismatched(): void
    {
        $reader = new DataReader(['a' => 1.5, 'b' => 2, 'c' => '1.5']);

        static::assertSame(1.5, $reader->nullFloat('a'));
        static::assertSame(2.0, $reader->nullFloat('b'));
        static::assertNull($reader->nullFloat('c'));
        static::assertNull($reader->nullFloat('d'));
    }

    #[Test]
    public function falsyReportsWhetherTheValueIsFalsy(): void
    {
        $reader = new DataReader([
            'a' => null,
            'b' => false,
            'c' => 0,
            'd' => 0.0,
            'e' => '',
            'f' => '0',
            'g' => [],
        ]);

        foreach ($reader->keys() as $key) {
            static::assertTrue($reader->falsy($key), $key);
        }

        static::assertTrue($reader->falsy('missing'));
    }

    #[Test]
    public function falsyReportsFalseForATruthyValue(): void
    {
        $reader = new DataReader(['a' => 'x', 'b' => 1, 'c' => 0.1, 'd' => true, 'e' => [0], 'f' => new Sample()]);

        foreach ($reader->keys() as $key) {
            static::assertFalse($reader->falsy($key), $key);
        }
    }

    #[Test]
    public function falsyFollowsDotNotation(): void
    {
        $reader = new DataReader(['a' => ['b' => 0, 'c' => 1]]);

        static::assertTrue($reader->falsy('a.b'));
        static::assertFalse($reader->falsy('a.c'));
    }

    #[Test]
    public function boolReturnsTheValueWhenItIsABool(): void
    {
        static::assertTrue((new DataReader(['a' => true]))->bool('a'));
    }

    #[Test]
    public function boolThrowsWhenTheValueIsNotABool(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new DataReader(['a' => 1]))->bool('a');
    }

    #[Test]
    public function asBoolConvertsRecognisedValues(): void
    {
        $reader = new DataReader([
            'a' => true,
            'b' => 1,
            'c' => 0,
            'd' => 1.0,
            'e' => 'TRUE',
            'f' => 'no',
            'g' => 'On',
            'h' => '0',
        ]);

        static::assertTrue($reader->asBool('a'));
        static::assertTrue($reader->asBool('b'));
        static::assertFalse($reader->asBool('c'));
        static::assertTrue($reader->asBool('d'));
        static::assertTrue($reader->asBool('e'));
        static::assertFalse($reader->asBool('f'));
        static::assertTrue($reader->asBool('g'));
        static::assertFalse($reader->asBool('h'));
    }

    #[Test]
    public function asBoolAcceptsTheEmptyStringAndSurroundingWhitespace(): void
    {
        $reader = new DataReader(['a' => '', 'b' => ' yes ']);

        static::assertFalse($reader->asBool('a'));
        static::assertTrue($reader->asBool('b'));
    }

    #[Test]
    public function asBoolThrowsWhenTheValueIsNotRecognised(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new DataReader(['a' => 'maybe']))->asBool('a');
    }

    #[Test]
    public function asBoolThrowsWhenTheNumberIsNeitherZeroNorOne(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new DataReader(['a' => 2]))->asBool('a');
    }

    #[Test]
    public function asBoolThrowsWhenTheValueIsNotScalar(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new DataReader(['a' => []]))->asBool('a');
    }

    #[Test]
    public function nullBoolReturnsNullWhenMissingOrMismatched(): void
    {
        $reader = new DataReader(['a' => false, 'b' => 0]);

        static::assertFalse($reader->nullBool('a'));
        static::assertNull($reader->nullBool('b'));
        static::assertNull($reader->nullBool('c'));
    }

    #[Test]
    public function arrayReturnsTheValueWhenItIsAnArray(): void
    {
        static::assertSame([1, 2], (new DataReader(['a' => [1, 2]]))->array('a'));
    }

    #[Test]
    public function arrayThrowsWhenTheValueIsNotAnArray(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new DataReader(['a' => 'x']))->array('a');
    }

    #[Test]
    public function nullArrayReturnsNullWhenMissingOrMismatched(): void
    {
        $reader = new DataReader(['a' => [], 'b' => 'x']);

        static::assertSame([], $reader->nullArray('a'));
        static::assertNull($reader->nullArray('b'));
        static::assertNull($reader->nullArray('c'));
    }

    #[Test]
    public function readerWrapsANestedStringKeyedArray(): void
    {
        $reader = new DataReader(['a' => ['b' => 'x']]);

        static::assertSame('x', $reader->reader('a')->string('b'));
    }

    #[Test]
    public function readerThrowsWhenTheNestedArrayHasNonStringKeys(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new DataReader(['a' => ['x']]))->reader('a');
    }

    #[Test]
    public function readerThrowsWhenTheValueIsNotAnArray(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new DataReader(['a' => 'x']))->reader('a');
    }

    #[Test]
    public function nullReaderReturnsNullWhenMissingOrMismatched(): void
    {
        $reader = new DataReader(['a' => ['b' => 'x'], 'b' => 'x', 'c' => ['x']]);

        static::assertSame('x', $reader->nullReader('a')?->string('b'));
        static::assertNull($reader->nullReader('b'));
        static::assertNull($reader->nullReader('c'));
        static::assertNull($reader->nullReader('d'));
    }

    #[Test]
    public function objectReturnsTheValueWhenItIsAnInstanceOfTheGivenClass(): void
    {
        $date = new DateTimeImmutable('2026-08-24');
        $reader = new DataReader(['a' => $date]);

        static::assertSame($date, $reader->object('a', DateTimeImmutable::class));
    }

    #[Test]
    public function objectThrowsWhenTheValueIsNotAnInstanceOfTheGivenClass(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new DataReader(['a' => 'x']))->object('a', DateTimeImmutable::class);
    }

    #[Test]
    public function nullObjectReturnsNullWhenMissingOrMismatched(): void
    {
        $date = new DateTimeImmutable('2026-08-24');
        $reader = new DataReader(['a' => $date, 'b' => 'x']);

        static::assertSame($date, $reader->nullObject('a', DateTimeImmutable::class));
        static::assertNull($reader->nullObject('b', DateTimeImmutable::class));
        static::assertNull($reader->nullObject('c', DateTimeImmutable::class));
    }

    #[Test]
    public function enumReturnsTheValueWhenItIsAnInstanceOfTheGivenEnum(): void
    {
        $reader = new DataReader(['a' => Suit::Hearts]);

        static::assertSame(Suit::Hearts, $reader->enum('a', Suit::class));
    }

    #[Test]
    public function enumThrowsWhenTheValueIsNotAnInstanceOfTheGivenEnum(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new DataReader(['a' => 'H']))->enum('a', Suit::class);
    }

    #[Test]
    public function nullEnumReturnsNullWhenMissingOrMismatched(): void
    {
        $reader = new DataReader(['a' => Suit::Hearts, 'b' => 'H']);

        static::assertSame(Suit::Hearts, $reader->nullEnum('a', Suit::class));
        static::assertNull($reader->nullEnum('b', Suit::class));
        static::assertNull($reader->nullEnum('c', Suit::class));
    }

    #[Test]
    public function dateTimeReturnsTheValueWhenItIsADateTime(): void
    {
        $date = new DateTimeImmutable('2026-08-24');

        static::assertSame($date, (new DataReader(['a' => $date]))->dateTime('a'));
    }

    #[Test]
    public function dateTimeThrowsWhenTheValueIsNotADateTime(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new DataReader(['a' => '2026-08-24']))->dateTime('a');
    }

    #[Test]
    public function dateTimeThrowsWhenTheValueIsMutable(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new DataReader(['a' => new DateTime('2026-08-24')]))->dateTime('a');
    }

    #[Test]
    public function asDateTimeConvertsAMutableDateTime(): void
    {
        $date = new DateTime('2026-08-24T00:00:00+00:00');
        $converted = (new DataReader(['a' => $date]))->asDateTime('a');

        static::assertSame($date->getTimestamp(), $converted->getTimestamp());
    }

    #[Test]
    public function nullDateTimeReturnsNullForAMutableDateTime(): void
    {
        static::assertNull((new DataReader(['a' => new DateTime('2026-08-24')]))->nullDateTime('a'));
    }

    #[Test]
    public function asDateTimeParsesStringsAndTimestamps(): void
    {
        $date = new DateTimeImmutable('2026-08-24T00:00:00+00:00');
        $reader = new DataReader(['a' => '2026-08-24T00:00:00+00:00', 'b' => $date->getTimestamp(), 'c' => $date]);

        static::assertSame($date->getTimestamp(), $reader->asDateTime('a')->getTimestamp());
        static::assertSame($date->getTimestamp(), $reader->asDateTime('b')->getTimestamp());
        static::assertSame($date, $reader->asDateTime('c'));
    }

    #[Test]
    public function asDateTimeReadsAScalarAsUtc(): void
    {
        $reader = new DataReader(['a' => '2026-08-24 00:00:00', 'b' => 1_800_000_000]);

        static::assertSame('2026-08-24T00:00:00+00:00', $reader->asDateTime('a')->format('c'));
        static::assertSame('UTC', $reader->asDateTime('a')->getTimezone()->getName());
        static::assertSame(1_800_000_000, $reader->asDateTime('b')->getTimestamp());
    }

    #[Test]
    public function asDateTimeKeepsTheTimeZoneCarriedByTheValue(): void
    {
        $sydney = new DateTimeZone('Australia/Sydney');
        $reader = new DataReader([
            'a' => '2026-08-24T10:00:00+10:00',
            'b' => new DateTime('2026-08-24T10:00:00', $sydney),
        ]);

        static::assertSame('2026-08-24T10:00:00+10:00', $reader->asDateTime('a')->format('c'));
        static::assertSame('Australia/Sydney', $reader->asDateTime('b')->getTimezone()->getName());
    }

    #[Test]
    public function asDateTimeThrowsWhenTheStringIsNotParsable(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new DataReader(['a' => 'not a date']))->asDateTime('a');
    }

    #[Test]
    public function asDateTimeThrowsWhenTheValueIsNotConvertible(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new DataReader(['a' => 1.5]))->asDateTime('a');
    }

    #[Test]
    public function nullDateTimeReturnsNullWhenMissingOrMismatched(): void
    {
        $date = new DateTimeImmutable('2026-08-24');
        $reader = new DataReader(['a' => $date, 'b' => '2026-08-24']);

        static::assertSame($date, $reader->nullDateTime('a'));
        static::assertNull($reader->nullDateTime('b'));
        static::assertNull($reader->nullDateTime('c'));
    }

}

enum Suit: string
{
    case Hearts = 'H';
    case Spades = 'S';
}

final class Sample
{
    public string $name = 'x';
    /**
     * @var array<string,mixed>
     */
    public array $nested = ['b' => 'y'];
    public string $unset;
    private string $hidden = 'z';

    public function reveal(): string
    {
        return $this->hidden;
    }
}

final class Magic
{
    public function __get(string $name): string
    {
        return 'x';
    }

    public function __isset(string $name): bool
    {
        return $name === 'magic';
    }
}

/**
 * @implements ArrayAccess<string,mixed>
 */
final class Offsets implements ArrayAccess
{
    /**
     * @param array<string,mixed> $offsets
     */
    public function __construct(private readonly array $offsets)
    {
    }

    public function offsetExists(mixed $offset): bool
    {
        return array_key_exists($offset, $this->offsets);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->offsets[$offset];
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        throw new BadMethodCallException('Read-only.');
    }

    public function offsetUnset(mixed $offset): void
    {
        throw new BadMethodCallException('Read-only.');
    }
}
