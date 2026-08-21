# PSR-13 Links — `Manychois\PhpStrong\Links`

Two classes satisfy all four `Psr\Link` interfaces: `Link` implements `EvolvableLinkInterface` (and therefore
`LinkInterface`), and `LinkProvider` implements `EvolvableLinkProviderInterface` (and therefore
`LinkProviderInterface`). Both are immutable — every `with*` method returns a new instance and
leaves the receiver untouched — and neither serializes anything. Turning links into an HTTP `Link:` header, HTML
`<link>` elements, or a HAL document is left to the consumer.

```php
use Manychois\PhpStrong\Links\Link;
use Manychois\PhpStrong\Links\LinkProvider;

$self = new Link('/posts/1', ['self']);
$search = (new Link('/posts{?q,page}'))
    ->withRel('search')
    ->withAttribute('title', 'Search posts')
    ->withAttribute('hreflang', ['en', 'fr']);

$search->isTemplated(); // true — derived from the href

$provider = new LinkProvider($self, $search);
$provider->getLinksByRel('search'); // [$search]
```

## `Link`

| Method | Notes |
| ------ | ----- |
| `__construct(string\|Stringable $href = '', array $rels = [], array $attributes = [])` | A `Stringable` href is cast immediately. Duplicate rels are collapsed, keeping first-seen order. Throws `InvalidArgumentException` for a malformed rel or attribute. |
| `getHref(): string` | The href as given. |
| `isTemplated(): bool` | True when the href is a valid RFC 6570 URI template containing at least one expression. Derived from the href on every change; there is no setter, per PSR-13 §1.6. A malformed `{` makes the href an ordinary URI, not an error. |
| `getRels(): array` | `list<string>` in first-seen order. |
| `getAttributes(): array` | `array<string, string\|int\|float\|bool\|list<string>>` in insertion order. |
| `withHref(string\|Stringable $href): static` | Recomputes `isTemplated()`. |
| `withRel(string $rel): static` | Returns the same instance when the rel is already present. Throws `InvalidArgumentException` for a malformed rel. |
| `withoutRel(string $rel): static` | Returns the same instance when the rel is absent. |
| `withAttribute(string $attribute, string\|Stringable\|int\|float\|bool\|array $value): static` | An existing name is overwritten in place, keeping its position. Throws `InvalidArgumentException` for a malformed name or value. |
| `withoutAttribute(string $attribute): static` | Returns the same instance when the attribute is absent. |

### Accepted values

- **Rel** — any non-blank string with no whitespace and no control character (`\x00`–`\x1F`, `\x7F`), so both an
  IANA keyword (`next`) and a private absolute URI (`https://example.com/rels/invoice`) are accepted. The IANA
  registry is not consulted; PSR-13 §1.3 states only a `SHOULD`. A non-string entry in the `$rels` array passed to
  the constructor raises `TypeError`, not `InvalidArgumentException`: the native `array` parameter type does not
  enforce `list<string>`.
- **Attribute name** — any non-blank string with no control character (`\x00`–`\x1F`, `\x7F`).
- **Attribute value** — `string`, `int`, `float`, `bool`, a `Stringable` (cast to `string` on the way in), or a
  list of strings. A keyed array, a nested array, or a list containing a non-string throws
  `InvalidArgumentException`. A value outside the declared union (e.g. `stdClass`, `null`) raises `TypeError` from
  the native parameter type before validation runs. Booleans are stored verbatim: the abbreviation and omission
  rules of PSR-13 §1.2 bind serializers, not link objects.

## `LinkProvider`

| Method | Notes |
| ------ | ----- |
| `__construct(LinkInterface ...$links)` | Holds the links in the order given, collapsing entries identical (`===`) to one already held. |
| `getLinks(): array` | `list<LinkInterface>` in insertion order. |
| `getLinksByRel(string $rel): array` | The links whose `getRels()` contains `$rel` by exact match, in insertion order; an empty list when none match. |
| `withLink(LinkInterface $link): static` | Returns the same instance when an identical link is already held. |
| `withoutLink(LinkInterface $link): static` | Removes by identity; returns the same instance when the link is absent. |

Membership is identity-based, as PSR-13 §3.3 requires. Two separately constructed links with equal state are two
distinct members: adding both keeps both, and removing one leaves the other.

## Exceptions

`Manychois\PhpStrong\Links\InvalidArgumentException` extends `\InvalidArgumentException`. PSR-13 declares no
exception interface, so it implements none.
