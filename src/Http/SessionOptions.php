<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\Http;

use InvalidArgumentException;

/**
 * Aggregates the settings a {@see NativeSession} passes to `session_start()`.
 *
 * Every option defaults to `null`, which means the setting is not passed at all and PHP keeps the value it is
 * configured with in `php.ini`. Set only what the application needs to decide for itself.
 */
final class SessionOptions
{
    /**
     * The settings a dedicated option already controls, which therefore may not appear in `$ini`.
     */
    private const RESERVED_INI = [
        'name',
        'save_path',
        'cookie_lifetime',
        'cookie_path',
        'cookie_domain',
        'cookie_secure',
        'cookie_httponly',
        'cookie_samesite',
        'cookie_partitioned',
        'use_strict_mode',
        'use_only_cookies',
        'gc_maxlifetime',
        'serialize_handler',
        'read_and_close',
    ];

    /**
     * @var array<string,bool|float|int|string>
     */
    public readonly array $ini;

    /**
     * @param ?string $name The name of the session, i.e. the name of its cookie. `null` keeps the name PHP is
     * configured with.
     * @param ?string $savePath The directory session files are written to. `null` keeps the path PHP is configured
     * with.
     * @param ?int $cookieLifetime The lifetime of the session cookie, in seconds; `0` until the browser closes.
     * @param ?string $cookiePath The path the session cookie is sent for.
     * @param ?string $cookieDomain The domain the session cookie is sent for; an empty string means the current host
     * only.
     * @param ?bool $cookieSecure Whether the session cookie is sent over HTTPS only.
     * @param ?bool $cookieHttpOnly Whether the session cookie is hidden from JavaScript.
     * @param ?SameSite $cookieSameSite When the browser sends the session cookie with a cross-site request.
     * @param ?bool $cookiePartitioned Whether the session cookie is partitioned per top-level site (CHIPS), which
     * browsers accept on a secure cookie only.
     * @param ?bool $useStrictMode Whether PHP refuses a session id it did not generate itself.
     * @param ?bool $useOnlyCookies Whether the session id is read from a cookie only, never from the URL.
     * @param ?int $gcMaxLifetime The number of seconds an idle session survives before it may be collected. `null`
     * keeps the value PHP is configured with.
     * @param ?SessionSerializer $serializeHandler The handler which serializes the session data. `null` keeps the
     * handler PHP is configured with.
     * @param ?bool $readAndClose Whether the session is read once and closed straight away, releasing its lock so
     * that concurrent requests of the same visitor are not held up. The session becomes read-only: every member which
     * would write throws.
     * @param array $ini Any further session settings, passed to `session_start()` verbatim. Keys are setting names
     * without the `session.` prefix, e.g. `gc_probability`, and none may be one a dedicated option above already
     * controls.
     *
     * @throws InvalidArgumentException if any value is unusable.
     *
     * @phpstan-param ?non-negative-int $cookieLifetime
     * @phpstan-param ?positive-int $gcMaxLifetime
     * @phpstan-param array<string,bool|float|int|string> $ini
     */
    public function __construct(
        public readonly ?string $name = null,
        public readonly ?string $savePath = null,
        public readonly ?int $cookieLifetime = null,
        public readonly ?string $cookiePath = null,
        public readonly ?string $cookieDomain = null,
        public readonly ?bool $cookieSecure = null,
        public readonly ?bool $cookieHttpOnly = null,
        public readonly ?SameSite $cookieSameSite = null,
        public readonly ?bool $cookiePartitioned = null,
        public readonly ?bool $useStrictMode = null,
        public readonly ?bool $useOnlyCookies = null,
        public readonly ?int $gcMaxLifetime = null,
        public readonly ?SessionSerializer $serializeHandler = null,
        public readonly ?bool $readAndClose = null,
        array $ini = [],
    ) {
        if ($name !== null) {
            if (preg_match('/^[A-Za-z0-9_-]+$/', $name) !== 1) {
                throw new InvalidArgumentException(
                    sprintf('Session name must consist of letters, digits, dashes and underscores, got "%s".', $name)
                );
            }
            if (preg_match('/^[0-9]+$/', $name) === 1) {
                throw new InvalidArgumentException('Session name must not consist of digits only.');
            }
        }
        if ($savePath === '') {
            throw new InvalidArgumentException('Save path must not be an empty string.');
        }
        if ($cookieLifetime !== null && $cookieLifetime < 0) {
            throw new InvalidArgumentException(
                sprintf('Cookie lifetime must not be negative, got %d.', $cookieLifetime)
            );
        }
        if ($cookiePath === '') {
            throw new InvalidArgumentException('Cookie path must not be an empty string.');
        }
        if ($gcMaxLifetime !== null && $gcMaxLifetime <= 0) {
            throw new InvalidArgumentException(
                sprintf('Garbage collection max lifetime must be greater than 0, got %d.', $gcMaxLifetime)
            );
        }
        if ($cookieSameSite === SameSite::None && $cookieSecure === false) {
            throw new InvalidArgumentException('SameSite None requires a secure cookie, which browsers enforce.');
        }
        if ($cookiePartitioned === true && $cookieSecure === false) {
            throw new InvalidArgumentException('A partitioned cookie must be secure, which browsers enforce.');
        }

        foreach (array_keys($ini) as $key) {
            if (str_starts_with($key, 'session.')) {
                throw new InvalidArgumentException(
                    sprintf('Setting "%s" must be named without the "session." prefix.', $key)
                );
            }
            if (in_array($key, self::RESERVED_INI, true)) {
                throw new InvalidArgumentException(
                    sprintf('Setting "%s" is controlled by a dedicated option; set that instead.', $key)
                );
            }
        }

        $this->ini = $ini;
    }
}
