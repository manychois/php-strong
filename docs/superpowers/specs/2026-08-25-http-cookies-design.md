# HTTP Cookies — Design

Date: 2026-08-25
Module: `Manychois\PhpStrong\Http`

## Goal

Make cookies a typed, tested concern in both roles the module already supports:

- **Server role** — the application receives a `ServerRequest` from a browser and returns a `Response`. Read
  incoming cookies; queue outgoing ones; flush them as `Set-Cookie` headers.
- **Client role** — the application calls a remote host through the PSR-18 `Client`. Absorb `Set-Cookie` from
  each response, and attach the matching `Cookie` header to the next request, so a login-then-call-endpoints
  flow works without the caller tracking cookies by hand.

Today neither role has any typing: outgoing cookies mean hand-formatting a header string, and the client role
loses cookies entirely between requests.

## Scope

In scope:

- `Cookie` — immutable value object; parses and formats one `Set-Cookie` header value.
- `CookieBag` — server-side collector; reads from a PSR-7 server request, queues outgoing cookies, applies them
  to a response.
- `CookieStore` — client-side in-memory store; RFC 6265 acceptance and sending rules, clock-driven expiry.
- `CookieAwareClient` — PSR-18 decorator wiring a `CookieStore` into any client, synchronously and
  asynchronously.
- `PendingRequest::onResponse()` — a new completion hook on the existing class, required for async absorption.

Out of scope (deliberately):

- **Persistence of `CookieStore` beyond the process.** In-memory only. No file backend, no PSR-6 backend, no
  storage interface. Adding one later is a constructor argument or a decorator; nothing here forecloses it.
- **A public suffix list.** See the limitation recorded under `CookieStore` below.
- **A response emitter.** Nothing in `src/` currently sends a `Response` to the SAPI, which means
  `CookieBag::applyTo()` produces headers that this library cannot yet deliver. That gap is real and is being
  designed as a separate spec immediately after this one. See "Hand-off to the emitter spec".
- **Cookie handling inside `NativeSession`.** The session cookie is emitted by PHP itself at `session_start()`;
  it does not pass through `Cookie` or `CookieBag`. See the hand-off note.

## Dependencies and layout

No new Composer dependencies. `psr/clock` and `psr/http-client` are already required.

- `src/Http/Cookie.php`
- `src/Http/CookieBag.php`
- `src/Http/CookieStore.php`
- `src/Http/CookieAwareClient.php`
- `src/Http/PendingRequest.php` — modified, one new public method.
- `tests/Http/CookieTest.php`, `CookieBagTest.php`, `CookieStoreTest.php`, `CookieAwareClientTest.php`
- `docs/http.md` — new Cookies section; `README.md` — mention in the Http module row.

`SameSite` (`src/Http/SameSite.php`) already exists and is reused unchanged.

Imports follow the project standard: `use Psr\Http\Message\ServerRequestInterface as IServerRequest;`,
`ResponseInterface as IResponse`, `RequestInterface as IRequest`, `UriInterface as IUri`,
`Psr\Http\Client\ClientInterface as IClient`, `Psr\Clock\ClockInterface as IClock`. `CookieStore` also imports
`Manychois\PhpStrong\Time\UtcClock` for its default clock.

## `Cookie`

```php
final class Cookie
{
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
    );

    public static function expired(string $name, ?string $domain = null, ?string $path = null): self;
    public static function parseSetCookie(string $header): self;

    public function toSetCookieHeader(): string;
}
```

### Construction

Validation throws `InvalidArgumentException`, per the module's fail-at-the-boundary principle:

- `$name` must be a non-empty RFC 6265 token: no control characters, no whitespace, and none of
  `( ) < > @ , ; : \ " / [ ] ? = { }`.
- `$value` is **not** validated. Values are URL-encoded on output (below), so any string is legal.
- `$maxAge` has no sign constraint — a negative value is how a cookie is expired.
- `$sameSite === SameSite::None` requires `$secure === true`.
- `$partitioned === true` requires `$secure === true`.

The last two mirror the equivalent checks already in `SessionOptions.php:118-123`, and both reflect what
browsers enforce rather than what the RFC permits.

`expired()` returns a cookie with value `''`, `maxAge` `-1`, and `expires` at the Unix epoch. Both attributes
are set because some older browsers honour only one of the two.

### Encoding

`$value` holds the **decoded** value. `toSetCookieHeader()` applies `rawurlencode()`; `parseSetCookie()`
applies `rawurldecode()`.

`rawurlencode` (RFC 3986, space → `%20`) is used rather than `urlencode` (space → `+`): it is what JavaScript's
`decodeURIComponent` expects, so a cookie written here is readable from the browser without surprises, and a
literal `+` in a value round-trips correctly.

### `toSetCookieHeader()`

Formats one header value, e.g. `theme=dark; Path=/; Secure; HttpOnly; SameSite=Lax`.

- `Expires` uses the IMF-fixdate format browsers require: `D, d M Y H:i:s \G\M\T`, always converted to UTC
  first regardless of the `DateTimeImmutable`'s own timezone.
- `Secure`, `HttpOnly` and `Partitioned` are emitted as bare flags when true, omitted when false.
- Any attribute left `null` is omitted entirely.
- Attribute order is fixed — `Expires`, `Max-Age`, `Domain`, `Path`, `Secure`, `HttpOnly`, `SameSite`,
  `Partitioned` — so output is deterministic and testable by string equality.

### `parseSetCookie()`

Parses one `Set-Cookie` header value into a `Cookie`.

- The first `name=value` pair is the cookie; everything after a `;` is an attribute.
- Attribute names are matched case-insensitively.
- **Unknown attributes are ignored**, as the RFC mandates. This is how new cookie attributes get adopted
  without breaking existing parsers.
- `Expires` is parsed leniently — browsers accept several date formats in practice. An unparseable date leaves
  `expires` as `null` rather than throwing.
- **Both `Expires` and `Max-Age` are retained when both are given.** The value object stores what it was told;
  `CookieStore` applies the RFC precedence when it computes an actual expiry instant. Collapsing them here
  would lose information a caller may legitimately want to inspect.
- A header with no `=` in its first pair throws `InvalidArgumentException`.

## `CookieBag`

```php
final class CookieBag
{
    public static function fromRequest(IServerRequest $request): self;

    public function all(): array;                    // array<string,string>
    public function applyTo(IResponse $response): IResponse;
    public function expire(string $name, ?string $domain = null, ?string $path = null): void;
    public function get(string $name): ?string;
    public function has(string $name): bool;
    public function queued(): array;                 // list<Cookie>
    public function set(Cookie $cookie): void;
}
```

Mutable by design. One instance is created per request and shared across middleware and handlers, so setting a
cookie deep in the stack does not require threading a modified object back out. `applyTo()` is the single point
where the bag crosses back into immutable PSR-7 territory.

### Reading

`fromRequest()` reads `$request->getCookieParams()` and keeps only string keys with string values; anything
else is skipped, since PSR-7 leaves that array untyped (`ServerRequest.php:26` already notes this).

**No decoding happens here.** PHP has already urldecoded `$_COOKIE`, which is what `getCookieParams()`
ultimately returns. Decoding again would turn a stored `100%25` into `100%`, and a stored `%41` into `A`.
Parsing a raw `Cookie:` header string would require decoding — this entry point does not.

`get()` returns `?string`, not `?Cookie`. An incoming cookie genuinely carries only a name and a value; the
browser sends no attributes. Returning a `Cookie` here would be a value object with eight meaningless nulls.

### Writing

`set()` queues a `Cookie` for output. The dedupe key is **name + domain + path**, matching how a browser
identifies a cookie: calling `set()` twice for the same triple replaces the earlier entry rather than appending,
so a middleware that sets a cookie and a later handler that overrides it emit one header, not two contradictory
ones.

`expire()` is `set(Cookie::expired(...))` and dedupes under the same rule, so set-then-expire within one request
correctly ends as a single expiry header.

`queued()` exposes the pending cookies for inspection and assertions.

### `applyTo()`

Appends one header per queued cookie using **`withAddedHeader()`, never `withHeader()`**. `Set-Cookie` is the
header where multiple values are normal, and replacement loses data. The response is returned; the bag does not
mutate it.

Takes and returns `IResponse`, and `fromRequest()` takes `IServerRequest`, so the bag works with any PSR-7
implementation rather than only this library's.

## `CookieStore`

```php
final class CookieStore
{
    public function __construct(private readonly IClock $clock = new UtcClock());

    public function absorb(IResponse $response, IUri $requestUri): void;
    public function all(): array;                    // list<Cookie>, unexpired
    public function attachTo(IRequest $request): IRequest;
    public function clear(): void;
}
```

In-memory, mutable, one instance per logical session with a remote host. The PSR-20 clock is injected rather
than calling `time()`, so `TestClock` (`src/Time/TestClock.php`) drives every expiry test deterministically.

`absorb()` needs the request URI because a PSR-7 response carries no URI of its own, and every acceptance rule
below depends on the host and path the request was sent to.

### Storage

An array keyed by `domain \0 path \0 name`, each entry holding the `Cookie`, a resolved
`?DateTimeImmutable $expiresAt`, a `bool $hostOnly`, and a creation timestamp used for send ordering. Expired
entries are pruned lazily on `absorb()`, `attachTo()` and `all()` — no timer, no background work.

The `Cookie` an entry holds is the **resolved** one: where the response omitted `Domain` or `Path`, the entry
stores a `Cookie` carrying the defaults derived from the request URI, not the nulls as received. `all()`
therefore returns cookies whose `domain` and `path` are always populated, which is what makes its output
meaningful for inspection and assertions.

### Acceptance rules (`absorb()`, RFC 6265 §5.3)

Applied to every `Set-Cookie` header on the response. **A rejected cookie is skipped silently**, not thrown: a
remote server sending a bad cookie is not an error in the calling code, and throwing would make one
misconfigured third-party header break an otherwise successful request.

- **Domain absent** → stored host-only against the request host. Only that exact host receives it back.
- **Domain present** → strip a leading `.`, then require a domain match against the request host:
  `$host === $domain || str_ends_with($host, ".$domain")`. Single-label domains (`com`) are rejected. On
  success the cookie is stored non-host-only.
- **Path absent** → the RFC default-path: the request path up to and excluding its last `/`, or `/` if there is
  none.
- **Expiry precedence:** `Max-Age` wins over `Expires` when both are present. `Max-Age <= 0` deletes any
  existing entry immediately. Neither attribute → a session cookie, alive for the lifetime of the store.
- **Cookie prefixes are enforced**, since ignoring them defeats their entire purpose:
  - `__Secure-` requires `Secure`.
  - `__Host-` requires `Secure`, `Path=/`, and no `Domain`.

  A cookie whose name carries a prefix it does not satisfy is rejected.

**Documented limitation.** Without a public suffix list, `Domain=co.uk` on a response from `foo.co.uk` is
accepted, where a browser would refuse it. Bundling and refreshing a PSL is real ongoing maintenance for a
library with no other data dependencies, and the client role here targets APIs the application chose to call
rather than arbitrary hosts. This is stated in the `CookieStore` class docblock, not left implicit.

### Sending rules (`attachTo()`, RFC 6265 §5.4)

- A cookie is sent when: the domain matches (exact when host-only, suffix otherwise), the path matches, it is
  unexpired, and — if `Secure` — the request scheme is `https`.
- **Ordering is longest path first, then oldest first**, as the RFC specifies. Some servers depend on it.
- Values are `rawurlencode`d on the way out, symmetric with parsing.
- **An existing `Cookie` header on the request wins.** Stored cookies whose names already appear are skipped;
  the rest are appended. An explicit header at the call site is an intent the store should not second-guess.
- With no matches and no existing header, the request is returned untouched — no empty `Cookie:` header.

## `CookieAwareClient`

```php
final class CookieAwareClient implements IClient
{
    public function __construct(
        private readonly IClient $inner,
        private readonly CookieStore $store,
    );

    public function sendAsync(IRequest $request): PendingRequest;
    public function sendRequest(IRequest $request): IResponse;
}
```

`sendRequest()` attaches, delegates, absorbs using the request URI, and returns. It accepts and returns PSR
interfaces, so it decorates any PSR-18 client, not only this library's `Client`.

`sendAsync()` is not part of PSR-18 — it exists only on the concrete `Client` (`Client.php:50`). The decorator
therefore accepts `IClient` and `sendAsync()` throws `BadMethodCallException` when the wrapped client does not
provide it. An explicit failure at the call site beats a decorator that silently cannot be used asynchronously.

On the async path the decorator attaches cookies before dispatch and registers an `onResponse()` callback that
absorbs on settlement.

**Concurrency semantics.** With several transfers in flight, cookies are absorbed in completion order, which
cURL determines. If two concurrent responses set the same cookie, the last settlement wins. This is
nondeterministic and is documented in as many words on `sendAsync()`. Making it deterministic would require
serialising requests, which defeats the purpose of the async API.

## `PendingRequest::onResponse()` — change to existing code

`PendingRequest` is `final` and its `settle()` (`PendingRequest.php:129`) offers no way for anything to observe
completion, so async absorption is impossible without a hook.

```php
public function onResponse(callable $callback): void;   // callable(Response): void
```

- Callbacks fire inside `settle()`, after the `Response` is constructed.
- They do **not** fire on a network or parse error. A failed transfer has no cookies, and handing callers a
  half-settled state invites bugs.
- Registering after settlement invokes the callback immediately, so there is no race between `sendAsync()`
  returning and the caller attaching a handler.

This is a genuine gap in `PendingRequest` rather than a cookie-shaped hack — logging and metrics want the same
hook. The class stays `final`; the callback list is private.

## Testing

Tests mirror the source under `tests/Http/`.

- **`CookieTest`** — table-driven round-tripping of `parseSetCookie()` and `toSetCookieHeader()`; every
  constructor validation error; `Expires` formatting from a non-UTC `DateTimeImmutable`; unknown attributes
  ignored; both `Expires` and `Max-Age` retained; `expired()` output.
- **`CookieBagTest`** — `fromRequest()` with non-string keys and values present; **the no-double-decode rule**,
  asserting a `getCookieParams()` value of `100%25` survives as `100%25`; dedupe by name+domain+path;
  set-then-expire collapsing to one header; `applyTo()` appending rather than replacing an existing
  `Set-Cookie`.
- **`CookieStoreTest`** — every acceptance and sending rule above, each expiry case driven by `TestClock`;
  domain rejection cases; both cookie prefixes, accepted and rejected; ordering by path length then age; the
  existing-`Cookie`-header-wins rule.
- **`CookieAwareClientTest`** — an in-memory fake `IClient` rather than the network for the sync path;
  `BadMethodCallException` when a non-`Client` is wrapped and `sendAsync()` is called. Async coverage follows
  whatever fixture approach `tests/Http/ClientTest.php` already uses for cURL.
- **`PendingRequestTest`** — extend with `onResponse()`: fires on success, does not fire on error, fires
  immediately when registered post-settlement.

Coverage target is 100%, matching the rest of the module.

## Hand-off to the emitter spec

The response emitter is the next spec. One rule belongs to both designs and is recorded here so it does not
fall between them:

**The emitter must never pass `replace: true` to `header()` for `Set-Cookie`.** The obvious implementation —
looping over `$response->getHeaders()` and replacing on the first value of each header — silently deletes any
`Set-Cookie` PHP has already queued, most notably the session cookie written by `session_start()` inside
`NativeSession` (`NativeSession.php:218`). `NativeSession` does not and will not route its cookie through
`Cookie` or `CookieBag`; the emitter is the single point where PHP's own queued headers and the `Response`'s
headers meet, and it must merge them rather than clobber them.

That spec is now written: `docs/superpowers/specs/2026-08-25-http-response-emitter-design.md`, and the rule is
implemented and tested in `SapiEmitter::emitHeaders()`. `CookieBag::applyTo()` is observable end to end.
