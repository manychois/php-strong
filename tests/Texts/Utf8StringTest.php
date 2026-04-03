<?php

declare(strict_types=1);

namespace Manychois\PhpStrongTests\Texts;

use BadMethodCallException;
use InvalidArgumentException;
use Manychois\PhpStrong\Texts\StringSide;
use Manychois\PhpStrong\Texts\Utf8String;
use OutOfBoundsException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use UnderflowException;
use UnexpectedValueException;

/**
 * Unit tests for Utf8String.
 */
final class Utf8StringTest extends TestCase
{
    #[Test]
    public function constructor_stores_raw_and_byte_length(): void
    {
        $s = new Utf8String('aé');

        self::assertSame('aé', $s->raw);
        self::assertSame(3, $s->byteLength);
    }

    #[Test]
    public function chr_builds_from_codepoints(): void
    {
        $s = Utf8String::chr(65, 0x20AC);

        self::assertSame('A€', $s->raw);
    }

    #[Test]
    public function chr_throws_for_invalid_codepoint(): void
    {
        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('1114112');

        $unused = Utf8String::chr(0x110000);
        unset($unused);
    }

    #[Test]
    public function format_with_strings_and_utf8_string_arguments(): void
    {
        $fmt = new Utf8String('%s-%s');
        $b = new Utf8String('b');
        $out = Utf8String::format($fmt, 'a', $b);

        self::assertSame('a-b', $out->raw);
    }

    #[Test]
    public function format_rejects_non_scalar_arguments(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Argument 0 must be a scalar');

        $unused = Utf8String::format('%s', [1]);
        unset($unused);
    }

    #[Test]
    public function contains_ends_with_and_starts_with(): void
    {
        $s = new Utf8String('hello café');
        $needle = new Utf8String('café');

        self::assertTrue($s->contains('caf'));
        self::assertTrue($s->contains($needle));
        self::assertTrue($s->endsWith($needle));
        self::assertTrue($s->startsWith('hello'));
        self::assertFalse($s->contains('zzz'));
    }

    #[Test]
    public function index_of_respects_case_and_offset(): void
    {
        $s = new Utf8String('AaA');

        self::assertSame(1, $s->indexOf('a'));
        self::assertSame(0, $s->indexOf('A', 0));
        self::assertSame(2, $s->indexOf('A', 1));
        self::assertSame(0, $s->indexOf('a', 0, false));
        self::assertSame(-1, $s->indexOf('z'));
    }

    #[Test]
    public function last_index_of_respects_case_and_offset(): void
    {
        $s = new Utf8String('abcabc');

        self::assertSame(4, $s->lastIndexOf('b'));
        self::assertSame(1, $s->lastIndexOf('b', -5));
        self::assertSame(-1, $s->lastIndexOf('z'));
    }

    #[Test]
    public function last_index_of_can_be_case_insensitive(): void
    {
        $s = new Utf8String('aBcAbC');

        self::assertSame(4, $s->lastIndexOf('b', -1, false));
        self::assertSame(-1, $s->lastIndexOf('z', -1, false));
    }

    #[Test]
    public function join_inserts_separator_between_elements(): void
    {
        $sep = new Utf8String(', ');
        $out = $sep->join(['a', new Utf8String('b'), 'c']);

        self::assertSame('a, b, c', $out->raw);
    }

    #[Test]
    public function join_with_empty_iterable_returns_empty_string(): void
    {
        $sep = new Utf8String('|');
        $out = $sep->join([]);

        self::assertSame('', $out->raw);
    }

    #[Test]
    public function lcfirst_lower_upper_ucfirst_title(): void
    {
        self::assertSame('hello', (new Utf8String('Hello'))->lcfirst()->raw);
        self::assertSame('straße', (new Utf8String('STRAßE'))->lower()->raw);
        self::assertSame('STRASSE', (new Utf8String('Straße'))->upper()->raw);
        self::assertSame('Straße', (new Utf8String('straße'))->ucfirst()->raw);
        self::assertSame('Hello World', (new Utf8String('hello world'))->title()->raw);
    }

    #[Test]
    public function ord_returns_first_codepoint(): void
    {
        self::assertSame(65, (new Utf8String('ABC'))->ord());
        self::assertSame(0x4e2d, (new Utf8String('中'))->ord());
    }

    #[Test]
    public function ord_throws_when_empty(): void
    {
        $this->expectException(UnderflowException::class);

        $unused = (new Utf8String(''))->ord();
        unset($unused);
    }

    #[Test]
    public function pad_applies_string_side(): void
    {
        $s = new Utf8String('x');

        self::assertSame('00x', $s->pad(3, '0', StringSide::Left)->raw);
        self::assertSame('x00', $s->pad(3, '0', StringSide::Right)->raw);
        self::assertSame('0x0', $s->pad(3, new Utf8String('0'), StringSide::Both)->raw);
    }

    #[Test]
    public function repeat_throws_when_negative(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $unused = (new Utf8String('ab'))->repeat(-1);
        unset($unused);
    }

    #[Test]
    public function repeat_allows_zero_and_positive(): void
    {
        self::assertSame('', (new Utf8String('ab'))->repeat(0)->raw);
        self::assertSame('abab', (new Utf8String('ab'))->repeat(2)->raw);
    }

    #[Test]
    public function replace_supports_utf8_string_operands(): void
    {
        $s = new Utf8String('one fish two fish');

        $out = $s->replace(new Utf8String('fish'), new Utf8String('emoji'));

        self::assertSame('one emoji two emoji', $out->raw);
    }

    #[Test]
    public function split_throws_when_delimiter_empty(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $unused = (new Utf8String('abc'))->split('');
        unset($unused);
    }

    #[Test]
    public function split_with_limit_zero_returns_original_as_single_element(): void
    {
        $parts = (new Utf8String('a,b'))->split(',', 0);

        self::assertCount(1, $parts);
        self::assertSame('a,b', $parts->at(0)->raw);
    }

    #[Test]
    public function split_respects_positive_limit(): void
    {
        $parts = (new Utf8String('a,b,c'))->split(',', 2);

        self::assertCount(2, $parts);
        self::assertSame('a', $parts->at(0)->raw);
        self::assertSame('b,c', $parts->at(1)->raw);
    }

    #[Test]
    public function split_accepts_utf8_string_delimiter_and_negative_limit(): void
    {
        $parts = (new Utf8String('u|v|w'))->split(new Utf8String('|'), -1);

        self::assertCount(3, $parts);
        self::assertSame('u', $parts->at(0)->raw);
        self::assertSame('v', $parts->at(1)->raw);
        self::assertSame('w', $parts->at(2)->raw);
    }

    #[Test]
    public function substr_uses_character_units(): void
    {
        $s = new Utf8String('aéb');

        self::assertSame('éb', $s->substr(1)->raw);
        self::assertSame('é', $s->substr(1, 1)->raw);
    }

    #[Test]
    public function trim_respects_side_and_character_set(): void
    {
        $s = new Utf8String('  xy  ');

        self::assertSame('xy  ', $s->trim(StringSide::Left)->raw);
        self::assertSame('  xy', $s->trim(StringSide::Right)->raw);
        self::assertSame('xy', $s->trim(StringSide::Both)->raw);
        self::assertSame('y', (new Utf8String('xxxyxx'))->trim(StringSide::Both, 'x')->raw);
        self::assertSame('y', (new Utf8String('xxxyxx'))->trim(StringSide::Both, new Utf8String('x'))->raw);
    }

    #[Test]
    public function array_access_reads_grapheme_indices(): void
    {
        $s = new Utf8String('aé');

        self::assertTrue(isset($s[0]));
        self::assertFalse(isset($s[2]));
        self::assertSame('a', $s[0]);
        self::assertSame('é', $s[1]);
    }

    #[Test]
    public function array_access_rejects_non_int_offset(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $method = new ReflectionMethod(Utf8String::class, 'offsetGet');
        $method->invoke(new Utf8String('x'), 'bad');
    }

    #[Test]
    public function offset_exists_rejects_non_int_offset(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $method = new ReflectionMethod(Utf8String::class, 'offsetExists');
        $method->invoke(new Utf8String('x'), 'bad');
    }

    #[Test]
    public function array_access_throws_when_out_of_bounds(): void
    {
        $this->expectException(OutOfBoundsException::class);

        (new Utf8String('x'))->offsetGet(1);
    }

    #[Test]
    public function array_access_mutations_throw(): void
    {
        $s = new Utf8String('x');

        $this->expectException(BadMethodCallException::class);
        $s->offsetSet(0, 'y');
    }

    #[Test]
    public function offset_unset_throws(): void
    {
        $this->expectException(BadMethodCallException::class);

        (new Utf8String('x'))->offsetUnset(0);
    }

    #[Test]
    public function count_returns_code_point_length(): void
    {
        self::assertCount(0, new Utf8String(''));
        self::assertCount(3, new Utf8String('aé€'));
    }

    #[Test]
    public function count_uses_split_cache_when_characters_loaded_before_length(): void
    {
        $s = new Utf8String('ab');
        foreach ($s as $_) {
            break;
        }

        self::assertCount(2, $s);
    }

    #[Test]
    public function iterator_yields_indices_and_characters(): void
    {
        $s = new Utf8String('aé');
        $keys = [];
        $chars = [];

        foreach ($s as $i => $ch) {
            $keys[] = $i;
            $chars[] = $ch;
        }

        self::assertSame([0, 1], $keys);
        self::assertSame(['a', 'é'], $chars);
    }

    #[Test]
    public function json_serialize_and_to_string_return_raw(): void
    {
        $s = new Utf8String('hi');

        self::assertSame('"hi"', json_encode($s, JSON_THROW_ON_ERROR));
        self::assertSame('hi', (string) $s);
    }
}
