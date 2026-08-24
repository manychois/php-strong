# Collections — `Manychois\PhpStrong\Collections`

Building blocks for working with data structures: `DataReader` for strongly typed reads out of an untyped array or
object, plus the `Iter` and `Seq` static utilities for iterables and lists.

## `DataReader`

`DataReader` wraps a decoded payload — a JSON body, a configuration array, `$_SERVER`, a row from a database — and
hands back values whose types PHP and PHPStan can both rely on, instead of `mixed` you have to test by hand.

```php
use Manychois\PhpStrong\Collections\DataReader;

$reader = new DataReader(json_decode($body, true));

$name    = $reader->string('user.name');          // string, or throws
$age     = $reader->asInt('user.age');            // '42' becomes 42
$admin   = $reader->nullBool('user.admin');       // null when absent or not a bool
$since   = $reader->asDateTime('user.since');     // DateTimeImmutable
```

The constructor takes an `array` or an `object`. Every key of an array must be a string; a numeric key throws
`InvalidArgumentException`, so the reader can promise string keys everywhere else.

### The three variants

Every value accessor exists in up to three variants, which differ only in how strict they are:

| Variant | Example | Behaviour |
| ------- | ------- | --------- |
| bare | `string('a')` | Strict. The value must already be a string, or `InvalidArgumentException` is thrown. |
| `as` | `asString('a')` | Converts, when a sensible conversion exists. Throws when none does. |
| `null` | `nullString('a')` | Strict, but returns `null` instead of throwing when the key is absent, the value is `null`, or the value has another type. |

Only the bare and `as` variants throw `OutOfBoundsException` for an absent key. The `null` variants never throw for a
missing key, which is what makes them convenient for optional fields.

### Dot notation

A key may be written in dot notation to reach a value nested inside the source. Each segment after the first indexes
one level deeper, through arrays and objects alike:

```php
$reader->string('user.address.city');
$reader->int('items.0.quantity');       // a numeric segment indexes a sequential array
```

An entry whose key matches the whole string wins over dot notation, so a literal key containing a dot stays readable:

```php
(new DataReader(['a.b' => 'x', 'a' => ['b' => 'y']]))->string('a.b'); // 'x'
```

A missing segment, or a segment that is neither an array nor an object, counts as absent: the strict variants throw
`OutOfBoundsException`, the `null` variants return `null`, and `has()` returns `false`.

`count()`, `keys()` and `entries()` always describe the top level only.

### Reading objects

An object is read through its offsets when it implements `ArrayAccess`, then through its public properties, and
finally through `__isset()` and `__get()` when it defines both. A non-public or uninitialized property counts as
absent, which keeps a value object such as a `DateTimeImmutable` a leaf rather than a node to descend into.

```php
$reader = new DataReader(json_decode($body));   // stdClass, not an array
$reader->string('user.name');                   // reads public properties
```

`count()`, `keys()` and `entries()` fall back to the object's public properties. An object property whose name is not
a string — `json_decode('{"0":"x"}')` produces one — makes those three throw `InvalidArgumentException`, since the
reader cannot then keep its string-key promise. The value accessors still read such a property.

### Conversions

The `as` variants of `DataReader` recognise the following. Another implementation of `DataReaderInterface` may choose
differently; the interface leaves the rules open.

| Method | Accepts |
| ------ | ------- |
| `asBool` | Whatever `filter_var()` with `FILTER_VALIDATE_BOOL` accepts: `1`, `true`, `on`, `yes` and their negatives, in any letter case, ignoring surrounding whitespace. The empty string is `false`. |
| `asInt` | Booleans, floats and numeric strings. A fractional part is discarded, so `3.7` becomes `3` and `-3.7` becomes `-3`. A float outside the integer range, or a non-finite one, is rejected. |
| `asFloat` | Integers, booleans and numeric strings. |
| `asString` | Output resembling JSON: a boolean becomes `'true'` or `'false'`, and `null` becomes the empty string. Integers, floats and stringable objects are converted as PHP renders them. |
| `asDateTime` | A string parsed with the standard date and time formats, or an integer read as a Unix timestamp. |

`asDateTime()` applies UTC only to those scalars: a string carrying no time zone is read as UTC, rather than in PHP's
default timezone, and a timestamp is UTC by definition. A value which is already a date and time keeps its own time
zone — a `DateTimeImmutable` is returned unchanged, and a mutable `DateTime` is converted with
`DateTimeImmutable::createFromInterface()`.

The strict `dateTime()` and `nullDateTime()` require a `DateTimeImmutable`; a mutable `DateTime` is a type mismatch,
because converting it would be a conversion, and the strict variants do not convert.

### Reference

| Member | Returns | Notes |
| ------ | ------- | ----- |
| `__construct(array\|object $source)` | | Throws `InvalidArgumentException` if an array key is not a string. |
| `get(string $key)` | `mixed` | The value, whatever its type. |
| `has(string $key)` | `bool` | True even when the value is `null`. |
| `falsy(string $key)` | `bool` | True for `null`, `false`, `0`, `0.0`, `''`, `'0'`, `[]`, and for an absent key. Never throws. |
| `entries()` | `array<string,mixed>` | The array itself, or the public properties of the object. |
| `keys()` | `list<string>` | Top-level keys, in source order. |
| `count()` | `int` | `DataReaderInterface` extends `Countable`. |
| `string` / `asString` / `nullString` | `string` | |
| `int` / `asInt` / `nullInt` | `int` | |
| `float` / `asFloat` / `nullFloat` | `float` | The strict variants accept an integer and widen it. |
| `bool` / `asBool` / `nullBool` | `bool` | |
| `dateTime` / `asDateTime` / `nullDateTime` | `DateTimeImmutable` | |
| `array` / `nullArray` | `array` | Any shape; the value must already be an array. |
| `reader` / `nullReader` | `static` | Wraps a nested array or object in another reader. |
| `object(string $key, string $className)` / `nullObject` | `TObject` | Generic over `class-string<TObject>`. |
| `enum(string $key, string $enumClass)` / `nullEnum` | `TEnum` | Generic over `class-string<TEnum of UnitEnum>`; the value must already be a case. |

`reader()` and `nullReader()` return `static`, so a subclass keeps its own type when descending.

## `Iter` and `Seq`

Two static-only utilities in the same namespace. `Iter` manipulates any `iterable` lazily through generators —
`map()`, `filter()`, `take()`, `chunk()`, `unique()` and the rest — while `Seq` does the same work eagerly over
lists, normalising every source to a list reindexed from 0 on entry. Both carry full generic PHPDoc, so element types
survive a chain of calls.
