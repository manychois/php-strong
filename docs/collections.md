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
| `reader` / `nullReader` | `DataReaderInterface` | Wraps a nested array or object in another reader. |
| `object(string $key, string $className)` / `nullObject` | `TObject` | Generic over `class-string<TObject>`. |
| `enum(string $key, string $enumClass)` / `nullEnum` | `TEnum` | Generic over `class-string<TEnum of UnitEnum>`; the value must already be a case. |

The reading logic itself lives in `Collections\Internal\AbstractDataReader`, which `DataReader` extends by supplying
its source and a factory for nested readers. That base is internal — depend on `DataReaderInterface`, not on it — but
it is what lets another reader with its own storage reuse the whole accessor surface.

## `Iter` and `Seq`

Two static-only utilities in the same namespace, covering the same ground with opposite evaluation strategies. Both
carry full generic PHPDoc, so the element type survives a chain of calls.

`Iter` works lazily through generators. Nothing is read from the source until the result is iterated, so a chain
composes without materialising an intermediate array and an endless source stays usable as long as the chain ends in
something that short-circuits. **Source keys are preserved** by the lazy methods, except where reindexing is
inherent to the operation (`chunk()`, `flatten()`, `flatMap()`).

`Seq` works eagerly over lists. Every source is normalised with `Iter::toList()` on entry, so **source keys are
discarded**, the result is always a `list` reindexed from 0, and each callback receives the element's position in
that list as its second argument. In exchange it offers what a single lazy pass cannot: positional access, ordering,
and searching backwards.

```php
use Manychois\PhpStrong\Collections\Iter;
use Manychois\PhpStrong\Collections\Seq;

$firstThree = Iter::take(Iter::filter($lines, $isError), 3);   // reads only as far as the third error
$sorted = Seq::orderBy($users, fn ($a, $b) => $a->age <=> $b->age);
```

### Shared members

Same name, same meaning, differing only in that `Iter` returns an `iterable` where `Seq` returns a `list`.

| Member | `Iter` returns | `Seq` returns | Notes |
| ------ | -------------- | ------------- | ----- |
| `all($source, $predicate)` | `bool` | `bool` | True for an empty source. Short-circuits on the first non-match. |
| `any($source, $predicate)` | `bool` | `bool` | False for an empty source. Short-circuits on the first match. |
| `chunk($source, int $size)` | `iterable<list<T>>` | `list<list<T>>` | The last chunk may be short. `InvalidArgumentException` if `$size` is not positive. |
| `filter($source, $predicate)` | `iterable<T>` | `list<T>` | |
| `first($source, ?$predicate = null)` | `T` | `T` | `UnderflowException` if nothing matches. |
| `firstOrNull($source, ?$predicate = null)` | `?T` | `?T` | |
| `flatMap($source, $mapper)` | `iterable<T2>` | `list<T2>` | The mapper returns an iterable per element; results are concatenated and reindexed. |
| `flatten($source)` | `iterable<T>` | `list<T>` | One level only. |
| `last($source, ?$predicate = null)` | `T` | `T` | `UnderflowException` if nothing matches. Consumes the whole source, so it never returns on an endless one. |
| `lastOrNull($source, ?$predicate = null)` | `?T` | `?T` | Same caveat as `last()`. |
| `map($source, $mapper)` | `iterable<T2>` | `list<T2>` | |
| `reduce($source, $reducer, mixed $initial)` | `mixed` | `mixed` | The initial value is required; there is no seedless overload. |
| `skip($source, int $count)` | `iterable<T>` | `list<T>` | `InvalidArgumentException` if `$count` is negative. |
| `skipWhile($source, $predicate)` | `iterable<T>` | `list<T>` | Stops testing after the first non-match; everything from there on is kept. |
| `take($source, int $count)` | `iterable<T>` | `list<T>` | `InvalidArgumentException` if `$count` is negative. |
| `takeWhile($source, $predicate)` | `iterable<T>` | `list<T>` | Stops at the first non-match. |
| `unique($source, ?$keySelector = null)` | `iterable<T>` | `list<T>` | Keeps the first occurrence of each key. Without a selector, elements are compared by PHP array-key coercion, so `1`, `'1'` and `true` collapse together and a non-array-key element raises `TypeError`. |

The second argument a callback receives differs between the two, following each class's treatment of keys.
`Iter` passes the **source key** — `callable(T $value, TKey $key)`, and `callable(TAcc $carry, T $value, TKey $key)`
for `reduce()` — so a string-keyed source reaches the callback with its string keys intact. `Seq` passes the
element's **position in the normalised list**, a `non-negative-int` counting from 0. `unique()` is the exception on
both: its `$keySelector` receives only the value and returns an `array-key`.

### `Iter` only

| Member | Returns | Notes |
| ------ | ------- | ----- |
| `count($source)` | `int` | Uses `count()` on a `Countable` source, otherwise walks it. |
| `toArray($source)` | `array<array-key,T>` | Preserves keys. On collision the last value wins, and keys are subject to array-key coercion; `TypeError` if a key cannot be one. |
| `toList($source)` | `list<T>` | Discards keys, reindexing from 0. |

### `Seq` only

Each of these needs the whole list in hand, which is why `Iter` has no counterpart.

| Member | Returns | Notes |
| ------ | ------- | ----- |
| `at($source, int $index)` | `T` | A negative index counts from the end, so `-1` is the last element. `OutOfBoundsException` if it resolves outside the list. |
| `concat(...$sources)` | `list<T>` | Appends any number of iterables end to end. |
| `contains($source, mixed $value)` | `bool` | Strict comparison (`===`). |
| `indexOf($source, mixed $value)` | `?int` | Position of the first strict match, or `null`. |
| `insertAt($source, int $index, mixed ...$values)` | `list<T>` | Inserts before `$index`; `$index === count($source)` appends. `OutOfBoundsException` if negative or past the end. |
| `lastIndexOf($source, mixed $value)` | `?int` | Position of the last strict match, or `null`. |
| `orderBy($source, ?$comparator = null)` | `list<T>` | Stable: equal elements keep their original order. Without a comparator, sorts ascending with the default comparison. |
| `removeAt($source, int $index)` | `list<T>` | `OutOfBoundsException` if `$index` is not a valid position. |
| `reverse($source)` | `list<T>` | |
| `slice($source, int $offset, ?int $length = null)` | `list<T>` | `array_slice()` semantics: a negative `$offset` counts from the end, a negative `$length` stops that many elements short of it, and `null` runs to the end. Out-of-range gives an empty list. |

Every method on both classes is static and none mutates its source; `insertAt()`, `removeAt()`, `orderBy()` and
`reverse()` return a new list.
