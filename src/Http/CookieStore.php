<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\Http;

use DateTimeImmutable;
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
        $this->prune();

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
        $this->prune();

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
        if ($slash === false || $slash === 0) {
            return '/';
        }

        return substr($requestPath, 0, $slash);
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
     * The `__Secure-` and `__Host-` cookie name prefixes defined by RFC 6265bis are enforced.
     *
     * @param Cookie $cookie The cookie as the response sent it.
     * @param string $host The lower-cased host the request was sent to.
     * @param string $requestPath The path the request was sent to.
     */
    private function store(Cookie $cookie, string $host, string $requestPath): void
    {
        if (str_starts_with($cookie->name, '__Secure-') && !$cookie->secure) {
            return;
        }
        if (str_starts_with($cookie->name, '__Host-')) {
            if (!$cookie->secure || $cookie->domain !== null || $cookie->path !== '/') {
                return;
            }
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

        $this->entries[$key] = new CookieEntry($resolved, $expiresAt, $hostOnly, $this->sequence);
        $this->sequence++;
    }
}
