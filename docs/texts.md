# Texts — `Manychois\PhpStrong\Texts`

Regular expressions, wrapped so that a failure is an exception and a match is an object.

PHP's `preg_*` functions report a bad pattern by emitting a warning and returning `false` or `null`, and they report
a match as a nested array whose shape changes with the flags passed. `Regex` removes both: every failure becomes a
`RuntimeException`, and every match becomes a `MatchResult` with named properties.

## Quick start

```php
use Manychois\PhpStrong\Texts\Regex;

$regex = new Regex('/(?<year>\d{4})-(\d{2})/');

$match = $regex->match('Released 2026-08 worldwide');
$match->success;                       // true
$match->value;                         // '2026-08'
$match->index;                         // 9
$match->namedCaptures['year']->value;  // '2026'
$match->captures[1]->value;            // '08'  (numbered groups, whole match excluded)

$regex->replace('2026-08', '$2/$1');   // '08/2026'
$regex->split('2026-08 2027-01');      // ['', ' ', '']  — the separators are consumed

```

## `Regex`

Immutable. The pattern is supplied once, including its delimiters and modifiers, and exposed as the readonly
`$pattern` property.

| Member | Returns | Notes |
| ------ | ------- | ----- |
| `__construct(string $pattern)` | | The pattern is not validated here; an invalid one throws on first use. |
| `escape(string $text, ?string $delimiter = null)` | `string` | Static. `preg_quote()`. Pass the delimiter you intend to use — `/` is not a regex metacharacter, so it is only escaped when named. |
| `match(string $subject, int $offset = 0)` | `MatchResult` | `$offset` is a byte offset, counted from the end when negative. Always returns a result; check `$success`. |
| `matchAll(string $subject, int $offset = 0)` | `list<MatchResult>` | Empty when nothing matches. |
| `replace(string $subject, string $replacement, int $limit = -1)` | `string` | `$replacement` uses `$1`/`${1}` backreferences. `-1` means no limit. |
| `replaceCallback(string $subject, callable $callback, int $limit = -1)` | `string` | The callback receives a `MatchResult` and returns the replacement string. |
| `split(string $subject, int $limit = -1, bool $nonEmpty = false)` | `list<string>` | `$nonEmpty` applies `PREG_SPLIT_NO_EMPTY`. A limit of `-1` or `0` means no limit; otherwise the tail is returned whole in the last element. |

Every method except `escape()` throws `RuntimeException` when the underlying `preg_*` call signals failure — a
malformed pattern, a backtrack limit hit, a subject that is not valid UTF-8 under the `u` modifier. The message is
PHP's own warning text where one was emitted, otherwise `preg_last_error_msg()`, and the exception code is the
corresponding `preg_last_error()` constant.

`replaceCallback()` runs its callback under `PREG_OFFSET_CAPTURE`, so the `MatchResult` it hands over carries byte
offsets like the one from `match()`.

## `MatchResult`

The outcome of one match attempt. It extends `Capture`, so the whole match is read from the result itself rather
than from a group.

| Property | Type | Meaning |
| -------- | ---- | ------- |
| `success` | `bool` | False only when the pattern did not match. |
| `value` | `string` | The whole match; `''` when `success` is false. |
| `index` | `?int` | The byte offset of the whole match; `null` when `success` is false. |
| `captures` | `list<Capture>` | The numbered groups, reindexed from 0, **excluding** the whole match — so `$captures[0]` is group 1. |
| `namedCaptures` | `array<string,Capture>` | The `(?<name>…)` groups, keyed by name. A named group also appears in `captures`, as PHP numbers it too. |

A group that did not participate in the match still occupies its position, as a `Capture` with an empty `value` and
a `null` `index`. That keeps `$captures` positional: group 3 is always `$captures[2]`, matched or not.

```php
$match = (new Regex('/(a)|(b)/'))->match('b');
$match->captures[0]->value;  // ''    — group 1 did not participate
$match->captures[0]->index;  // null
$match->captures[1]->value;  // 'b'
```

The constructor takes the raw `$matches` array from `preg_match()`, with or without `PREG_OFFSET_CAPTURE`. Without
the flag every `index` is `null`. Constructing one directly is rarely needed; `Regex` does it for you.

## `Capture`

One captured substring.

| Property | Type | Meaning |
| -------- | ---- | ------- |
| `value` | `string` | The captured text. |
| `index` | `?int` | The zero-based byte offset in the subject, or `null` when unknown or the group did not participate. |

`new Capture(string $value, ?int $index = null)` throws `InvalidArgumentException` on a negative index.

Offsets are **byte** offsets, not character offsets, even under the `u` modifier — this is PCRE's behaviour, and it
is preserved rather than translated. Use `substr()` and friends, not `mb_substr()`, when slicing a subject by one.
