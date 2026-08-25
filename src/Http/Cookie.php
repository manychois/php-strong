<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\Http;

use DateTimeImmutable;
use DateTimeZone;
use Exception;
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
     * The characters a `Domain` or `Path` attribute may consist of, i.e. RFC 6265 `av-octet` minus the separator.
     */
    private const ATTRIBUTE_PATTERN = '/^[\x20-\x3A\x3C-\x7E]*$/';

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
     * @throws InvalidArgumentException if the name is not a valid token, the domain or the path carries a character
     * illegal in a cookie attribute, or an attribute combination is one browsers reject.
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
        if ($domain !== null && preg_match(self::ATTRIBUTE_PATTERN, $domain) !== 1) {
            throw new InvalidArgumentException(
                sprintf('Cookie domain must not contain a control or separator character, got "%s".', $domain)
            );
        }
        if ($path !== null && preg_match(self::ATTRIBUTE_PATTERN, $path) !== 1) {
            throw new InvalidArgumentException(
                sprintf('Cookie path must not contain a control or separator character, got "%s".', $path)
            );
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
     * A name carrying a cookie prefix of RFC 6265bis gets the attributes that prefix demands, since a browser
     * rejects the clearing cookie otherwise and the cookie is never cleared: a `__Host-` name forces `Secure`,
     * `Path=/` and no `Domain`, and a `__Secure-` name forces `Secure`. Whatever the caller passed for a forced
     * attribute is ignored.
     *
     * @param string $name The name of the cookie to clear.
     * @param ?string $domain The domain of the cookie to clear, which must match the one it was set with.
     * @param ?string $path The path of the cookie to clear, which must match the one it was set with.
     *
     * @return self The cookie which clears it.
     */
    public static function expired(string $name, ?string $domain = null, ?string $path = null): self
    {
        $secure = false;
        if (str_starts_with($name, '__Host-')) {
            $secure = true;
            $domain = null;
            $path = '/';
        } elseif (str_starts_with($name, '__Secure-')) {
            $secure = true;
        }

        return new self(
            name: $name,
            value: '',
            expires: new DateTimeImmutable('@0'),
            maxAge: -1,
            domain: $domain,
            path: $path,
            secure: $secure,
        );
    }

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
}
