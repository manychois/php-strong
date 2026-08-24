# HTTP Cookies Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Give `Manychois\PhpStrong\Http` typed, tested cookie handling in both roles it supports — reading and writing cookies on a server request/response pair, and persisting cookies across PSR-18 client calls.

**Architecture:** One immutable `Cookie` value object owns all RFC 6265 parsing and formatting. Two containers use it and share nothing else: `CookieBag` (mutable, server-side, collect-then-flush) and `CookieStore` (mutable, client-side, in-memory, match-and-attach). `CookieAwareClient` decorates any PSR-18 client to wire the store in. One small hook, `PendingRequest::onResponse()`, is added to existing code so async transfers can absorb cookies on settlement.

**Tech Stack:** PHP 8.5, PSR-7 (`psr/http-message`), PSR-18 (`psr/http-client`), PSR-20 (`psr/clock`), PHPUnit 12, PHPStan max + strict rules, PHPCS with the project ruleset. No new Composer dependencies.

**Spec:** `docs/superpowers/specs/2026-08-25-http-cookies-design.md`

## Global Constraints

- PHP `>=8.5`. No new Composer dependencies — `psr/clock` and `psr/http-client` are already required.
- Every file starts `<?php`, blank line, `declare(strict_types=1);`, blank line, `namespace ...;`.
- Interfaces are aliased on import: `use Psr\Http\Message\ResponseInterface as IResponse;`. Native PHP classes are imported (`use DateTimeImmutable;`), never written fully qualified. Global constants use a leading backslash (`\CURLE_OK`).
- `#[Override]` on every interface implementation. Members implementing an interface live in `#region implements IFoo` … `#endregion implements IFoo` blocks.
- Method order: static before instance; public/protected before private; alphabetical within each group. Public/protected methods that are *not* part of a region go **above** the first `#region`; private methods go **below** the last `#endregion`.
- PHPDoc required on every public and protected method and on every class. One blank line between different annotation types (`@param` then blank then `@return`). Single spaces inside tags — no column alignment. Wrap docblock prose at 120 columns; do not wrap earlier.
- Use plain `int` in `@param`/`@return` for readability and add a separate `@phpstan-param`/`@phpstan-return` for precise types (`non-negative-int`, `list<T>`).
- Tests live in `Manychois\PhpStrongTests\Http`, use `#[Test]` attributes with descriptive camelCase method names, and assert via `static::assertX(...)`.
- Quality gates, run in this order after every task: `composer phpcbf` → `composer phpcs` → `composer phpstan` → `composer test`. All must be clean before committing.
- Coverage target is 100% for every new class, matching the rest of the module.

## File Structure

| File | Responsibility |
|---|---|
| `src/Http/Cookie.php` | Create. Immutable value object; all RFC 6265 parsing and formatting. |
| `src/Http/CookieBag.php` | Create. Server-side collector: read incoming, queue outgoing, flush to a response. |
| `src/Http/CookieStore.php` | Create. Client-side in-memory store: acceptance rules, sending rules, expiry. |
| `src/Http/Internal/CookieEntry.php` | Create. `@internal` storage record for `CookieStore` — resolved cookie, expiry instant, host-only flag, insertion sequence. |
| `src/Http/CookieAwareClient.php` | Create. PSR-18 decorator wiring a `CookieStore` into any client. |
| `src/Http/PendingRequest.php` | Modify. Add `onResponse()` and fire callbacks from `settle()`. |
| `tests/Http/CookieTest.php` | Create. |
| `tests/Http/CookieBagTest.php` | Create. |
| `tests/Http/CookieStoreTest.php` | Create. |
| `tests/Http/CookieAwareClientTest.php` | Create. |
| `tests/Http/PendingRequestTest.php` | Modify. Add `onResponse()` coverage. |
| `docs/http.md` | Modify. New `## Cookies` section. |
| `README.md` | Modify. Mention cookies in the Http module row. |

`Cookie` is split across Tasks 1–3 because its three concerns — construction, formatting, parsing — each carry their own test cycle and a reviewer could reasonably reject one while accepting the others. `CookieStore` is split across Tasks 6–9 for the same reason; its acceptance rules, expiry handling, prefix enforcement, and sending rules are independently reviewable.

---

### Task 1: `Cookie` — construction and validation

**Files:**
- Create: `src/Http/Cookie.php`
- Test: `tests/Http/CookieTest.php`

**Interfaces:**
- Consumes: `Manychois\PhpStrong\Http\SameSite` (existing enum, cases `Lax`, `None`, `Strict`).
- Produces: `final class Cookie` with public readonly properties `$name`, `$value`, `$expires`, `$maxAge`, `$domain`, `$path`, `$secure`, `$httpOnly`, `$sameSite`, `$partitioned`, and `public static function expired(string $name, ?string $domain = null, ?string $path = null): self`.

- [ ] **Step 1: Write the failing test**

Create `tests/Http/CookieTest.php`:

```php
<?php

declare(strict_types=1);

namespace Manychois\PhpStrongTests\Http;

use DateTimeImmutable;
use InvalidArgumentException;
use Manychois\PhpStrong\Http\Cookie;
use Manychois\PhpStrong\Http\SameSite;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for {@see Cookie}.
 */
final class CookieTest extends TestCase
{
    #[Test]
    public function everyAttributeDefaultsToTheUnsetValue(): void
    {
        $cookie = new Cookie('theme', 'dark');

        static::assertSame('theme', $cookie->name);
        static::assertSame('dark', $cookie->value);
        static::assertNull($cookie->expires);
        static::assertNull($cookie->maxAge);
        static::assertNull($cookie->domain);
        static::assertNull($cookie->path);
        static::assertFalse($cookie->secure);
        static::assertFalse($cookie->httpOnly);
        static::assertNull($cookie->sameSite);
        static::assertFalse($cookie->partitioned);
    }

    #[Test]
    public function attributesArePreserved(): void
    {
        $expires = new DateTimeImmutable('2026-08-25 10:00:00');
        $cookie = new Cookie(
            name: 'sid',
            value: 'abc',
            expires: $expires,
            maxAge: 600,
            domain: 'example.com',
            path: '/app',
            secure: true,
            httpOnly: true,
            sameSite: SameSite::Strict,
            partitioned: true,
        );

        static::assertSame($expires, $cookie->expires);
        static::assertSame(600, $cookie->maxAge);
        static::assertSame('example.com', $cookie->domain);
        static::assertSame('/app', $cookie->path);
        static::assertTrue($cookie->secure);
        static::assertTrue($cookie->httpOnly);
        static::assertSame(SameSite::Strict, $cookie->sameSite);
        static::assertTrue($cookie->partitioned);
    }

    #[Test]
    public function anEmptyNameIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cookie name must be a valid token, got "".');

        new Cookie('', 'x');
    }

    #[Test]
    public function aNameWithASeparatorIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cookie name must be a valid token, got "a=b".');

        new Cookie('a=b', 'x');
    }

    #[Test]
    public function aNameWithWhitespaceIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Cookie("a b", 'x');
    }

    #[Test]
    public function anyValueIsAcceptedBecauseValuesAreEncodedOnOutput(): void
    {
        $cookie = new Cookie('t', 'a b;c,d"e');

        static::assertSame('a b;c,d"e', $cookie->value);
    }

    #[Test]
    public function aNegativeMaxAgeIsAcceptedBecauseItExpiresTheCookie(): void
    {
        $cookie = new Cookie('t', '', maxAge: -1);

        static::assertSame(-1, $cookie->maxAge);
    }

    #[Test]
    public function sameSiteNoneRequiresASecureCookie(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('SameSite None requires a secure cookie, which browsers enforce.');

        new Cookie('t', 'v', sameSite: SameSite::None);
    }

    #[Test]
    public function aPartitionedCookieMustBeSecure(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('A partitioned cookie must be secure, which browsers enforce.');

        new Cookie('t', 'v', partitioned: true);
    }

    #[Test]
    public function expiredBuildsACookieThatClearsItself(): void
    {
        $cookie = Cookie::expired('sid', 'example.com', '/app');

        static::assertSame('sid', $cookie->name);
        static::assertSame('', $cookie->value);
        static::assertSame(-1, $cookie->maxAge);
        static::assertNotNull($cookie->expires);
        static::assertSame(0, $cookie->expires->getTimestamp());
        static::assertSame('example.com', $cookie->domain);
        static::assertSame('/app', $cookie->path);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/phpunit tests/Http/CookieTest.php`
Expected: FAIL — `Class "Manychois\PhpStrong\Http\Cookie" not found`.

- [ ] **Step 3: Write minimal implementation**

Create `src/Http/Cookie.php`:

```php
<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\Http;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * One HTTP cookie, as sent in a `Set-Cookie` header.
 *
 * The `$value` held here is always the decoded value; it is `rawurlencode`d when written to a header and
 * `rawurldecode`d when parsed from one. RFC 3986 encoding is used rather than form encoding so that a cookie written
 * here is readable from JavaScript with `decodeURIComponent`, and so that a literal `+` round-trips correctly.
 *
 * Both `Expires` and `Max-Age` are kept when both are given; this object records what it was told, and
 * {@see CookieStore} applies the precedence between them when it computes an actual expiry instant.
 */
final class Cookie
{
    /**
     * The characters a cookie name may consist of, i.e. an RFC 2616 token.
     */
    private const NAME_PATTERN = '/^[!#$%&\'*+\-.^_`|~0-9A-Za-z]+$/';

    /**
     * @param string $name The name of the cookie, which must be a valid token.
     * @param string $value The decoded value of the cookie. Any string is allowed; it is encoded on output.
     * @param ?DateTimeImmutable $expires The instant the cookie expires. `null` leaves the attribute out.
     * @param ?int $maxAge The number of seconds until the cookie expires. A value of `0` or less expires it
     * immediately. `null` leaves the attribute out.
     * @param ?string $domain The domain the cookie is sent to. `null` leaves the attribute out, which makes the
     * cookie host-only.
     * @param ?string $path The path the cookie is sent for. `null` leaves the attribute out.
     * @param bool $secure Whether the cookie is sent over HTTPS only.
     * @param bool $httpOnly Whether the cookie is hidden from JavaScript.
     * @param ?SameSite $sameSite When the browser sends the cookie with a cross-site request.
     * @param bool $partitioned Whether the cookie is partitioned per top-level site (CHIPS).
     *
     * @throws InvalidArgumentException if the name is not a valid token, or an attribute combination is one
     * browsers reject.
     */
    public function __construct(
        public readonly string $name,
        public readonly string $value,
        public readonly ?DateTimeImmutable $expires = null,
        public readonly ?int $maxAge = null,
        public readonly ?string $domain = null,
        public readonly ?string $path = null,
        public readonly bool $secure = false,
        public readonly bool $httpOnly = false,
        public readonly ?SameSite $sameSite = null,
        public readonly bool $partitioned = false,
    ) {
        if (preg_match(self::NAME_PATTERN, $name) !== 1) {
            throw new InvalidArgumentException(sprintf('Cookie name must be a valid token, got "%s".', $name));
        }
        if ($sameSite === SameSite::None && !$secure) {
            throw new InvalidArgumentException('SameSite None requires a secure cookie, which browsers enforce.');
        }
        if ($partitioned && !$secure) {
            throw new InvalidArgumentException('A partitioned cookie must be secure, which browsers enforce.');
        }
    }

    /**
     * Creates a cookie which clears an existing cookie of the same name.
     *
     * Both `Max-Age` and `Expires` are set, because some older browsers honour only one of the two.
     *
     * @param string $name The name of the cookie to clear.
     * @param ?string $domain The domain of the cookie to clear, which must match the one it was set with.
     * @param ?string $path The path of the cookie to clear, which must match the one it was set with.
     *
     * @return self The cookie which clears it.
     */
    public static function expired(string $name, ?string $domain = null, ?string $path = null): self
    {
        return new self(
            name: $name,
            value: '',
            expires: new DateTimeImmutable('@0'),
            maxAge: -1,
            domain: $domain,
            path: $path,
        );
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/phpunit tests/Http/CookieTest.php`
Expected: PASS, 10 tests.

- [ ] **Step 5: Run the quality gates**

Run: `composer phpcbf && composer phpcs && composer phpstan && composer test`
Expected: all clean.

- [ ] **Step 6: Commit**

```bash
git add src/Http/Cookie.php tests/Http/CookieTest.php
git commit -m "feat(http): add the Cookie value object"
```

---

### Task 2: `Cookie::toSetCookieHeader()`

**Files:**
- Modify: `src/Http/Cookie.php`
- Test: `tests/Http/CookieTest.php`

**Interfaces:**
- Consumes: `Cookie` from Task 1.
- Produces: `public function toSetCookieHeader(): string`.

Attribute order is fixed — `Expires`, `Max-Age`, `Domain`, `Path`, `Secure`, `HttpOnly`, `SameSite`, `Partitioned` — so output is deterministic and testable by string equality.

- [ ] **Step 1: Write the failing test**

Append these methods to `tests/Http/CookieTest.php` (add `use DateTimeZone;` to the imports):

```php
    #[Test]
    public function toSetCookieHeaderWritesTheNameAndValueOnly(): void
    {
        $cookie = new Cookie('theme', 'dark');

        static::assertSame('theme=dark', $cookie->toSetCookieHeader());
    }

    #[Test]
    public function toSetCookieHeaderEncodesTheValue(): void
    {
        $cookie = new Cookie('t', 'a b+c%d');

        static::assertSame('t=a%20b%2Bc%25d', $cookie->toSetCookieHeader());
    }

    #[Test]
    public function toSetCookieHeaderWritesEveryAttributeInAFixedOrder(): void
    {
        $cookie = new Cookie(
            name: 'sid',
            value: 'abc',
            expires: new DateTimeImmutable('2026-08-25 10:00:00', new DateTimeZone('UTC')),
            maxAge: 600,
            domain: 'example.com',
            path: '/app',
            secure: true,
            httpOnly: true,
            sameSite: SameSite::Lax,
            partitioned: true,
        );

        static::assertSame(
            'sid=abc; Expires=Tue, 25 Aug 2026 10:00:00 GMT; Max-Age=600; Domain=example.com; Path=/app; '
            . 'Secure; HttpOnly; SameSite=Lax; Partitioned',
            $cookie->toSetCookieHeader()
        );
    }

    #[Test]
    public function toSetCookieHeaderConvertsExpiresToUtc(): void
    {
        $cookie = new Cookie(
            't',
            'v',
            expires: new DateTimeImmutable('2026-08-25 20:00:00', new DateTimeZone('Australia/Sydney')),
        );

        static::assertSame('t=v; Expires=Tue, 25 Aug 2026 10:00:00 GMT', $cookie->toSetCookieHeader());
    }

    #[Test]
    public function toSetCookieHeaderOmitsFalseFlags(): void
    {
        $cookie = new Cookie('t', 'v', secure: false, httpOnly: false);

        static::assertSame('t=v', $cookie->toSetCookieHeader());
    }
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/phpunit tests/Http/CookieTest.php`
Expected: FAIL — `Call to undefined method ...::toSetCookieHeader()`.

- [ ] **Step 3: Write minimal implementation**

Add `use DateTimeZone;` to the imports of `src/Http/Cookie.php`, then add this method. It is a public instance method on a class with no regions, so it goes below `expired()` (static before instance) and above any private members:

```php
    /**
     * Formats this cookie as the value of a `Set-Cookie` header.
     *
     * The value is `rawurlencode`d. `Expires` is written in the IMF-fixdate format browsers require, always
     * converted to UTC first. Attributes left `null` and flags left `false` are omitted.
     *
     * @return string The header value, e.g. `theme=dark; Path=/; Secure; HttpOnly; SameSite=Lax`.
     */
    public function toSetCookieHeader(): string
    {
        $parts = [$this->name . '=' . rawurlencode($this->value)];

        if ($this->expires !== null) {
            $utc = $this->expires->setTimezone(new DateTimeZone('UTC'));
            $parts[] = 'Expires=' . $utc->format('D, d M Y H:i:s \G\M\T');
        }
        if ($this->maxAge !== null) {
            $parts[] = 'Max-Age=' . $this->maxAge;
        }
        if ($this->domain !== null) {
            $parts[] = 'Domain=' . $this->domain;
        }
        if ($this->path !== null) {
            $parts[] = 'Path=' . $this->path;
        }
        if ($this->secure) {
            $parts[] = 'Secure';
        }
        if ($this->httpOnly) {
            $parts[] = 'HttpOnly';
        }
        if ($this->sameSite !== null) {
            $parts[] = 'SameSite=' . $this->sameSite->value;
        }
        if ($this->partitioned) {
            $parts[] = 'Partitioned';
        }

        return implode('; ', $parts);
    }
```

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/phpunit tests/Http/CookieTest.php`
Expected: PASS, 15 tests.

- [ ] **Step 5: Run the quality gates**

Run: `composer phpcbf && composer phpcs && composer phpstan && composer test`
Expected: all clean.

- [ ] **Step 6: Commit**

```bash
git add src/Http/Cookie.php tests/Http/CookieTest.php
git commit -m "feat(http): format a Cookie as a Set-Cookie header"
```

---

### Task 3: `Cookie::parseSetCookie()`

**Files:**
- Modify: `src/Http/Cookie.php`
- Test: `tests/Http/CookieTest.php`

**Interfaces:**
- Consumes: `Cookie` from Tasks 1–2.
- Produces: `public static function parseSetCookie(string $header): self`.

Unknown attributes are ignored, as RFC 6265 mandates — that is how new cookie attributes get adopted without breaking existing parsers. An unparseable `Expires` leaves `expires` null rather than throwing; only a first pair with no `=` throws.

Note the interaction with Task 1's validation: a header carrying `SameSite=None` without `Secure` makes the constructor throw. That is intentional — browsers reject that combination too, and `CookieStore` (Task 6) catches `InvalidArgumentException` and skips such a cookie.

- [ ] **Step 1: Write the failing test**

Append to `tests/Http/CookieTest.php`:

```php
    #[Test]
    public function parseSetCookieReadsTheNameAndValue(): void
    {
        $cookie = Cookie::parseSetCookie('theme=dark');

        static::assertSame('theme', $cookie->name);
        static::assertSame('dark', $cookie->value);
    }

    #[Test]
    public function parseSetCookieDecodesTheValue(): void
    {
        $cookie = Cookie::parseSetCookie('t=a%20b%2Bc%25d');

        static::assertSame('a b+c%d', $cookie->value);
    }

    #[Test]
    public function parseSetCookieStripsSurroundingQuotesFromTheValue(): void
    {
        $cookie = Cookie::parseSetCookie('t="quoted"');

        static::assertSame('quoted', $cookie->value);
    }

    #[Test]
    public function parseSetCookieReadsEveryAttributeCaseInsensitively(): void
    {
        $cookie = Cookie::parseSetCookie(
            'sid=abc; expires=Tue, 25 Aug 2026 10:00:00 GMT; MAX-AGE=600; Domain=example.com; path=/app; '
            . 'secure; httponly; samesite=lax; PARTITIONED'
        );

        static::assertSame('sid', $cookie->name);
        static::assertNotNull($cookie->expires);
        static::assertSame('2026-08-25T10:00:00+00:00', $cookie->expires->format('c'));
        static::assertSame(600, $cookie->maxAge);
        static::assertSame('example.com', $cookie->domain);
        static::assertSame('/app', $cookie->path);
        static::assertTrue($cookie->secure);
        static::assertTrue($cookie->httpOnly);
        static::assertSame(SameSite::Lax, $cookie->sameSite);
        static::assertTrue($cookie->partitioned);
    }

    #[Test]
    public function parseSetCookieIgnoresUnknownAttributes(): void
    {
        $cookie = Cookie::parseSetCookie('t=v; Comment=hello; Version=1; Path=/');

        static::assertSame('/', $cookie->path);
        static::assertSame('v', $cookie->value);
    }

    #[Test]
    public function parseSetCookieKeepsBothExpiresAndMaxAge(): void
    {
        $cookie = Cookie::parseSetCookie('t=v; Expires=Tue, 25 Aug 2026 10:00:00 GMT; Max-Age=60');

        static::assertNotNull($cookie->expires);
        static::assertSame(60, $cookie->maxAge);
    }

    #[Test]
    public function parseSetCookieIgnoresAnUnparseableExpires(): void
    {
        $cookie = Cookie::parseSetCookie('t=v; Expires=not-a-date');

        static::assertNull($cookie->expires);
    }

    #[Test]
    public function parseSetCookieIgnoresANonNumericMaxAge(): void
    {
        $cookie = Cookie::parseSetCookie('t=v; Max-Age=soon');

        static::assertNull($cookie->maxAge);
    }

    #[Test]
    public function parseSetCookieIgnoresAnUnknownSameSiteValue(): void
    {
        $cookie = Cookie::parseSetCookie('t=v; SameSite=sideways');

        static::assertNull($cookie->sameSite);
    }

    #[Test]
    public function parseSetCookieRejectsAHeaderWithNoNameValuePair(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Set-Cookie header must begin with a name=value pair, got "nonsense".');

        Cookie::parseSetCookie('nonsense');
    }

    #[Test]
    public function parseSetCookieRoundTripsWithToSetCookieHeader(): void
    {
        $original = new Cookie(
            name: 'sid',
            value: 'a b/c',
            maxAge: 600,
            domain: 'example.com',
            path: '/app',
            secure: true,
            httpOnly: true,
            sameSite: SameSite::Strict,
        );

        $parsed = Cookie::parseSetCookie($original->toSetCookieHeader());

        static::assertSame($original->name, $parsed->name);
        static::assertSame($original->value, $parsed->value);
        static::assertSame($original->maxAge, $parsed->maxAge);
        static::assertSame($original->domain, $parsed->domain);
        static::assertSame($original->path, $parsed->path);
        static::assertTrue($parsed->secure);
        static::assertTrue($parsed->httpOnly);
        static::assertSame(SameSite::Strict, $parsed->sameSite);
    }
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/phpunit tests/Http/CookieTest.php`
Expected: FAIL — `Call to undefined method ...::parseSetCookie()`.

- [ ] **Step 3: Write minimal implementation**

Add `use Exception;` to the imports of `src/Http/Cookie.php`. Add `parseSetCookie()` as a static method — it sorts alphabetically before `expired()`, so place it first among the statics — and add the two private helpers **below** all public methods:

```php
    /**
     * Parses one `Set-Cookie` header value.
     *
     * Attribute names are matched case-insensitively and unknown attributes are ignored, as RFC 6265 requires. An
     * `Expires` which cannot be parsed, a non-numeric `Max-Age` and an unrecognised `SameSite` are each ignored
     * rather than treated as errors, since a remote server's malformed attribute should not fail the whole header.
     *
     * @param string $header The header value to parse, without the `Set-Cookie:` name.
     *
     * @return self The parsed cookie.
     *
     * @throws InvalidArgumentException if the header does not begin with a `name=value` pair, or if the attributes
     * describe a combination browsers reject.
     */
    public static function parseSetCookie(string $header): self
    {
        $segments = explode(';', $header);
        $first = array_shift($segments);
        $equals = strpos($first, '=');
        if ($equals === false) {
            throw new InvalidArgumentException(
                sprintf('Set-Cookie header must begin with a name=value pair, got "%s".', trim($header))
            );
        }

        $name = trim(substr($first, 0, $equals));
        $raw = trim(substr($first, $equals + 1));
        if (strlen($raw) >= 2 && str_starts_with($raw, '"') && str_ends_with($raw, '"')) {
            $raw = substr($raw, 1, -1);
        }

        $expires = null;
        $maxAge = null;
        $domain = null;
        $path = null;
        $secure = false;
        $httpOnly = false;
        $sameSite = null;
        $partitioned = false;

        foreach ($segments as $segment) {
            [$key, $attrValue] = self::splitAttribute($segment);
            switch ($key) {
                case 'expires':
                    $expires = self::parseDate($attrValue);
                    break;
                case 'max-age':
                    if (preg_match('/^-?\d+$/', $attrValue) === 1) {
                        $maxAge = (int) $attrValue;
                    }
                    break;
                case 'domain':
                    $domain = $attrValue;
                    break;
                case 'path':
                    $path = $attrValue;
                    break;
                case 'secure':
                    $secure = true;
                    break;
                case 'httponly':
                    $httpOnly = true;
                    break;
                case 'samesite':
                    $sameSite = SameSite::tryFrom(ucfirst(strtolower($attrValue)));
                    break;
                case 'partitioned':
                    $partitioned = true;
                    break;
                default:
                    break;
            }
        }

        return new self(
            name: $name,
            value: rawurldecode($raw),
            expires: $expires,
            maxAge: $maxAge,
            domain: $domain,
            path: $path,
            secure: $secure,
            httpOnly: $httpOnly,
            sameSite: $sameSite,
            partitioned: $partitioned,
        );
    }
```

And below `toSetCookieHeader()`, at the bottom of the class:

```php
    /**
     * Parses a cookie date, leniently, since browsers accept several formats in practice.
     *
     * @param string $value The date to parse.
     *
     * @return ?DateTimeImmutable The parsed instant, or `null` if it could not be parsed.
     */
    private static function parseDate(string $value): ?DateTimeImmutable
    {
        if ($value === '') {
            return null;
        }

        try {
            return new DateTimeImmutable($value);
        } catch (Exception) {
            return null;
        }
    }

    /**
     * Splits one attribute segment into its lower-cased name and its trimmed value.
     *
     * @param string $segment The segment to split.
     *
     * @return array The name and the value; the value is `''` for a bare flag.
     *
     * @phpstan-return array{string,string}
     */
    private static function splitAttribute(string $segment): array
    {
        $equals = strpos($segment, '=');
        if ($equals === false) {
            return [strtolower(trim($segment)), ''];
        }

        return [strtolower(trim(substr($segment, 0, $equals))), trim(substr($segment, $equals + 1))];
    }
```

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/phpunit tests/Http/CookieTest.php`
Expected: PASS, 26 tests.

- [ ] **Step 5: Run the quality gates**

Run: `composer phpcbf && composer phpcs && composer phpstan && composer test`
Expected: all clean.

- [ ] **Step 6: Commit**

```bash
git add src/Http/Cookie.php tests/Http/CookieTest.php
git commit -m "feat(http): parse a Set-Cookie header into a Cookie"
```

---

### Task 4: `CookieBag` — reading incoming cookies

**Files:**
- Create: `src/Http/CookieBag.php`
- Test: `tests/Http/CookieBagTest.php`

**Interfaces:**
- Consumes: `Cookie` (Tasks 1–3); `Psr\Http\Message\ServerRequestInterface as IServerRequest`.
- Produces: `CookieBag::fromRequest(IServerRequest $request): self`, `all(): array<string,string>`, `get(string $name): ?string`, `has(string $name): bool`.

**Critical rule:** `fromRequest()` must **not** decode. PHP has already urldecoded `$_COOKIE`, which is what `getCookieParams()` ultimately returns (`ServerRequest.php:155`). Decoding again would turn a stored `100%25` into `100%`, and a stored `%41` into `A`.

`get()` returns `?string` rather than `?Cookie` on purpose: an incoming cookie carries only a name and a value, so a `Cookie` here would be a value object with eight meaningless nulls.

- [ ] **Step 1: Write the failing test**

Create `tests/Http/CookieBagTest.php`:

```php
<?php

declare(strict_types=1);

namespace Manychois\PhpStrongTests\Http;

use Manychois\PhpStrong\Http\CookieBag;
use Manychois\PhpStrong\Http\ServerRequest;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for {@see CookieBag}.
 */
final class CookieBagTest extends TestCase
{
    #[Test]
    public function fromRequestReadsTheCookieParams(): void
    {
        $request = new ServerRequest(cookieParams: ['theme' => 'dark', 'sid' => 'abc']);

        $bag = CookieBag::fromRequest($request);

        static::assertSame(['theme' => 'dark', 'sid' => 'abc'], $bag->all());
        static::assertSame('dark', $bag->get('theme'));
        static::assertTrue($bag->has('sid'));
    }

    #[Test]
    public function getReturnsNullForAnAbsentCookie(): void
    {
        $bag = CookieBag::fromRequest(new ServerRequest());

        static::assertNull($bag->get('nope'));
        static::assertFalse($bag->has('nope'));
    }

    #[Test]
    public function fromRequestSkipsNonStringKeysAndValues(): void
    {
        $request = new ServerRequest(cookieParams: [
            'good' => 'yes',
            7 => 'numeric key',
            'array' => ['not', 'a', 'string'],
            'null' => null,
        ]);

        $bag = CookieBag::fromRequest($request);

        static::assertSame(['good' => 'yes'], $bag->all());
    }

    #[Test]
    public function fromRequestDoesNotDecodeBecausePhpAlreadyDid(): void
    {
        $request = new ServerRequest(cookieParams: ['pct' => '100%25', 'hex' => '%41']);

        $bag = CookieBag::fromRequest($request);

        static::assertSame('100%25', $bag->get('pct'));
        static::assertSame('%41', $bag->get('hex'));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/phpunit tests/Http/CookieBagTest.php`
Expected: FAIL — `Class "Manychois\PhpStrong\Http\CookieBag" not found`.

- [ ] **Step 3: Write minimal implementation**

Create `src/Http/CookieBag.php`:

```php
<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\Http;

use Psr\Http\Message\ServerRequestInterface as IServerRequest;

/**
 * Reads the cookies which arrived on a server request and collects the ones to send back.
 *
 * This class is mutable on purpose: one instance is created per request and shared across middleware and handlers,
 * so setting a cookie deep in the stack does not require threading a modified object back out. `applyTo()` is the
 * single point where the bag crosses back into immutable PSR-7 territory.
 *
 * Incoming values are taken exactly as PSR-7 reports them and are never decoded, because PHP has already decoded
 * `$_COOKIE`. Outgoing values are encoded by {@see Cookie}.
 */
final class CookieBag
{
    /**
     * @var array<string,string>
     */
    private array $incoming = [];

    /**
     * Creates a bag holding the cookies which arrived on the given request.
     *
     * Entries of `getCookieParams()` which are not a string key with a string value are skipped, since PSR-7
     * leaves that array untyped.
     *
     * @param IServerRequest $request The request to read the cookies from.
     *
     * @return self The bag of incoming cookies.
     */
    public static function fromRequest(IServerRequest $request): self
    {
        $bag = new self();
        foreach ($request->getCookieParams() as $name => $value) {
            if (is_string($name) && is_string($value)) {
                $bag->incoming[$name] = $value;
            }
        }

        return $bag;
    }

    /**
     * Returns every cookie which arrived on the request.
     *
     * @return array The incoming cookies, keyed by name.
     *
     * @phpstan-return array<string,string>
     */
    public function all(): array
    {
        return $this->incoming;
    }

    /**
     * Returns the value of an incoming cookie.
     *
     * @param string $name The name of the cookie.
     *
     * @return ?string The value, or `null` if the request carried no such cookie.
     */
    public function get(string $name): ?string
    {
        return $this->incoming[$name] ?? null;
    }

    /**
     * Tells whether an incoming cookie of the given name exists.
     *
     * @param string $name The name of the cookie.
     *
     * @return bool True if the request carried the cookie.
     */
    public function has(string $name): bool
    {
        return array_key_exists($name, $this->incoming);
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/phpunit tests/Http/CookieBagTest.php`
Expected: PASS, 4 tests.

- [ ] **Step 5: Run the quality gates**

Run: `composer phpcbf && composer phpcs && composer phpstan && composer test`
Expected: all clean.

- [ ] **Step 6: Commit**

```bash
git add src/Http/CookieBag.php tests/Http/CookieBagTest.php
git commit -m "feat(http): read incoming cookies with CookieBag"
```

---

### Task 5: `CookieBag` — queueing and flushing outgoing cookies

**Files:**
- Modify: `src/Http/CookieBag.php`
- Test: `tests/Http/CookieBagTest.php`

**Interfaces:**
- Consumes: `CookieBag` (Task 4), `Cookie` (Tasks 1–3), `Psr\Http\Message\ResponseInterface as IResponse`.
- Produces: `set(Cookie $cookie): void`, `expire(string $name, ?string $domain = null, ?string $path = null): void`, `queued(): list<Cookie>`, `applyTo(IResponse $response): IResponse`.

The dedupe key is **name + domain + path**, matching how a browser identifies a cookie. `applyTo()` uses `withAddedHeader()`, never `withHeader()` — `Set-Cookie` is the header where multiple values are normal and replacement loses data.

- [ ] **Step 1: Write the failing test**

Append to `tests/Http/CookieBagTest.php` (add `use Manychois\PhpStrong\Http\Cookie;` and `use Manychois\PhpStrong\Http\Response;` to the imports):

```php
    #[Test]
    public function applyToAppendsOneHeaderPerQueuedCookie(): void
    {
        $bag = CookieBag::fromRequest(new ServerRequest());
        $bag->set(new Cookie('theme', 'dark'));
        $bag->set(new Cookie('lang', 'en', path: '/'));

        $response = $bag->applyTo(new Response());

        static::assertSame(['theme=dark', 'lang=en; Path=/'], $response->getHeader('Set-Cookie'));
    }

    #[Test]
    public function applyToPreservesASetCookieHeaderAlreadyOnTheResponse(): void
    {
        $bag = CookieBag::fromRequest(new ServerRequest());
        $bag->set(new Cookie('theme', 'dark'));

        $response = $bag->applyTo(new Response(headers: ['Set-Cookie' => 'existing=1']));

        static::assertSame(['existing=1', 'theme=dark'], $response->getHeader('Set-Cookie'));
    }

    #[Test]
    public function setReplacesACookieWithTheSameNameDomainAndPath(): void
    {
        $bag = CookieBag::fromRequest(new ServerRequest());
        $bag->set(new Cookie('theme', 'dark', path: '/'));
        $bag->set(new Cookie('theme', 'light', path: '/'));

        static::assertCount(1, $bag->queued());
        static::assertSame(['theme=light; Path=/'], $bag->applyTo(new Response())->getHeader('Set-Cookie'));
    }

    #[Test]
    public function setKeepsCookiesWhichDifferByPathOrDomain(): void
    {
        $bag = CookieBag::fromRequest(new ServerRequest());
        $bag->set(new Cookie('theme', 'dark', path: '/'));
        $bag->set(new Cookie('theme', 'light', path: '/admin'));
        $bag->set(new Cookie('theme', 'blue', domain: 'example.com', path: '/'));

        static::assertCount(3, $bag->queued());
    }

    #[Test]
    public function expireQueuesAClearingCookie(): void
    {
        $bag = CookieBag::fromRequest(new ServerRequest());
        $bag->expire('sid', 'example.com', '/app');

        $header = $bag->applyTo(new Response())->getHeader('Set-Cookie');

        static::assertCount(1, $header);
        static::assertStringContainsString('sid=;', $header[0]);
        static::assertStringContainsString('Max-Age=-1', $header[0]);
        static::assertStringContainsString('Expires=Thu, 01 Jan 1970 00:00:00 GMT', $header[0]);
        static::assertStringContainsString('Domain=example.com', $header[0]);
        static::assertStringContainsString('Path=/app', $header[0]);
    }

    #[Test]
    public function setThenExpireCollapsesToASingleExpiryHeader(): void
    {
        $bag = CookieBag::fromRequest(new ServerRequest());
        $bag->set(new Cookie('sid', 'abc', domain: 'example.com', path: '/app'));
        $bag->expire('sid', 'example.com', '/app');

        $header = $bag->applyTo(new Response())->getHeader('Set-Cookie');

        static::assertCount(1, $header);
        static::assertStringContainsString('Max-Age=-1', $header[0]);
    }

    #[Test]
    public function applyToLeavesTheResponseAloneWhenNothingIsQueued(): void
    {
        $bag = CookieBag::fromRequest(new ServerRequest());
        $response = new Response();

        static::assertFalse($bag->applyTo($response)->hasHeader('Set-Cookie'));
    }
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/phpunit tests/Http/CookieBagTest.php`
Expected: FAIL — `Call to undefined method ...::set()`.

- [ ] **Step 3: Write minimal implementation**

Add `use Psr\Http\Message\ResponseInterface as IResponse;` to the imports of `src/Http/CookieBag.php` and add the `$outgoing` property below `$incoming`:

```php
    /**
     * @var array<string,Cookie>
     */
    private array $outgoing = [];
```

Add these public instance methods, keeping the class alphabetical — `all()`, `applyTo()`, `expire()`, `get()`, `has()`, `queued()`, `set()` — and the private helper at the bottom:

```php
    /**
     * Adds one `Set-Cookie` header to the response for each queued cookie.
     *
     * Headers are appended, never replaced, because `Set-Cookie` is the header where multiple values are normal.
     *
     * @param IResponse $response The response to write the cookies to.
     *
     * @return IResponse The response carrying the cookies.
     */
    public function applyTo(IResponse $response): IResponse
    {
        foreach ($this->outgoing as $cookie) {
            $response = $response->withAddedHeader('Set-Cookie', $cookie->toSetCookieHeader());
        }

        return $response;
    }

    /**
     * Queues a cookie which clears an existing cookie of the given name.
     *
     * The domain and path must match the ones the cookie was set with, or the browser will clear nothing.
     *
     * @param string $name The name of the cookie to clear.
     * @param ?string $domain The domain the cookie was set with.
     * @param ?string $path The path the cookie was set with.
     */
    public function expire(string $name, ?string $domain = null, ?string $path = null): void
    {
        $this->set(Cookie::expired($name, $domain, $path));
    }

    /**
     * Returns the cookies queued to be sent back.
     *
     * @return array The queued cookies.
     *
     * @phpstan-return list<Cookie>
     */
    public function queued(): array
    {
        return array_values($this->outgoing);
    }

    /**
     * Queues a cookie to be sent back on the response.
     *
     * A cookie already queued with the same name, domain and path is replaced, which is how a browser identifies a
     * cookie; this keeps a handler overriding a middleware's cookie from emitting two contradictory headers.
     *
     * @param Cookie $cookie The cookie to send.
     */
    public function set(Cookie $cookie): void
    {
        $this->outgoing[self::keyOf($cookie)] = $cookie;
    }
```

And at the bottom of the class:

```php
    /**
     * Builds the key a cookie is deduplicated by, i.e. how a browser identifies it.
     *
     * @param Cookie $cookie The cookie to key.
     *
     * @return string The key.
     */
    private static function keyOf(Cookie $cookie): string
    {
        return $cookie->name . "\0" . ($cookie->domain ?? '') . "\0" . ($cookie->path ?? '');
    }
```

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/phpunit tests/Http/CookieBagTest.php`
Expected: PASS, 11 tests.

- [ ] **Step 5: Run the quality gates**

Run: `composer phpcbf && composer phpcs && composer phpstan && composer test`
Expected: all clean.

- [ ] **Step 6: Commit**

```bash
git add src/Http/CookieBag.php tests/Http/CookieBagTest.php
git commit -m "feat(http): queue and flush outgoing cookies with CookieBag"
```

---

### Task 6: `CookieStore` — storage, domain and path resolution

**Files:**
- Create: `src/Http/Internal/CookieEntry.php`
- Create: `src/Http/CookieStore.php`
- Test: `tests/Http/CookieStoreTest.php`

**Interfaces:**
- Consumes: `Cookie` (Tasks 1–3); `Psr\Clock\ClockInterface as IClock`; `Manychois\PhpStrong\Time\UtcClock`; `Psr\Http\Message\{ResponseInterface as IResponse, UriInterface as IUri}`.
- Produces: `final class CookieStore` with `__construct(IClock $clock = new UtcClock())`, `absorb(IResponse $response, IUri $requestUri): void`, `all(): list<Cookie>`, `clear(): void`. Internal record `Manychois\PhpStrong\Http\Internal\CookieEntry` with public readonly `$cookie`, `$expiresAt`, `$hostOnly`, `$sequence`.

Expiry is added in Task 7; this task stores everything with `$expiresAt = null`.

The `Cookie` an entry holds is the **resolved** one: where the response omitted `Domain` or `Path`, the entry stores a `Cookie` carrying the defaults derived from the request URI, not the nulls as received. So `all()` returns cookies whose `domain` and `path` are always populated.

**Documented limitation:** without a public suffix list, `Domain=co.uk` from a `foo.co.uk` response is accepted where a browser would refuse it. This goes in the class docblock, not left implicit.

- [ ] **Step 1: Write the failing test**

Create `tests/Http/CookieStoreTest.php`:

```php
<?php

declare(strict_types=1);

namespace Manychois\PhpStrongTests\Http;

use Manychois\PhpStrong\Http\CookieStore;
use Manychois\PhpStrong\Http\Response;
use Manychois\PhpStrong\Http\Uri;
use Manychois\PhpStrong\Time\TestClock;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for {@see CookieStore}.
 */
final class CookieStoreTest extends TestCase
{
    #[Test]
    public function absorbStoresACookieAsHostOnlyWhenNoDomainIsGiven(): void
    {
        $store = new CookieStore(new TestClock('2026-08-25 00:00:00'));

        $store->absorb($this->responseWith('sid=abc'), Uri::fromString('https://api.example.com/v1/login'));

        $all = $store->all();
        static::assertCount(1, $all);
        static::assertSame('sid', $all[0]->name);
        static::assertSame('abc', $all[0]->value);
        static::assertSame('api.example.com', $all[0]->domain);
        static::assertSame('/v1', $all[0]->path);
    }

    #[Test]
    public function absorbAcceptsADomainWhichMatchesTheRequestHost(): void
    {
        $store = new CookieStore(new TestClock('2026-08-25 00:00:00'));

        $store->absorb(
            $this->responseWith('sid=abc; Domain=example.com'),
            Uri::fromString('https://api.example.com/')
        );

        static::assertSame('example.com', $store->all()[0]->domain);
    }

    #[Test]
    public function absorbStripsALeadingDotFromTheDomain(): void
    {
        $store = new CookieStore(new TestClock('2026-08-25 00:00:00'));

        $store->absorb(
            $this->responseWith('sid=abc; Domain=.example.com'),
            Uri::fromString('https://api.example.com/')
        );

        static::assertSame('example.com', $store->all()[0]->domain);
    }

    #[Test]
    public function absorbRejectsADomainWhichIsNotASuffixOfTheRequestHost(): void
    {
        $store = new CookieStore(new TestClock('2026-08-25 00:00:00'));

        $store->absorb(
            $this->responseWith('sid=abc; Domain=other.com'),
            Uri::fromString('https://api.example.com/')
        );

        static::assertSame([], $store->all());
    }

    #[Test]
    public function absorbRejectsASingleLabelDomain(): void
    {
        $store = new CookieStore(new TestClock('2026-08-25 00:00:00'));

        $store->absorb($this->responseWith('sid=abc; Domain=com'), Uri::fromString('https://api.example.com/'));

        static::assertSame([], $store->all());
    }

    #[Test]
    public function absorbUsesTheExplicitPathWhenGiven(): void
    {
        $store = new CookieStore(new TestClock('2026-08-25 00:00:00'));

        $store->absorb(
            $this->responseWith('sid=abc; Path=/admin'),
            Uri::fromString('https://example.com/v1/login')
        );

        static::assertSame('/admin', $store->all()[0]->path);
    }

    #[Test]
    public function absorbFallsBackToTheDefaultPathForARootRequest(): void
    {
        $store = new CookieStore(new TestClock('2026-08-25 00:00:00'));

        $store->absorb($this->responseWith('sid=abc'), Uri::fromString('https://example.com/login'));

        static::assertSame('/', $store->all()[0]->path);
    }

    #[Test]
    public function absorbIgnoresAPathWhichIsNotAbsolute(): void
    {
        $store = new CookieStore(new TestClock('2026-08-25 00:00:00'));

        $store->absorb(
            $this->responseWith('sid=abc; Path=relative'),
            Uri::fromString('https://example.com/v1/login')
        );

        static::assertSame('/v1', $store->all()[0]->path);
    }

    #[Test]
    public function absorbSkipsAMalformedSetCookieHeader(): void
    {
        $store = new CookieStore(new TestClock('2026-08-25 00:00:00'));

        $store->absorb($this->responseWith('nonsense'), Uri::fromString('https://example.com/'));

        static::assertSame([], $store->all());
    }

    #[Test]
    public function absorbSkipsACookieWhoseAttributesBrowsersReject(): void
    {
        $store = new CookieStore(new TestClock('2026-08-25 00:00:00'));

        $store->absorb($this->responseWith('sid=abc; SameSite=None'), Uri::fromString('https://example.com/'));

        static::assertSame([], $store->all());
    }

    #[Test]
    public function absorbReadsEverySetCookieHeader(): void
    {
        $store = new CookieStore(new TestClock('2026-08-25 00:00:00'));
        $response = new Response(headers: ['Set-Cookie' => ['a=1', 'b=2']]);

        $store->absorb($response, Uri::fromString('https://example.com/'));

        static::assertCount(2, $store->all());
    }

    #[Test]
    public function absorbReplacesACookieWithTheSameDomainPathAndName(): void
    {
        $store = new CookieStore(new TestClock('2026-08-25 00:00:00'));
        $uri = Uri::fromString('https://example.com/');

        $store->absorb($this->responseWith('sid=first'), $uri);
        $store->absorb($this->responseWith('sid=second'), $uri);

        static::assertCount(1, $store->all());
        static::assertSame('second', $store->all()[0]->value);
    }

    #[Test]
    public function clearEmptiesTheStore(): void
    {
        $store = new CookieStore(new TestClock('2026-08-25 00:00:00'));
        $store->absorb($this->responseWith('sid=abc'), Uri::fromString('https://example.com/'));

        $store->clear();

        static::assertSame([], $store->all());
    }

    private function responseWith(string $setCookie): Response
    {
        return new Response(headers: ['Set-Cookie' => $setCookie]);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/phpunit tests/Http/CookieStoreTest.php`
Expected: FAIL — `Class "Manychois\PhpStrong\Http\CookieStore" not found`.

- [ ] **Step 3: Write the internal storage record**

Create `src/Http/Internal/CookieEntry.php`:

```php
<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\Http\Internal;

use DateTimeImmutable;
use Manychois\PhpStrong\Http\Cookie;

/**
 * One cookie held by a cookie store, together with everything the store needs which the cookie itself does not
 * carry: when it actually expires, whether it is bound to one exact host, and the order it arrived in.
 *
 * @internal
 */
final class CookieEntry
{
    /**
     * @param Cookie $cookie The cookie, with its domain and path resolved against the request it arrived on.
     * @param ?DateTimeImmutable $expiresAt The instant it expires, or `null` for a session cookie.
     * @param bool $hostOnly Whether it is sent only to the exact host which set it.
     * @param int $sequence The order it was stored in, used to break ties when ordering cookies for sending.
     */
    public function __construct(
        public readonly Cookie $cookie,
        public readonly ?DateTimeImmutable $expiresAt,
        public readonly bool $hostOnly,
        public readonly int $sequence,
    ) {
    }
}
```

- [ ] **Step 4: Write the store**

Create `src/Http/CookieStore.php`:

```php
<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\Http;

use InvalidArgumentException;
use Manychois\PhpStrong\Http\Internal\CookieEntry;
use Manychois\PhpStrong\Time\UtcClock;
use Psr\Clock\ClockInterface as IClock;
use Psr\Http\Message\ResponseInterface as IResponse;
use Psr\Http\Message\UriInterface as IUri;

/**
 * Remembers the cookies a remote host has set, so that later requests to it carry them back.
 *
 * This is the client-side counterpart to {@see CookieBag}: use it when this application is the one calling a remote
 * host, typically wired in through {@see CookieAwareClient}. Storage is in memory and lives as long as the instance.
 *
 * A cookie a remote host sends which breaks the rules of RFC 6265 is skipped silently rather than throwing, because
 * a third party's bad header is not an error in the calling code and should not fail an otherwise good request.
 *
 * Limitation: without a public suffix list this store accepts `Domain=co.uk` from a response served by
 * `foo.co.uk`, where a browser would refuse it. Bundling and refreshing such a list is ongoing maintenance which a
 * library with no other data dependencies should not take on lightly, and the client role here targets hosts the
 * application chose to call rather than arbitrary ones.
 */
final class CookieStore
{
    /**
     * @var array<string,CookieEntry>
     */
    private array $entries = [];
    private int $sequence = 0;

    /**
     * Initializes a new instance of the CookieStore class.
     *
     * @param IClock $clock The clock which decides whether a cookie has expired.
     */
    public function __construct(private readonly IClock $clock = new UtcClock())
    {
    }

    /**
     * Stores every cookie the response sets which the rules of RFC 6265 allow.
     *
     * The request URI is needed because a response carries none of its own, and both the domain and the path a
     * cookie defaults to are derived from it.
     *
     * @param IResponse $response The response to read `Set-Cookie` headers from.
     * @param IUri $requestUri The URI the request was sent to.
     */
    public function absorb(IResponse $response, IUri $requestUri): void
    {
        $host = strtolower($requestUri->getHost());
        foreach ($response->getHeader('Set-Cookie') as $line) {
            try {
                $cookie = Cookie::parseSetCookie($line);
            } catch (InvalidArgumentException) {
                continue;
            }

            $this->store($cookie, $host, $requestUri->getPath());
        }
    }

    /**
     * Returns every cookie currently held, with its domain and path resolved.
     *
     * @return array The stored cookies.
     *
     * @phpstan-return list<Cookie>
     */
    public function all(): array
    {
        return array_values(array_map(static fn (CookieEntry $e): Cookie => $e->cookie, $this->entries));
    }

    /**
     * Forgets every cookie held.
     */
    public function clear(): void
    {
        $this->entries = [];
    }

    /**
     * Works out the path a cookie defaults to, i.e. the request path up to and excluding its last slash.
     *
     * @param string $requestPath The path of the request the cookie arrived on.
     *
     * @return string The default path.
     */
    private static function defaultPath(string $requestPath): string
    {
        if ($requestPath === '' || !str_starts_with($requestPath, '/')) {
            return '/';
        }

        $slash = strrpos($requestPath, '/');
        if ($slash === 0) {
            return '/';
        }

        return substr($requestPath, 0, $slash);
    }

    /**
     * Applies the acceptance rules of RFC 6265 and stores the cookie if it passes them.
     *
     * @param Cookie $cookie The cookie as the response sent it.
     * @param string $host The lower-cased host the request was sent to.
     * @param string $requestPath The path the request was sent to.
     */
    private function store(Cookie $cookie, string $host, string $requestPath): void
    {
        $hostOnly = true;
        $domain = $host;
        if ($cookie->domain !== null && $cookie->domain !== '') {
            $candidate = strtolower(ltrim($cookie->domain, '.'));
            if (!str_contains($candidate, '.')) {
                return;
            }
            if ($candidate !== $host && !str_ends_with($host, '.' . $candidate)) {
                return;
            }

            $domain = $candidate;
            $hostOnly = false;
        }

        $path = $cookie->path;
        if ($path === null || !str_starts_with($path, '/')) {
            $path = self::defaultPath($requestPath);
        }

        $resolved = new Cookie(
            name: $cookie->name,
            value: $cookie->value,
            expires: $cookie->expires,
            maxAge: $cookie->maxAge,
            domain: $domain,
            path: $path,
            secure: $cookie->secure,
            httpOnly: $cookie->httpOnly,
            sameSite: $cookie->sameSite,
            partitioned: $cookie->partitioned,
        );

        $key = $domain . "\0" . $path . "\0" . $cookie->name;
        $this->entries[$key] = new CookieEntry($resolved, null, $hostOnly, $this->sequence);
        $this->sequence++;
    }
}
```

Note `$this->clock` is unused until Task 7; PHPStan's `max` level does not flag an unused private promoted property that is read nowhere yet, but if it does, complete Task 7 before running the gates.

- [ ] **Step 5: Run test to verify it passes**

Run: `./vendor/bin/phpunit tests/Http/CookieStoreTest.php`
Expected: PASS, 13 tests.

- [ ] **Step 6: Run the quality gates**

Run: `composer phpcbf && composer phpcs && composer phpstan && composer test`
Expected: all clean.

- [ ] **Step 7: Commit**

```bash
git add src/Http/CookieStore.php src/Http/Internal/CookieEntry.php tests/Http/CookieStoreTest.php
git commit -m "feat(http): store client cookies with domain and path resolution"
```

---

### Task 7: `CookieStore` — expiry

**Files:**
- Modify: `src/Http/CookieStore.php`
- Test: `tests/Http/CookieStoreTest.php`

**Interfaces:**
- Consumes: `CookieStore` (Task 6), `CookieEntry` (Task 6), `Manychois\PhpStrong\Time\TestClock` in tests.
- Produces: no new public methods. `absorb()` and `all()` now prune expired entries; `CookieEntry::$expiresAt` is populated.

Rules: `Max-Age` beats `Expires` when both are present. `Max-Age <= 0` deletes any existing entry immediately. An `Expires` already in the past does the same. Neither attribute means a session cookie, alive for the lifetime of the store. Pruning is lazy — on `absorb()` and `all()` — never on a timer.

- [ ] **Step 1: Write the failing test**

Append to `tests/Http/CookieStoreTest.php`:

```php
    #[Test]
    public function aCookieWithNeitherExpiresNorMaxAgeSurvivesIndefinitely(): void
    {
        $clock = new TestClock('2026-08-25 00:00:00');
        $store = new CookieStore($clock);
        $store->absorb($this->responseWith('sid=abc'), Uri::fromString('https://example.com/'));

        $clock->advance('P10Y');

        static::assertCount(1, $store->all());
    }

    #[Test]
    public function maxAgeExpiresTheCookieWhenTheClockPassesIt(): void
    {
        $clock = new TestClock('2026-08-25 00:00:00');
        $store = new CookieStore($clock);
        $store->absorb($this->responseWith('sid=abc; Max-Age=60'), Uri::fromString('https://example.com/'));

        $clock->advance('PT59S');
        static::assertCount(1, $store->all());

        $clock->advance('PT2S');
        static::assertSame([], $store->all());
    }

    #[Test]
    public function expiresExpiresTheCookieWhenTheClockPassesIt(): void
    {
        $clock = new TestClock('2026-08-25 00:00:00');
        $store = new CookieStore($clock);
        $store->absorb(
            $this->responseWith('sid=abc; Expires=Tue, 25 Aug 2026 00:01:00 GMT'),
            Uri::fromString('https://example.com/')
        );

        $clock->advance('PT30S');
        static::assertCount(1, $store->all());

        $clock->advance('PT31S');
        static::assertSame([], $store->all());
    }

    #[Test]
    public function maxAgeWinsOverExpiresWhenBothAreGiven(): void
    {
        $clock = new TestClock('2026-08-25 00:00:00');
        $store = new CookieStore($clock);
        $store->absorb(
            $this->responseWith('sid=abc; Expires=Tue, 25 Aug 2026 10:00:00 GMT; Max-Age=60'),
            Uri::fromString('https://example.com/')
        );

        $clock->advance('PT61S');

        static::assertSame([], $store->all());
    }

    #[Test]
    public function aZeroOrNegativeMaxAgeDeletesTheCookieImmediately(): void
    {
        $clock = new TestClock('2026-08-25 00:00:00');
        $store = new CookieStore($clock);
        $uri = Uri::fromString('https://example.com/');
        $store->absorb($this->responseWith('sid=abc'), $uri);

        $store->absorb($this->responseWith('sid=; Max-Age=0'), $uri);

        static::assertSame([], $store->all());
    }

    #[Test]
    public function anExpiresAlreadyInThePastDeletesTheCookieImmediately(): void
    {
        $clock = new TestClock('2026-08-25 00:00:00');
        $store = new CookieStore($clock);
        $uri = Uri::fromString('https://example.com/');
        $store->absorb($this->responseWith('sid=abc'), $uri);

        $store->absorb($this->responseWith('sid=; Expires=Mon, 24 Aug 2026 00:00:00 GMT'), $uri);

        static::assertSame([], $store->all());
    }
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/phpunit tests/Http/CookieStoreTest.php`
Expected: FAIL — the expiry tests still find the cookie, e.g. `Failed asserting that actual size 1 matches expected size 0`.

- [ ] **Step 3: Write minimal implementation**

In `src/Http/CookieStore.php`, add `use DateTimeImmutable;` to the imports. Call `$this->prune();` as the first statement of both `absorb()` and `all()`:

```php
    public function absorb(IResponse $response, IUri $requestUri): void
    {
        $this->prune();
        $host = strtolower($requestUri->getHost());
        // ... unchanged
    }

    public function all(): array
    {
        $this->prune();

        return array_values(array_map(static fn (CookieEntry $e): Cookie => $e->cookie, $this->entries));
    }
```

Replace the last three lines of `store()` (from `$key = ...` onwards) with:

```php
        $key = $domain . "\0" . $path . "\0" . $cookie->name;
        $now = $this->clock->now();
        $expiresAt = null;
        if ($cookie->maxAge !== null) {
            if ($cookie->maxAge <= 0) {
                unset($this->entries[$key]);

                return;
            }

            $expiresAt = $now->modify(sprintf('+%d seconds', $cookie->maxAge));
        } elseif ($cookie->expires !== null) {
            if ($cookie->expires <= $now) {
                unset($this->entries[$key]);

                return;
            }

            $expiresAt = $cookie->expires;
        }

        $this->entries[$key] = new CookieEntry($resolved, $expiresAt, $hostOnly, $this->sequence);
        $this->sequence++;
```

Add this private method, alphabetically before `store()`:

```php
    /**
     * Drops every entry whose expiry the clock has passed.
     */
    private function prune(): void
    {
        $now = $this->clock->now();
        foreach ($this->entries as $key => $entry) {
            if ($entry->expiresAt !== null && $entry->expiresAt <= $now) {
                unset($this->entries[$key]);
            }
        }
    }
```

`DateTimeImmutable::modify()` returns `DateTimeImmutable|false`; because the format string is built from an `int` it never fails, but PHPStan at `max` will still complain. If it does, assert the type rather than suppressing it:

```php
            $moved = $now->modify(sprintf('+%d seconds', $cookie->maxAge));
            $expiresAt = $moved instanceof DateTimeImmutable ? $moved : $now;
```

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/phpunit tests/Http/CookieStoreTest.php`
Expected: PASS, 19 tests.

- [ ] **Step 5: Run the quality gates**

Run: `composer phpcbf && composer phpcs && composer phpstan && composer test`
Expected: all clean.

- [ ] **Step 6: Commit**

```bash
git add src/Http/CookieStore.php tests/Http/CookieStoreTest.php
git commit -m "feat(http): expire stored cookies against the clock"
```

---

### Task 8: `CookieStore` — cookie name prefixes

**Files:**
- Modify: `src/Http/CookieStore.php`
- Test: `tests/Http/CookieStoreTest.php`

**Interfaces:**
- Consumes: `CookieStore` (Tasks 6–7).
- Produces: no new public methods; `absorb()` now rejects cookies which break their own name prefix.

`__Secure-` requires `Secure`. `__Host-` requires `Secure`, an explicit `Path=/`, and no `Domain`. A prefix a cookie does not honour is the whole point of the prefix, so ignoring it would be worse than not supporting it.

- [ ] **Step 1: Write the failing test**

Append to `tests/Http/CookieStoreTest.php`:

```php
    #[Test]
    public function aSecurePrefixedCookieIsAcceptedWhenItIsSecure(): void
    {
        $store = new CookieStore(new TestClock('2026-08-25 00:00:00'));

        $store->absorb($this->responseWith('__Secure-sid=abc; Secure'), Uri::fromString('https://example.com/'));

        static::assertCount(1, $store->all());
    }

    #[Test]
    public function aSecurePrefixedCookieIsRejectedWhenItIsNotSecure(): void
    {
        $store = new CookieStore(new TestClock('2026-08-25 00:00:00'));

        $store->absorb($this->responseWith('__Secure-sid=abc'), Uri::fromString('https://example.com/'));

        static::assertSame([], $store->all());
    }

    #[Test]
    public function aHostPrefixedCookieIsAcceptedWhenItMeetsEveryCondition(): void
    {
        $store = new CookieStore(new TestClock('2026-08-25 00:00:00'));

        $store->absorb(
            $this->responseWith('__Host-sid=abc; Secure; Path=/'),
            Uri::fromString('https://example.com/')
        );

        static::assertCount(1, $store->all());
    }

    #[Test]
    public function aHostPrefixedCookieIsRejectedWithoutSecure(): void
    {
        $store = new CookieStore(new TestClock('2026-08-25 00:00:00'));

        $store->absorb($this->responseWith('__Host-sid=abc; Path=/'), Uri::fromString('https://example.com/'));

        static::assertSame([], $store->all());
    }

    #[Test]
    public function aHostPrefixedCookieIsRejectedWithADomain(): void
    {
        $store = new CookieStore(new TestClock('2026-08-25 00:00:00'));

        $store->absorb(
            $this->responseWith('__Host-sid=abc; Secure; Path=/; Domain=example.com'),
            Uri::fromString('https://example.com/')
        );

        static::assertSame([], $store->all());
    }

    #[Test]
    public function aHostPrefixedCookieIsRejectedWithoutAnExplicitRootPath(): void
    {
        $store = new CookieStore(new TestClock('2026-08-25 00:00:00'));

        $store->absorb($this->responseWith('__Host-sid=abc; Secure'), Uri::fromString('https://example.com/'));

        static::assertSame([], $store->all());
    }
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/phpunit tests/Http/CookieStoreTest.php`
Expected: FAIL — the rejection tests find a stored cookie.

- [ ] **Step 3: Write minimal implementation**

In `src/Http/CookieStore.php`, add this guard as the **first** statement of `store()`, before the domain resolution:

```php
        if (str_starts_with($cookie->name, '__Secure-') && !$cookie->secure) {
            return;
        }
        if (str_starts_with($cookie->name, '__Host-')) {
            if (!$cookie->secure || $cookie->domain !== null || $cookie->path !== '/') {
                return;
            }
        }
```

And extend the method's docblock with a line noting that the two name prefixes are enforced.

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/phpunit tests/Http/CookieStoreTest.php`
Expected: PASS, 25 tests.

- [ ] **Step 5: Run the quality gates**

Run: `composer phpcbf && composer phpcs && composer phpstan && composer test`
Expected: all clean.

- [ ] **Step 6: Commit**

```bash
git add src/Http/CookieStore.php tests/Http/CookieStoreTest.php
git commit -m "feat(http): enforce __Secure- and __Host- cookie prefixes"
```

---

### Task 9: `CookieStore::attachTo()` — sending rules

**Files:**
- Modify: `src/Http/CookieStore.php`
- Test: `tests/Http/CookieStoreTest.php`

**Interfaces:**
- Consumes: `CookieStore` (Tasks 6–8); `Psr\Http\Message\RequestInterface as IRequest`.
- Produces: `public function attachTo(IRequest $request): IRequest`.

Rules: domain match (exact when host-only, suffix otherwise), path match, `Secure` cookies over `https` only, unexpired only. Ordering is longest path first, then oldest first — the order RFC 6265 specifies, which some servers depend on. An existing `Cookie` header on the request wins: stored cookies whose names already appear are skipped, the rest appended. No matches and no existing header means the request is returned untouched, with no empty `Cookie:` header.

- [ ] **Step 1: Write the failing test**

Append to `tests/Http/CookieStoreTest.php` (add `use Manychois\PhpStrong\Http\Request;` to the imports):

```php
    #[Test]
    public function attachToSendsAMatchingCookie(): void
    {
        $store = new CookieStore(new TestClock('2026-08-25 00:00:00'));
        $store->absorb($this->responseWith('sid=abc'), Uri::fromString('https://example.com/'));

        $request = $store->attachTo(new Request('GET', 'https://example.com/v1/things'));

        static::assertSame('sid=abc', $request->getHeaderLine('Cookie'));
    }

    #[Test]
    public function attachToEncodesTheValue(): void
    {
        $store = new CookieStore(new TestClock('2026-08-25 00:00:00'));
        $store->absorb($this->responseWith('sid=a%20b'), Uri::fromString('https://example.com/'));

        $request = $store->attachTo(new Request('GET', 'https://example.com/'));

        static::assertSame('sid=a%20b', $request->getHeaderLine('Cookie'));
    }

    #[Test]
    public function attachToLeavesTheRequestUntouchedWhenNothingMatches(): void
    {
        $store = new CookieStore(new TestClock('2026-08-25 00:00:00'));
        $request = new Request('GET', 'https://example.com/');

        static::assertFalse($store->attachTo($request)->hasHeader('Cookie'));
    }

    #[Test]
    public function attachToDoesNotSendAHostOnlyCookieToASubdomain(): void
    {
        $store = new CookieStore(new TestClock('2026-08-25 00:00:00'));
        $store->absorb($this->responseWith('sid=abc'), Uri::fromString('https://example.com/'));

        $request = $store->attachTo(new Request('GET', 'https://api.example.com/'));

        static::assertFalse($request->hasHeader('Cookie'));
    }

    #[Test]
    public function attachToSendsADomainCookieToASubdomain(): void
    {
        $store = new CookieStore(new TestClock('2026-08-25 00:00:00'));
        $store->absorb(
            $this->responseWith('sid=abc; Domain=example.com'),
            Uri::fromString('https://example.com/')
        );

        $request = $store->attachTo(new Request('GET', 'https://api.example.com/'));

        static::assertSame('sid=abc', $request->getHeaderLine('Cookie'));
    }

    #[Test]
    public function attachToRespectsThePath(): void
    {
        $store = new CookieStore(new TestClock('2026-08-25 00:00:00'));
        $store->absorb($this->responseWith('sid=abc; Path=/admin'), Uri::fromString('https://example.com/'));

        static::assertFalse($store->attachTo(new Request('GET', 'https://example.com/public'))->hasHeader('Cookie'));
        static::assertSame(
            'sid=abc',
            $store->attachTo(new Request('GET', 'https://example.com/admin/users'))->getHeaderLine('Cookie')
        );
    }

    #[Test]
    public function attachToDoesNotSendASecureCookieOverPlainHttp(): void
    {
        $store = new CookieStore(new TestClock('2026-08-25 00:00:00'));
        $store->absorb($this->responseWith('sid=abc; Secure'), Uri::fromString('https://example.com/'));

        static::assertFalse($store->attachTo(new Request('GET', 'http://example.com/'))->hasHeader('Cookie'));
        static::assertSame(
            'sid=abc',
            $store->attachTo(new Request('GET', 'https://example.com/'))->getHeaderLine('Cookie')
        );
    }

    #[Test]
    public function attachToDoesNotSendAnExpiredCookie(): void
    {
        $clock = new TestClock('2026-08-25 00:00:00');
        $store = new CookieStore($clock);
        $store->absorb($this->responseWith('sid=abc; Max-Age=60'), Uri::fromString('https://example.com/'));

        $clock->advance('PT61S');

        static::assertFalse($store->attachTo(new Request('GET', 'https://example.com/'))->hasHeader('Cookie'));
    }

    #[Test]
    public function attachToOrdersByLongestPathThenOldestFirst(): void
    {
        $store = new CookieStore(new TestClock('2026-08-25 00:00:00'));
        $uri = Uri::fromString('https://example.com/');
        $store->absorb($this->responseWith('a=1; Path=/'), $uri);
        $store->absorb($this->responseWith('b=2; Path=/admin'), $uri);
        $store->absorb($this->responseWith('c=3; Path=/'), $uri);

        $request = $store->attachTo(new Request('GET', 'https://example.com/admin/users'));

        static::assertSame('b=2; a=1; c=3', $request->getHeaderLine('Cookie'));
    }

    #[Test]
    public function attachToLetsAnExistingCookieHeaderWin(): void
    {
        $store = new CookieStore(new TestClock('2026-08-25 00:00:00'));
        $uri = Uri::fromString('https://example.com/');
        $store->absorb($this->responseWith('sid=stored'), $uri);
        $store->absorb($this->responseWith('other=stored'), $uri);

        $request = $store->attachTo(
            new Request('GET', 'https://example.com/', ['Cookie' => 'sid=explicit'])
        );

        static::assertSame('sid=explicit; other=stored', $request->getHeaderLine('Cookie'));
    }
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/phpunit tests/Http/CookieStoreTest.php`
Expected: FAIL — `Call to undefined method ...::attachTo()`.

- [ ] **Step 3: Write minimal implementation**

Add `use Psr\Http\Message\RequestInterface as IRequest;` to the imports of `src/Http/CookieStore.php`. Add `attachTo()` as a public instance method — alphabetically after `all()` — and the private helpers below, keeping them alphabetical among the privates:

```php
    /**
     * Adds a `Cookie` header carrying every stored cookie the request should send.
     *
     * Cookies already named in the request's own `Cookie` header are left alone: an explicit header at the call
     * site is an intent this store should not second-guess. Cookies are ordered longest path first, then oldest
     * first, as RFC 6265 requires.
     *
     * @param IRequest $request The request to attach the cookies to.
     *
     * @return IRequest The request carrying the cookies, or the original if none apply.
     */
    public function attachTo(IRequest $request): IRequest
    {
        $this->prune();
        $uri = $request->getUri();
        $host = strtolower($uri->getHost());
        $secure = strtolower($uri->getScheme()) === 'https';
        $path = $uri->getPath() === '' ? '/' : $uri->getPath();
        $existingHeader = $request->getHeaderLine('Cookie');
        $existing = self::namesIn($existingHeader);

        $matches = [];
        foreach ($this->entries as $entry) {
            $cookie = $entry->cookie;
            if ($cookie->secure && !$secure) {
                continue;
            }
            if (!self::domainMatches($entry, $host)) {
                continue;
            }
            if (!self::pathMatches($path, $cookie->path ?? '/')) {
                continue;
            }
            if (in_array($cookie->name, $existing, true)) {
                continue;
            }

            $matches[] = $entry;
        }

        if ($matches === []) {
            return $request;
        }

        usort($matches, static function (CookieEntry $a, CookieEntry $b): int {
            $byPath = strlen($b->cookie->path ?? '') <=> strlen($a->cookie->path ?? '');

            return $byPath !== 0 ? $byPath : $a->sequence <=> $b->sequence;
        });

        $pairs = array_map(
            static fn (CookieEntry $e): string => $e->cookie->name . '=' . rawurlencode($e->cookie->value),
            $matches
        );
        $joined = implode('; ', $pairs);

        return $request->withHeader('Cookie', $existingHeader === '' ? $joined : $existingHeader . '; ' . $joined);
    }
```

And these private statics:

```php
    /**
     * Tells whether a stored cookie's domain covers the host of a request.
     *
     * @param CookieEntry $entry The stored cookie.
     * @param string $host The lower-cased host of the request.
     *
     * @return bool True if the cookie should be sent to that host.
     */
    private static function domainMatches(CookieEntry $entry, string $host): bool
    {
        $domain = $entry->cookie->domain ?? '';
        if ($entry->hostOnly) {
            return $host === $domain;
        }

        return $host === $domain || str_ends_with($host, '.' . $domain);
    }

    /**
     * Reads the cookie names already present in a `Cookie` header.
     *
     * @param string $header The header value, which may be empty.
     *
     * @return array The names found.
     *
     * @phpstan-return list<string>
     */
    private static function namesIn(string $header): array
    {
        if (trim($header) === '') {
            return [];
        }

        $names = [];
        foreach (explode(';', $header) as $pair) {
            $equals = strpos($pair, '=');
            $names[] = $equals === false ? trim($pair) : trim(substr($pair, 0, $equals));
        }

        return $names;
    }

    /**
     * Tells whether a stored cookie's path covers the path of a request, per RFC 6265.
     *
     * @param string $requestPath The path of the request.
     * @param string $cookiePath The path the cookie was stored with.
     *
     * @return bool True if the cookie should be sent for that path.
     */
    private static function pathMatches(string $requestPath, string $cookiePath): bool
    {
        if ($requestPath === $cookiePath) {
            return true;
        }
        if (!str_starts_with($requestPath, $cookiePath)) {
            return false;
        }
        if (str_ends_with($cookiePath, '/')) {
            return true;
        }

        return ($requestPath[strlen($cookiePath)] ?? '') === '/';
    }
```

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/phpunit tests/Http/CookieStoreTest.php`
Expected: PASS, 35 tests.

- [ ] **Step 5: Run the quality gates**

Run: `composer phpcbf && composer phpcs && composer phpstan && composer test`
Expected: all clean.

- [ ] **Step 6: Commit**

```bash
git add src/Http/CookieStore.php tests/Http/CookieStoreTest.php
git commit -m "feat(http): attach stored cookies to an outgoing request"
```

---

### Task 10: `PendingRequest::onResponse()`

**Files:**
- Modify: `src/Http/PendingRequest.php`
- Test: `tests/Http/PendingRequestTest.php`

**Interfaces:**
- Consumes: existing `PendingRequest`.
- Produces: `public function onResponse(callable $callback): void`, where `$callback` is `callable(Response): void`.

`PendingRequest` is `final` and `settle()` (`PendingRequest.php:129`) offers nothing a caller can observe, so async cookie absorption is impossible without this hook. Callbacks fire once the `Response` is built and **not** on a network or parse error — a failed transfer has no cookies. Registering after settlement invokes immediately, so there is no race between `sendAsync()` returning and a handler being attached.

Place `onResponse()` immediately before `response()` — public instance methods are alphabetical, and `__destruct()` stays where it is.

- [ ] **Step 1: Write the failing test**

`tests/Http/PendingRequestTest.php` already has the two helpers these tests need: `makePending()` (line 245) builds a `PendingRequest` over a bare cURL handle, and `settle()` (line 256) invokes the private `settle()` through reflection so a transfer can be completed without a network. That file uses snake_case test names and `self::assert*`; match it rather than the camelCase style used elsewhere.

Add `use Manychois\PhpStrong\Http\Response;` to its imports and append:

```php
    #[Test]
    public function onResponse_fires_when_the_transfer_succeeds(): void
    {
        $pending = $this->makePending();
        $seen = [];
        $pending->onResponse(static function (Response $response) use (&$seen): void {
            $seen[] = $response->getStatusCode();
        });

        $this->settle($pending, \CURLE_OK, '', ['HTTP/1.1 200 OK'], 'body');

        self::assertSame([200], $seen);
    }

    #[Test]
    public function onResponse_does_not_fire_when_the_transfer_fails(): void
    {
        $pending = $this->makePending();
        $fired = false;
        $pending->onResponse(static function () use (&$fired): void {
            $fired = true;
        });

        $this->settle($pending, \CURLE_COULDNT_CONNECT, 'connection refused', [], '');

        self::assertFalse($fired);
    }

    #[Test]
    public function onResponse_does_not_fire_when_the_response_cannot_be_parsed(): void
    {
        $pending = $this->makePending();
        $fired = false;
        $pending->onResponse(static function () use (&$fired): void {
            $fired = true;
        });

        $this->settle($pending, \CURLE_OK, '', ['not a status line'], 'body');

        self::assertFalse($fired);
    }

    #[Test]
    public function onResponse_fires_immediately_when_registered_after_settlement(): void
    {
        $pending = $this->makePending();
        $this->settle($pending, \CURLE_OK, '', ['HTTP/1.1 201 Created'], '');

        $seen = [];
        $pending->onResponse(static function (Response $response) use (&$seen): void {
            $seen[] = $response->getStatusCode();
        });

        self::assertSame([201], $seen);
    }
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/phpunit tests/Http/PendingRequestTest.php`
Expected: FAIL — `Call to undefined method ...::onResponse()`.

- [ ] **Step 3: Write minimal implementation**

In `src/Http/PendingRequest.php`, add the callback list to the properties at the top of the class:

```php
    /**
     * @var list<callable(Response):void>
     */
    private array $onResponse = [];
```

Add the public method immediately before `response()`:

```php
    /**
     * Registers a callback to run once this transfer produces a response.
     *
     * The callback does not run when the transfer fails, because there is no response to hand it. Registering
     * after the transfer has already settled runs the callback straight away, so no completion can be missed.
     *
     * @param callable $callback The callback to run.
     *
     * @phpstan-param callable(Response):void $callback
     */
    public function onResponse(callable $callback): void
    {
        if ($this->settled) {
            if ($this->response !== null) {
                $callback($this->response);
            }

            return;
        }

        $this->onResponse[] = $callback;
    }
```

In `settle()`, fire the callbacks after the response is built. The `try` block becomes:

```php
        try {
            $raw = RawResponse::fromHeaderLines($headerLines, $body);
            $this->response = new Response(
                $raw->statusCode,
                $raw->reasonPhrase,
                $raw->headers,
                $raw->body,
                $raw->protocolVersion,
            );
        } catch (ClientException $ex) {
            $this->error = $ex;

            return;
        }

        $callbacks = $this->onResponse;
        $this->onResponse = [];
        foreach ($callbacks as $callback) {
            $callback($this->response);
        }
```

Clearing the list before invoking keeps a callback which registers another callback from looping.

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/phpunit tests/Http/PendingRequestTest.php`
Expected: PASS, existing tests plus the 4 new ones.

- [ ] **Step 5: Run the quality gates**

Run: `composer phpcbf && composer phpcs && composer phpstan && composer test`
Expected: all clean.

- [ ] **Step 6: Commit**

```bash
git add src/Http/PendingRequest.php tests/Http/PendingRequestTest.php
git commit -m "feat(http): let callers observe a PendingRequest completing"
```

---

### Task 11: `CookieAwareClient` — the synchronous path

**Files:**
- Create: `src/Http/CookieAwareClient.php`
- Test: `tests/Http/CookieAwareClientTest.php`

**Interfaces:**
- Consumes: `CookieStore` (Tasks 6–9); `Psr\Http\Client\ClientInterface as IClient`; `Psr\Http\Message\{RequestInterface as IRequest, ResponseInterface as IResponse}`.
- Produces: `final class CookieAwareClient implements IClient` with `__construct(IClient $inner, CookieStore $store)` and `sendRequest(IRequest $request): IResponse`.

`sendAsync()` arrives in Task 12. The decorator takes and returns PSR interfaces so it wraps any PSR-18 client, not only this library's.

- [ ] **Step 1: Write the failing test**

Create `tests/Http/CookieAwareClientTest.php`:

```php
<?php

declare(strict_types=1);

namespace Manychois\PhpStrongTests\Http;

use Manychois\PhpStrong\Http\CookieAwareClient;
use Manychois\PhpStrong\Http\CookieStore;
use Manychois\PhpStrong\Http\Request;
use Manychois\PhpStrong\Http\Response;
use Manychois\PhpStrong\Http\Uri;
use Manychois\PhpStrong\Time\TestClock;
use Override;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface as IClient;
use Psr\Http\Message\RequestInterface as IRequest;
use Psr\Http\Message\ResponseInterface as IResponse;

/**
 * Unit tests for {@see CookieAwareClient}.
 */
final class CookieAwareClientTest extends TestCase
{
    #[Test]
    public function sendRequestAbsorbsTheCookiesTheResponseSets(): void
    {
        $store = new CookieStore(new TestClock('2026-08-25 00:00:00'));
        $inner = $this->fakeClient(new Response(headers: ['Set-Cookie' => 'sid=abc']));
        $client = new CookieAwareClient($inner, $store);

        $client->sendRequest(new Request('GET', 'https://example.com/login'));

        static::assertCount(1, $store->all());
        static::assertSame('sid', $store->all()[0]->name);
    }

    #[Test]
    public function sendRequestAttachesStoredCookiesToTheOutgoingRequest(): void
    {
        $store = new CookieStore(new TestClock('2026-08-25 00:00:00'));
        $inner = $this->fakeClient(new Response());
        $client = new CookieAwareClient($inner, $store);

        $store->absorb(
            new Response(headers: ['Set-Cookie' => 'sid=abc']),
            Uri::fromString('https://example.com/')
        );

        $client->sendRequest(new Request('GET', 'https://example.com/things'));

        static::assertNotNull($inner->lastRequest);
        static::assertSame('sid=abc', $inner->lastRequest->getHeaderLine('Cookie'));
    }

    #[Test]
    public function aLoginThenCallFlowCarriesTheSessionCookie(): void
    {
        $store = new CookieStore(new TestClock('2026-08-25 00:00:00'));
        $inner = $this->fakeClient(new Response(headers: ['Set-Cookie' => 'sid=abc; Path=/']));
        $client = new CookieAwareClient($inner, $store);

        $client->sendRequest(new Request('POST', 'https://example.com/login'));
        $inner->next = new Response();
        $client->sendRequest(new Request('GET', 'https://example.com/profile'));

        static::assertNotNull($inner->lastRequest);
        static::assertSame('sid=abc', $inner->lastRequest->getHeaderLine('Cookie'));
    }

    #[Test]
    public function sendRequestReturnsTheInnerResponseUnchanged(): void
    {
        $expected = new Response(201, 'Created');
        $client = new CookieAwareClient($this->fakeClient($expected), new CookieStore());

        static::assertSame($expected, $client->sendRequest(new Request('GET', 'https://example.com/')));
    }

    /**
     * Creates a client stub that records the request it was given.
     *
     * @return IClient&object{lastRequest: ?IRequest, next: Response}
     */
    private function fakeClient(Response $response): IClient
    {
        return new class ($response) implements IClient {
            public ?IRequest $lastRequest = null;

            public function __construct(public Response $next)
            {
            }

            #[Override]
            public function sendRequest(IRequest $request): IResponse
            {
                $this->lastRequest = $request;

                return $this->next;
            }
        };
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/phpunit tests/Http/CookieAwareClientTest.php`
Expected: FAIL — `Class "Manychois\PhpStrong\Http\CookieAwareClient" not found`.

- [ ] **Step 3: Write minimal implementation**

Create `src/Http/CookieAwareClient.php`:

```php
<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\Http;

use Override;
use Psr\Http\Client\ClientInterface as IClient;
use Psr\Http\Message\RequestInterface as IRequest;
use Psr\Http\Message\ResponseInterface as IResponse;

/**
 * Wraps any PSR-18 client so that cookies a remote host sets are remembered and sent back on later requests.
 *
 * Cookies become invisible to the calling code: the request goes out carrying whatever the {@see CookieStore}
 * holds for its URI, and whatever the response sets is absorbed before it is returned.
 */
final class CookieAwareClient implements IClient
{
    /**
     * Initializes a new instance of the CookieAwareClient class.
     *
     * @param IClient $inner The client which actually sends the request.
     * @param CookieStore $store The store which remembers the cookies.
     */
    public function __construct(
        private readonly IClient $inner,
        private readonly CookieStore $store,
    ) {
    }

    #region implements IClient

    /**
     * @inheritDoc
     */
    #[Override]
    public function sendRequest(IRequest $request): IResponse
    {
        $prepared = $this->store->attachTo($request);
        $response = $this->inner->sendRequest($prepared);
        $this->store->absorb($response, $prepared->getUri());

        return $response;
    }

    #endregion implements IClient
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/phpunit tests/Http/CookieAwareClientTest.php`
Expected: PASS, 4 tests.

- [ ] **Step 5: Run the quality gates**

Run: `composer phpcbf && composer phpcs && composer phpstan && composer test`
Expected: all clean.

- [ ] **Step 6: Commit**

```bash
git add src/Http/CookieAwareClient.php tests/Http/CookieAwareClientTest.php
git commit -m "feat(http): add CookieAwareClient for the synchronous path"
```

---

### Task 12: `CookieAwareClient::sendAsync()`

**Files:**
- Modify: `src/Http/CookieAwareClient.php`
- Test: `tests/Http/CookieAwareClientTest.php`

**Interfaces:**
- Consumes: `CookieAwareClient` (Task 11), `PendingRequest::onResponse()` (Task 10), the concrete `Client`.
- Produces: `public function sendAsync(IRequest $request): PendingRequest`.

`sendAsync()` is not part of PSR-18 — it exists only on the concrete `Client` (`Client.php:50`). The decorator therefore accepts `IClient` and throws `BadMethodCallException` when the wrapped client does not offer it: an explicit failure at the call site beats a decorator that silently cannot be used asynchronously.

`sendAsync()` is a public method which is *not* part of `IClient`, so per the project's ordering rules it goes **above** the `#region implements IClient` block.

**Concurrency semantics to document on the method:** with several transfers in flight, cookies are absorbed in completion order, which cURL determines. If two concurrent responses set the same cookie, the last settlement wins. Making that deterministic would mean serialising requests, which defeats the point of the async API.

- [ ] **Step 1: Write the failing test**

Append to `tests/Http/CookieAwareClientTest.php` (add `use BadMethodCallException;` to the imports):

```php
    #[Test]
    public function sendAsyncRefusesAClientWhichCannotSendAsynchronously(): void
    {
        $client = new CookieAwareClient($this->fakeClient(new Response()), new CookieStore());

        $this->expectException(BadMethodCallException::class);
        $this->expectExceptionMessage('The wrapped client does not support asynchronous requests.');

        $client->sendAsync(new Request('GET', 'https://example.com/'));
    }
```

For the happy path, drive the transfer to completion the way `tests/Http/PendingRequestTest.php` does — reflectively invoking the private `settle()` — so no socket is ever opened. `Client::sendAsync()` creates a cURL handle but transfers nothing until the executor is pumped, and settling by hand skips that entirely. Add `use Manychois\PhpStrong\Http\Client;` and `use ReflectionMethod;` to the imports:

```php
    #[Test]
    public function sendAsyncAbsorbsCookiesWhenTheTransferSettles(): void
    {
        $store = new CookieStore(new TestClock('2026-08-25 00:00:00'));
        $client = new CookieAwareClient(new Client(), $store);

        $pending = $client->sendAsync(new Request('GET', 'https://example.com/login'));
        $settle = new ReflectionMethod($pending, 'settle');
        $settle->invoke($pending, \CURLE_OK, '', ['HTTP/1.1 200 OK', 'Set-Cookie: sid=abc'], '');

        static::assertCount(1, $store->all());
        static::assertSame('sid', $store->all()[0]->name);
        static::assertSame('abc', $store->all()[0]->value);
    }
```

The cookie is absorbed against the URI the request was sent to, so it is stored host-only for `example.com` with the default path `/`.

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/phpunit tests/Http/CookieAwareClientTest.php`
Expected: FAIL — `Call to undefined method ...::sendAsync()`.

- [ ] **Step 3: Write minimal implementation**

Add `use BadMethodCallException;` to the imports of `src/Http/CookieAwareClient.php` and add this method above the `#region implements IClient` block:

```php
    /**
     * Dispatches the request without waiting for the response, attaching the stored cookies on the way out and
     * absorbing whatever the response sets once the transfer settles.
     *
     * This method is an extension beyond PSR-18 and is available only when the wrapped client provides it.
     *
     * With several transfers in flight, cookies are absorbed in completion order, which cURL decides. Should two
     * concurrent responses set the same cookie, the last one to settle wins; making that deterministic would mean
     * serialising the requests, which is the opposite of what this method is for.
     *
     * @param IRequest $request The request to send.
     *
     * @return PendingRequest The handle for collecting the response.
     *
     * @throws BadMethodCallException if the wrapped client cannot send requests asynchronously.
     */
    public function sendAsync(IRequest $request): PendingRequest
    {
        if (!$this->inner instanceof Client) {
            throw new BadMethodCallException('The wrapped client does not support asynchronous requests.');
        }

        $prepared = $this->store->attachTo($request);
        $pending = $this->inner->sendAsync($prepared);
        $store = $this->store;
        $uri = $prepared->getUri();
        $pending->onResponse(static function (Response $response) use ($store, $uri): void {
            $store->absorb($response, $uri);
        });

        return $pending;
    }
```

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/phpunit tests/Http/CookieAwareClientTest.php`
Expected: PASS, 6 tests.

- [ ] **Step 5: Run the quality gates**

Run: `composer phpcbf && composer phpcs && composer phpstan && composer test`
Expected: all clean.

- [ ] **Step 6: Commit**

```bash
git add src/Http/CookieAwareClient.php tests/Http/CookieAwareClientTest.php
git commit -m "feat(http): absorb cookies from asynchronous transfers"
```

---

### Task 13: Documentation

**Files:**
- Modify: `docs/http.md`
- Modify: `README.md`

**Interfaces:**
- Consumes: every class from Tasks 1–12.
- Produces: no code.

`docs/http.md` currently runs `## Quick start`, `## Classes`, `## Factories (PSR-17)`, `## HTTP Client (PSR-18)`, `## Middleware (PSR-15)`, `## Session`. Add `## Cookies` between `## Middleware (PSR-15)` and `## Session` — cookies belong beside the request/response material and lead naturally into the session section.

Follow Diátaxis (`docs/internal/diataxis-framework-reference.md`): this is reference material with one short how-to example per role. Describe the machinery; do not teach, and do not explain design rationale — that lives in the spec.

- [ ] **Step 1: Write the `## Cookies` section in `docs/http.md`**

Cover, in this order:

1. A one-paragraph statement of the two roles and which class serves each.
2. **`Cookie`** — the constructor's parameters as a table (name, type, default, meaning); the two named constructors `expired()` and `parseSetCookie()`; `toSetCookieHeader()`. State that `$value` is held decoded and `rawurlencode`d on output.
3. **Server role** — a `CookieBag` example:

```php
$cookies = CookieBag::fromRequest($request);
$theme = $cookies->get('theme') ?? 'light';

$cookies->set(new Cookie('theme', 'dark', maxAge: 31536000, path: '/', httpOnly: true));
$cookies->expire('legacy', path: '/');

return $cookies->applyTo($response);
```

   State that `get()` returns a `string` because incoming cookies carry no attributes, that queued cookies are deduplicated by name, domain and path, and that values from `getCookieParams()` are not decoded again because PHP has already decoded them.

4. **Client role** — a `CookieAwareClient` example:

```php
$client = new CookieAwareClient(new Client(), new CookieStore());

$client->sendRequest(new Request('POST', 'https://api.example.com/login', body: $credentials));
$profile = $client->sendRequest(new Request('GET', 'https://api.example.com/profile'));
```

   State that the store is in-memory and lives as long as the instance, that a cookie breaking RFC 6265 is skipped silently, that `__Secure-` and `__Host-` prefixes are enforced, and — as a short note — that without a public suffix list `Domain=co.uk` is accepted where a browser would refuse it.

5. **Async note** — `sendAsync()` requires the concrete `Client`; with concurrent transfers the last response to settle wins for a given cookie.
6. **A short "not yet covered" note:** the library has no response emitter, so `applyTo()` returns a response whose `Set-Cookie` headers the application must send itself for now.

- [ ] **Step 2: Update `README.md`**

Find the Http module row and extend its description to mention cookies alongside the PSR-7/17/18/15 coverage already listed. Keep the existing wording and format; add the smallest phrase that fits.

- [ ] **Step 3: Verify the examples compile**

Copy each PHP example from the new section into a scratch `.php` file (under the session scratchpad, not the repo), give it the imports the example implies, and run `php -l` on it. That catches a typo'd method name or a wrong argument order before the docs ship. Delete the scratch file afterwards.

- [ ] **Step 4: Run the quality gates**

Run: `composer phpcbf && composer phpcs && composer phpstan && composer test`
Expected: all clean — this task changes no source, so this is a regression check.

- [ ] **Step 5: Commit**

```bash
git add docs/http.md README.md
git commit -m "docs(http): document cookie handling on both roles"
```

---

## Follow-up, not in this plan

The response emitter is specified separately, immediately after this plan. When it is built, the rule carried forward from the cookies spec must hold: **the emitter must never pass `replace: true` to `header()` for `Set-Cookie`**, or it silently deletes the session cookie `session_start()` queued inside `NativeSession` (`NativeSession.php:218`). Until then, `CookieBag::applyTo()` is verifiable by assertion on the returned `Response` but is not observable end to end from a real SAPI request.
