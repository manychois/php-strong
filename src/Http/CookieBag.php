<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\Http;

use Psr\Http\Message\ResponseInterface as IResponse;
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
     * @var array<string,Cookie>
     */
    private array $outgoing = [];

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
}
