# PSR-13 Link — Design

Date: 2026-08-21
Module: `Manychois\PhpStrong\Links`

## Goal

Ship concrete, strongly-typed implementations of the four PSR-13 (`psr/link`) interfaces: an immutable link value
object and an immutable link collection, both evolvable, with `isTemplated()` derived from a strict RFC 6570 check
rather than a brace heuristic.

## Scope

In scope:

- `Link` — implements `Psr\Link\EvolvableLinkInterface` (and therefore `LinkInterface`).
- `LinkProvider` — implements `Psr\Link\EvolvableLinkProviderInterface` (and therefore `LinkProviderInterface`).
- `InvalidArgumentException` — thrown at the boundary for malformed rels and attributes.
- `Internal\UriTemplate` — an `@internal` RFC 6570 grammar check backing `Link::isTemplated()`.

Out of scope (deliberately):

- Serializers of any kind: no RFC 8288 `Link:` header renderer, no HTML `<link>`/`<a>` renderer. PSR-13 defines
  link *definition*, and the serialization rules in §1.2 bind serializers, not link objects. A serializer is added
  when a caller in this package actually needs one.
- PSR-7 integration (a response that is itself a link provider).
- URI template *expansion*. The module answers "is this a template", never "expand it with these values".
- An IANA link-relations registry check, and any mutable (non-evolvable) provider.

## Dependencies and layout

- `composer.json`: require `psr/link: ^2` (v2 carries the parameter and return type hints, so the interface
  methods are natively typed); add `psr-13` and `link` keywords.
- `src/Links/Link.php`, `LinkProvider.php`, `InvalidArgumentException.php`, `Internal/UriTemplate.php`.
- `tests/Links/` mirrors `src/Links/`.
- `docs/links.md` — reference page in the style of `docs/events.md`.
- `README.md` — add the module to the module list.

The module has no intra-package dependency; it depends only on `psr/link`.

Imports follow the project standard: `use Psr\Link\EvolvableLinkInterface as IEvolvableLink;`,
`use Psr\Link\EvolvableLinkProviderInterface as IEvolvableLinkProvider;`, `use Psr\Link\LinkInterface as ILink;`.

## Exceptions

`Manychois\PhpStrong\Links\InvalidArgumentException` extends `\InvalidArgumentException`, mirroring the `Cache`
module's pattern of a module-local exception class. PSR-13 mandates no exception interface, so this class
implements none. Every validation failure described below throws it with a descriptive message naming the
offending value.

## `Link`

`final class Link implements IEvolvableLink`.

### Construction

```php
public function __construct(string|Stringable $href = '', array $rels = [], array $attributes = [])
```

- `$href` is cast to `string` immediately, satisfying the §3.2 requirement that a `Stringable` is evaluated on the
  way in rather than held and stringified later. The default `''` gives a usable empty link to evolve from.
- `$rels` is a `list<string>`; each entry is validated (below) and duplicates are collapsed, keeping first-seen
  order.
- `$attributes` is an `array<string, string|int|float|bool|list<string>>`; each key and value is validated (below).
  Insertion order is preserved.

### State

Four `private` properties: `string $href`, `bool $templated`, `list<string> $rels`,
`array<string, string|int|float|bool|list<string>> $attributes`. They are not `readonly`: PHP 8.5 rejects
`clone $this with { ... }`, so evolution follows the `Http\Uri` pattern already in this package — `$clone = clone
$this;` then assign. The class is `final` and every property is private, so instances are immutable from outside.

`$templated` is computed in the constructor from `$href` via `UriTemplate::isTemplate()`. There is no
`withTemplated()` and no way to set it independently — §1.6 requires it to be derived from the href alone.

### Methods

All eight interface methods live in a `#region implements IEvolvableLink` block, sorted alphabetically:
`getAttributes()`, `getHref()`, `getRels()`, `isTemplated()`, `withAttribute()`, `withHref()`, `withoutAttribute()`,
`withoutRel()`, `withRel()`. Each carries `#[Override]`.

Evolution clones and assigns, as `Http\Uri` does. Returns are declared `static` to honour the interface's
`@return static` contract.

- `withHref(string|Stringable $href): static` — casts immediately and recomputes `$templated`.
- `withRel(string $rel): static` — validates, then returns `$this` unchanged when the rel is already present
  (§3.2: must return normally, must not add a second time).
- `withoutRel(string $rel): static` — returns `$this` unchanged when absent; otherwise removes and reindexes so the
  result stays a `list`.
- `withAttribute(string $attribute, string|Stringable|int|float|bool|array $value): static` — the union is fixed
  by the interface signature in `psr/link` 2.0 and cannot be narrowed. Validates name and value; an existing name
  is overwritten in place, keeping its original position.
- `withoutAttribute(string $attribute): static` — returns `$this` unchanged when absent.

Getter return types are narrowed in PHPDoc: `@return list<string>` for `getRels()`,
`@return array<string, string|int|float|bool|list<string>>` for `getAttributes()`.

### Validation

- **Rel**: rejects an empty or whitespace-only string, and any string containing whitespace (a rel is a single
  token or a single absolute URI; a space-separated list is a serialization concern, not a rel value). Every other
  string is accepted, so both IANA keywords and private absolute URIs pass. §1.3 states only a `SHOULD` for the
  registry, so no registry is bundled.
- **Attribute name**: rejects an empty or whitespace-only string. Any other string is accepted — §1.2 explicitly
  declines to define a registry of names.
- **Attribute value**: accepts `string`, `int`, `float`, `bool`, a `Stringable` (cast to `string` immediately, as
  `withHref()` does), or a `list<string>`. Everything else throws — a non-`Stringable` object, a nested array, a
  string-keyed array, and a list containing a non-string. This makes the "PHP
  primitive or an array of PHP strings" wording of §1.2 enforceable and gives PHPStan a precise type to reason
  about. Note that `bool` values are stored verbatim; the abbreviation and omission rules of §1.2 apply to
  serializers, which are out of scope.

## `LinkProvider`

`final class LinkProvider implements IEvolvableLinkProvider`.

### Construction

```php
public function __construct(ILink ...$links)
```

Stores a `list<ILink>` in the order given, collapsing entries that are `===` identical to one already held.

### Methods

A `#region implements IEvolvableLinkProvider` block, alphabetically: `getLinks()`, `getLinksByRel()`,
`withLink()`, `withoutLink()`, each with `#[Override]`.

- `getLinks(): array` — returns the `list<ILink>` in insertion order. `@return list<ILink>`.
- `getLinksByRel(string $rel): array` — returns the links whose `getRels()` contains `$rel` by exact string
  match, in insertion order; an empty `list` when none match. No rel validation here: an unmatchable rel simply
  matches nothing.
- `withLink(ILink $link): static` — identity (`===`) comparison per §3.3; returns `$this` unchanged when the link
  is already present, otherwise appends.
- `withoutLink(ILink $link): static` — identity comparison; returns `$this` unchanged when absent, otherwise
  removes and reindexes so the result stays a `list`.

Both getters return plain arrays rather than generators, which the PSR permits (`iterable`) and which makes the
result countable and re-iterable.

## `Internal\UriTemplate`

`final class UriTemplate` marked `@internal`, holding a single static method:

```php
public static function isTemplate(string $href): bool
```

Returns `true` only when the whole string parses as an RFC 6570 URI template **and** contains at least one
expression. A plain URI containing no `{` therefore returns `false`, and so does a string with a malformed or
unbalanced `{` — a malformed href is treated as a non-templated URI and never throws, because PSR-13 permits any
absolute or relative URI as an href and the module must not reject one.

Grammar enforced:

- **Literal runs** exclude CTL (`\x00-\x1F`, `\x7F`), space, `"`, `'`, `<`, `>`, `\`, `^`, `` ` ``, `|`, `{`, `}`.
  A `%` is allowed only as the start of a `pct-encoded` triplet (`%` followed by two hex digits). Characters above
  `\x7F` are accepted as `ucschar`/`iprivate` without further decoding.
- **Expression** = `{` `operator`? `varspec` ( `,` `varspec` )* `}`.
- **Operator** = one of `+ # . / ; ? &`. The operators RFC 6570 reserves for future extension (`= , ! @ |`) are
  rejected: they are not valid templates under the current RFC.
- **Varspec** = `varname` `modifier`?, where `varname` = `varchar` ( `.`? `varchar` )* and `varchar` = ALPHA /
  DIGIT / `_` / `pct-encoded`. A varname may neither start nor end with `.` nor contain `..`.
- **Modifier** = `:` followed by a max-length of 1–4 digits with no leading zero (1–9999), or `*`.

Implementation is a single pass over the string with a compiled `preg_match` per expression rather than a
hand-rolled character loop, keeping the class small enough to read in one screen.

## Testing

`tests/Links/LinkTest.php`, `LinkProviderTest.php`, `Internal/UriTemplateTest.php`, targeting 100% coverage of the
module as with `Cache` and `Events`.

- **Evolvability round-trips**: every `with*`/`without*` returns a new instance whose single change took effect,
  and the receiver is verifiably unmutated (assert the original's getters afterwards).
- **Idempotence**: `withRel()` with a rel already present, `withoutRel()` with one absent, `withAttribute()`
  overwriting an existing name in place, `withoutAttribute()` with one absent, `withLink()` with an identical
  instance, `withoutLink()` with one absent — each returns without error and leaves the value set unchanged.
- **Identity semantics**: two `Link` instances with equal state are distinct to the provider; adding both keeps
  both, and `withoutLink()` on one leaves the other.
- **Boundary rejections**: a data-provider table of invalid rels, attribute names, and attribute values, each
  asserting `InvalidArgumentException` from both the constructor and the corresponding `with*` method.
- **Templated detection**: a data-provider table built from the RFC 6570 level 1–4 example templates (expected
  `true`), plain absolute/relative URIs and an empty string (expected `false`), and malformed cases (expected
  `false`): unbalanced `{`, empty expression `{}`, a reserved operator, a trailing comma, a varname starting or
  ending with `.`, `:0`, `:12345`, a bare `%` in a literal, and a space in a literal.
- **Cross-check**: `new Link('/posts/{id}')->isTemplated()` is `true` and `withHref('/posts/1')` flips it to
  `false`, proving the derivation follows the href.

## Documentation

`docs/links.md`, following `docs/events.md`: a short intro naming the interfaces implemented, one runnable example
building a link and a provider, then a method table per class with the notes that matter (identity semantics,
idempotence, derived `isTemplated()`, accepted attribute value types). Reference material only — no tutorial, no
explanation of RFC 6570 beyond what a caller needs. `README.md` gains the module row.

## Quality gates

`composer phpcbf` → `composer phpcs` → `composer phpstan` → `composer test`, all clean before the work is called
done.
