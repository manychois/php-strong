<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\Http;

use DateTimeImmutable;
use InvalidArgumentException;
use Manychois\PhpStrong\Http\Internal\CookieEntry;
use Manychois\PhpStrong\Time\UtcClock;
use Psr\Clock\ClockInterface as IClock;
use Psr\Http\Message\RequestInterface as IRequest;
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
     * The request URI is needed because a response carries none of its own: the domain and the path a cookie
     * defaults to are derived from it, and so is whether the connection counted as a secure one.
     *
     * @param IResponse $response The response to read `Set-Cookie` headers from.
     * @param IUri $requestUri The URI the request was sent to.
     */
    public function absorb(IResponse $response, IUri $requestUri): void
    {
        $this->prune();

        $host = strtolower($requestUri->getHost());
        $secureRequest = strtolower($requestUri->getScheme()) === 'https';
        foreach ($response->getHeader('Set-Cookie') as $line) {
            try {
                $cookie = Cookie::parseSetCookie($line);
            } catch (InvalidArgumentException) {
                continue;
            }

            $this->store($cookie, self::rawValueIn($line), $host, $requestUri->getPath(), $secureRequest);
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
        $this->prune();

        return array_values(array_map(static fn (CookieEntry $e): Cookie => $e->cookie, $this->entries));
    }

    /**
     * Adds a `Cookie` header carrying every stored cookie the request should send.
     *
     * Each value is written exactly as the `Set-Cookie` header carried it, which RFC 6265 requires: a cookie value
     * is opaque to a client, so re-encoding one that never arrived encoded would hand the server a different value
     * from the one it set.
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
            static fn (CookieEntry $e): string => $e->cookie->name . '=' . $e->rawValue,
            $matches
        );
        $joined = implode('; ', $pairs);

        return $request->withHeader('Cookie', $existingHeader === '' ? $joined : $existingHeader . '; ' . $joined);
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
        if ($slash === false || $slash === 0) {
            return '/';
        }

        return substr($requestPath, 0, $slash);
    }

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

    /**
     * Reads the cookie value out of a `Set-Cookie` header exactly as it arrived, without decoding it or stripping
     * the quotes a server may have wrapped it in, so that it can be echoed back octet for octet.
     *
     * @param string $header The header value the cookie was parsed from.
     *
     * @return string The raw value, which is `''` when the header carries no `=`.
     */
    private static function rawValueIn(string $header): string
    {
        $first = explode(';', $header)[0];
        $equals = strpos($first, '=');

        return $equals === false ? '' : trim(substr($first, $equals + 1));
    }

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

    /**
     * Applies the acceptance rules of RFC 6265 and stores the cookie if it passes them.
     *
     * The `__Secure-` and `__Host-` cookie name prefixes defined by RFC 6265bis are enforced. A request which was
     * not made over HTTPS can neither set a cookie which claims to be secure — by the `Secure` attribute or by
     * either name prefix — nor overwrite an entry already stored as secure, since the storage key carries no scheme
     * and an on-path attacker on a plain request could otherwise replace a cookie set over a secure one.
     *
     * @param Cookie $cookie The cookie as the response sent it.
     * @param string $rawValue The value exactly as the `Set-Cookie` header carried it.
     * @param string $host The lower-cased host the request was sent to.
     * @param string $requestPath The path the request was sent to.
     * @param bool $secureRequest Whether the request was made over a secure scheme.
     */
    private function store(
        Cookie $cookie,
        string $rawValue,
        string $host,
        string $requestPath,
        bool $secureRequest,
    ): void {
        if (str_starts_with($cookie->name, '__Secure-') && !$cookie->secure) {
            return;
        }
        if (str_starts_with($cookie->name, '__Host-')) {
            if (!$cookie->secure || $cookie->domain !== null || $cookie->path !== '/') {
                return;
            }
        }
        if (!$secureRequest && $cookie->secure) {
            return;
        }

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
        $held = $this->entries[$key] ?? null;
        if ($held !== null && $held->cookie->secure && !$secureRequest) {
            return;
        }

        $now = $this->clock->now();
        $expiresAt = null;
        if ($cookie->maxAge !== null) {
            if ($cookie->maxAge <= 0) {
                unset($this->entries[$key]);

                return;
            }

            $moved = $now->modify(sprintf('+%d seconds', $cookie->maxAge));
            $expiresAt = $moved instanceof DateTimeImmutable ? $moved : $now;
        } elseif ($cookie->expires !== null) {
            if ($cookie->expires <= $now) {
                unset($this->entries[$key]);

                return;
            }

            $expiresAt = $cookie->expires;
        }

        $this->entries[$key] = new CookieEntry($resolved, $rawValue, $expiresAt, $hostOnly, $this->sequence);
        $this->sequence++;
    }
}
