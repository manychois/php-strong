<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\Texts;

use ArrayAccess;
use BadMethodCallException;
use Countable;
use InvalidArgumentException;
use IteratorAggregate;
use JsonSerializable;
use Manychois\PhpStrong\Collections\ArrayList;
use NoDiscard;
use OutOfBoundsException;
use Override;
use Stringable;
use Traversable;
use UnderflowException;
use UnexpectedValueException;

/**
 * A UTF-8 string.
 *
 * @implements ArrayAccess<int, string>
 * @implements IteratorAggregate<non-negative-int, string>
 */
final class Utf8String implements ArrayAccess, Countable, IteratorAggregate, JsonSerializable, Stringable
{
    public const string ENCODING = 'UTF-8';

    /**
     * The raw string value.
     */
    public readonly string $raw;

    private int $length = -1;

    /**
     * The characters of the string.
     *
     * @var null|list<string>
     */
    private ?array $characters = null;

    /**
     * The byte length of the string.
     */
    public int $byteLength {
        get => strlen($this->raw);
    }

    /**
     * Initializes a new instance of the Utf8String class.
     *
     * @param string $value The string value. Must be valid UTF-8.
     */
    public function __construct(string $value)
    {
        $this->raw = $value;
    }

    /**
     * Creates a new UTF-8 string from one or more Unicode code points.
     *
     * @param int ...$codepoints The Unicode code points.
     *
     * @return self The new UTF-8 string containing the concatenated code points.
     */
    #[NoDiscard]
    public static function chr(int ...$codepoints): self
    {
        $s = '';
        foreach ($codepoints as $codepoint) {
            $chr = mb_chr($codepoint, self::ENCODING);
            if ($chr === false) {
                throw new UnexpectedValueException(sprintf('The Unicode code point %d is invalid', $codepoint));
            }
            $s .= $chr;
        }
        return new self($s);
    }

    /**
     * Formats a string according to a format string using the given arguments.
     *
     * @param string|self $format The format string.
     * @param mixed ...$args The arguments to format.
     *
     * @return self The formatted string.
     */
    #[NoDiscard]
    public static function format(string|self $format, mixed ...$args): self
    {
        $format = $format instanceof self ? $format->raw : $format;
        $values = [];
        foreach ($args as $index => $arg) {
            if ($arg instanceof self) {
                $values[] = $arg->raw;
            } elseif (!is_scalar($arg)) {
                throw new InvalidArgumentException(
                    sprintf('Argument %d must be a scalar, type %s given', $index, get_debug_type($arg))
                );
            } else {
                $values[] = $arg;
            }
        }

        return new self(vsprintf($format, $values));
    }

    /**
     * Checks if the string contains the given substring.
     *
     * @param string|self $needle The substring to check for.
     *
     * @return bool True if the string contains the substring, false otherwise.
     */
    #[NoDiscard]
    public function contains(string|self $needle): bool
    {
        $needle = $needle instanceof self ? $needle->raw : $needle;
        return str_contains($this->raw, $needle);
    }

    /**
     * Checks if the string ends with the given substring.
     *
     * @param string|self $needle The substring to check for.
     *
     * @return bool True if the string ends with the substring, false otherwise.
     */
    #[NoDiscard]
    public function endsWith(string|self $needle): bool
    {
        $needle = $needle instanceof self ? $needle->raw : $needle;
        return str_ends_with($this->raw, $needle);
    }

    /**
     * Returns the index of the first occurrence of the substring in the string.
     *
     * @param string|self $needle The substring to search for.
     * @param int $offset The offset to start searching from.
     * @param bool $caseSensitive Whether the search should be case-sensitive.
     *
     * @return int The index of the first occurrence of the substring, or -1 if not found.
     */
    #[NoDiscard]
    public function indexOf(string|self $needle, int $offset = 0, bool $caseSensitive = true): int
    {
        $needle = $needle instanceof self ? $needle->raw : $needle;
        $result = $caseSensitive
            ? mb_strpos($this->raw, $needle, $offset, self::ENCODING)
            : mb_stripos($this->raw, $needle, $offset, self::ENCODING);
        return $result === false ? -1 : $result;
    }

    /**
     * Joins an iterable of strings into a single string.
     *
     * @param iterable<string|self> $strings The strings to join.
     *
     * @return self The joined string.
     */
    #[NoDiscard]
    public function join(iterable $strings): self
    {
        $s = '';
        $firstJoined = false;
        foreach ($strings as $string) {
            if ($firstJoined) {
                $s .= $this->raw;
            }
            $s .= $string instanceof self ? $string->raw : $string;
            $firstJoined = true;
        }
        return new self($s);
    }

    /**
     * Returns the index of the last occurrence of the substring in the string.
     *
     * @param string|self $needle The substring to search for.
     * @param int $offset The offset to start searching from.
     * @param bool $caseSensitive Whether the search should be case-sensitive.
     *
     * @return int The index of the last occurrence of the substring, or -1 if not found.
     */
    public function lastIndexOf(string|self $needle, int $offset = -1, bool $caseSensitive = true): int
    {
        $needle = $needle instanceof self ? $needle->raw : $needle;
        $result = $caseSensitive
            ? mb_strrpos($this->raw, $needle, $offset, self::ENCODING)
            : mb_strripos($this->raw, $needle, $offset, self::ENCODING);
        return $result === false ? -1 : $result;
    }

    /**
     * Returns a new string with the first character converted to lowercase.
     *
     * @return self The new string with the first character converted to lowercase.
     */
    #[NoDiscard]
    public function lcfirst(): self
    {
        return new self(mb_lcfirst($this->raw, self::ENCODING));
    }

    /**
     * Returns a new string with all characters converted to lowercase.
     *
     * @return self The new string with all characters converted to lowercase.
     */
    #[NoDiscard]
    public function lower(): self
    {
        return new self(mb_strtolower($this->raw, self::ENCODING));
    }

    /**
     * Returns the Unicode code point of the first character of the string.
     *
     * @return int The Unicode code point of the first character of the string.
     *
     * @throws UnderflowException if the string is empty.
     */
    #[NoDiscard]
    public function ord(): int
    {
        if ($this->raw === '') {
            throw new UnderflowException('The string is empty');
        }
        $first = mb_substr($this->raw, 0, 1, self::ENCODING);

        return mb_ord($first, self::ENCODING);
    }

    /**
     * Returns a new string with the string padded to the given length.
     *
     * @param int $length The length to pad the string to.
     * @param string|self $padString The string to pad with.
     * @param StringSide $side Which side(s) to extend when padding.
     *
     * @return self The new string with the string padded to the given length.
     */
    #[NoDiscard]
    public function pad(int $length, string|self $padString = ' ', StringSide $side = StringSide::Right): self
    {
        $padString = $padString instanceof self ? $padString->raw : $padString;
        return new self(mb_str_pad($this->raw, $length, $padString, $side->value, self::ENCODING));
    }

    /**
     * Returns a new string with the string repeated the given number of times.
     *
     * @param int $times The number of times to repeat the string.
     *
     * @return self The new string with the string repeated the given number of times.
     *
     * @throws InvalidArgumentException If the number of times to repeat the string is negative.
     */
    #[NoDiscard]
    public function repeat(int $times): self
    {
        if ($times < 0) {
            throw new InvalidArgumentException('The number of times to repeat the string must be non-negative');
        }
        return new self(str_repeat($this->raw, $times));
    }

    /**
     * Returns a new string with all occurrences of the search string replaced with the replacement string.
     *
     * @param string|self $search The search string.
     * @param string|self $replace The replacement string.
     *
     * @return self The new string with the replacements.
     */
    #[NoDiscard]
    public function replace(string|self $search, string|self $replace): self
    {
        $search = $search instanceof self ? $search->raw : $search;
        $replace = $replace instanceof self ? $replace->raw : $replace;
        return new self(str_replace($search, $replace, $this->raw));
    }

    /**
     * Splits the string into an array of substrings.
     *
     * @param string|self $delimiter The delimiter.
     * @param int $limit The maximum number of substrings to return.
     * If negative, all substrings are returned.
     * If zero, the original string is returned.
     *
     * @return ArrayList<self> The array of substrings.
     */
    #[NoDiscard]
    public function split(string|self $delimiter, int $limit = -1): ArrayList
    {
        $delimiter = $delimiter instanceof self ? $delimiter->raw : $delimiter;
        if ($delimiter === '') {
            throw new InvalidArgumentException('Delimiter must not be empty');
        }
        if ($limit === 0) {
            return new ArrayList([new self($this->raw)]);
        }
        if ($limit < 0) {
            $limit = PHP_INT_MAX;
        }
        $result = explode($delimiter, $this->raw, $limit);
        return new ArrayList(array_map(static fn(string $s) => new self($s), $result));
    }

    /**
     * Checks if the string starts with the given substring.
     *
     * @param string|self $needle The substring to check for.
     *
     * @return bool True if the string starts with the substring, false otherwise.
     */
    #[NoDiscard]
    public function startsWith(string|self $needle): bool
    {
        $needle = $needle instanceof self ? $needle->raw : $needle;
        return str_starts_with($this->raw, $needle);
    }

    /**
     * Returns a new string with the substring starting at the given index and optionally with the given length.
     *
     * @param int $start The index to start the substring at.
     * @param ?int $length The length of the substring.
     * If null, the substring will be from the start index to the end of the string.
     *
     * @return self The new string with the substring.
     */
    #[NoDiscard]
    public function substr(int $start, ?int $length = null): self
    {
        return new self(mb_substr($this->raw, $start, $length, self::ENCODING));
    }

    /**
     * Returns a new string with the first letter of each word converted to uppercase.
     *
     * @return self The new string with the first letter of each word converted to uppercase.
     */
    #[NoDiscard]
    public function title(): self
    {
        return new self(mb_convert_case($this->raw, MB_CASE_TITLE, self::ENCODING));
    }

    /**
     * Returns a new string with the leading and trailing characters removed.
     *
     * @param null|string|self $characters The characters to remove.
     * If null, whitespace characters listed in https://www.php.net/manual/en/function.mb-trim.php are used.
     *
     * @return self The new string with the leading and trailing characters removed.
     */
    #[NoDiscard]
    public function trim(StringSide $side = StringSide::Both, null|string|self $characters = null): self
    {
        $characters = $characters instanceof self ? $characters->raw : $characters;
        return match ($side) {
            StringSide::Left => new self(mb_ltrim($this->raw, $characters, self::ENCODING)),
            StringSide::Right => new self(mb_rtrim($this->raw, $characters, self::ENCODING)),
            StringSide::Both => new self(mb_trim($this->raw, $characters, self::ENCODING)),
        };
    }

    /**
     * Returns a new string with the first character converted to uppercase.
     *
     * @return self The new string with the first character converted to uppercase.
     */
    #[NoDiscard]
    public function ucfirst(): self
    {
        return new self(mb_ucfirst($this->raw, self::ENCODING));
    }

    /**
     * Returns a new string with all characters converted to uppercase.
     *
     * @return self The new string with all characters converted to uppercase.
     */
    #[NoDiscard]
    public function upper(): self
    {
        return new self(mb_strtoupper($this->raw, self::ENCODING));
    }

    /**
     * @return list<string>
     */
    private function getCharacters(): array
    {
        if ($this->characters === null) {
            $this->characters = mb_str_split($this->raw, 1, self::ENCODING);
        }
        return $this->characters;
    }

    #region implements ArrayAccess

    /**
     * @inheritDoc
     */
    #[Override]
    public function offsetExists(mixed $offset): bool
    {
        if (!is_int($offset)) {
            throw new InvalidArgumentException('Offset must be an integer');
        }
        return $offset >= 0 && $offset < $this->count();
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function offsetGet(mixed $offset): string
    {
        if (!is_int($offset)) {
            throw new InvalidArgumentException('Offset must be an integer');
        }
        if ($offset < 0 || $offset >= $this->count()) {
            throw new OutOfBoundsException('Offset is out of bounds');
        }
        return $this->getCharacters()[$offset];
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function offsetSet(mixed $offset, mixed $value): void
    {
        throw new BadMethodCallException('The string is immutable');
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function offsetUnset(mixed $offset): void
    {
        throw new BadMethodCallException('The string is immutable');
    }

    #endregion implements ArrayAccess

    #region implements Countable

    /**
     * Returns the number of Unicode code points in the string.
     *
     * @return non-negative-int The number of Unicode code points in the string.
     */
    #[Override]
    public function count(): int
    {
        if ($this->length < 0) {
            if ($this->characters !== null) {
                $this->length = count($this->characters);
            } else {
                $this->length = mb_strlen($this->raw, self::ENCODING);
            }
        }
        return $this->length;
    }

    #endregion implements Countable

    #region implements IteratorAggregate

    /**
     * @inheritDoc
     */
    #[Override]
    public function getIterator(): Traversable
    {
        foreach ($this->getCharacters() as $index => $character) {
            yield $index => $character;
        }
    }

    #endregion implements IteratorAggregate

    #region implements JsonSerializable

    /**
     * @inheritDoc
     */
    #[Override]
    public function jsonSerialize(): string
    {
        return $this->raw;
    }

    #endregion implements JsonSerializable

    #region implements Stringable

    /**
     * @inheritDoc
     */
    #[Override]
    public function __toString(): string
    {
        return $this->raw;
    }

    #endregion implements Stringable
}
