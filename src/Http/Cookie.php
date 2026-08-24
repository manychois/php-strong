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
