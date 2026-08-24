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
