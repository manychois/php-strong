<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\Http;

use BadMethodCallException;
use InvalidArgumentException;
use Manychois\PhpStrong\Collections\DataReader;
use Manychois\PhpStrong\Collections\DataReaderInterface as IDataReader;
use Manychois\PhpStrong\Collections\Internal\AbstractDataReader;
use Manychois\PhpStrong\Http\SessionInterface as ISession;
use Override;

/**
 * Reads and writes the session of PHP itself, i.e. the `$_SESSION` superglobal.
 *
 * The session starts lazily: constructing this class touches nothing, and `session_start()` is called on the first
 * read or write of a value, passing the {@see NativeSessionOptions} given to the constructor to `session_start()`.
 * `id()` and `isStarted()` deliberately do not start it.
 *
 * With `readAndClose` the session is read once and closed immediately, so `isStarted()` reports false from then on
 * and every member which would write throws `BadMethodCallException`.
 *
 * A key may be written in dot notation to reach a value nested inside the session data, e.g. `user.address.city`.
 * Writing to a path whose segments do not exist creates them as arrays.
 */
final class NativeSession extends AbstractDataReader implements ISession
{
    private bool $loaded = false;

    /**
     * Initializes a new instance of the NativeSession class.
     *
     * @param NativeSessionOptions $options The settings passed to `session_start()`.
     */
    public function __construct(private readonly NativeSessionOptions $options = new NativeSessionOptions())
    {
    }

    #region extends AbstractDataReader

    /**
     * @inheritDoc
     */
    #[Override]
    protected function createReader(array|object $source): IDataReader
    {
        return new DataReader($source);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    protected function source(): array
    {
        $this->start();

        return self::stringKeyed($_SESSION);
    }

    #endregion extends AbstractDataReader

    #region implements ISession

    /**
     * @inheritDoc
     */
    #[Override]
    public function clear(): void
    {
        $this->refuseWhenReadOnly();
        $this->start();
        $_SESSION = [];
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function destroy(): void
    {
        $this->refuseWhenReadOnly();
        if (session_status() !== \PHP_SESSION_ACTIVE) {
            return;
        }

        $_SESSION = [];
        session_destroy();
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function id(): string
    {
        if (session_status() !== \PHP_SESSION_ACTIVE) {
            return '';
        }

        $id = session_id();

        return $id === false ? '' : $id;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function isStarted(): bool
    {
        return session_status() === \PHP_SESSION_ACTIVE;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function regenerate(bool $deleteOldSession = true): void
    {
        $this->refuseWhenReadOnly();
        $this->start();
        session_regenerate_id($deleteOldSession);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function remove(string $key): void
    {
        $this->refuseWhenReadOnly();
        $this->start();
        if (array_key_exists($key, $_SESSION)) {
            unset($_SESSION[$key]);

            return;
        }

        $segments = explode('.', $key);
        $last = array_pop($segments);
        $node = &$_SESSION;
        foreach ($segments as $segment) {
            if (!is_array($node) || !array_key_exists($segment, $node)) {
                return;
            }

            $node = &$node[$segment];
        }

        if (!is_array($node)) {
            return;
        }

        unset($node[$last]);
    }

    /**
     * @inheritDoc
     *
     * A segment which exists but holds a value other than an array is not overwritten; writing through it throws
     * instead, so that no data is silently discarded. A numeric key is rejected as well, since PHP would store it as
     * an integer and `keys()` promises strings.
     */
    #[Override]
    public function set(string $key, mixed $value): void
    {
        $this->refuseWhenReadOnly();
        $this->start();
        if (array_key_exists($key, $_SESSION)) {
            $_SESSION[$key] = $value;

            return;
        }

        $segments = explode('.', $key);
        if (is_numeric($segments[0])) {
            throw new InvalidArgumentException(sprintf('Top-level key "%s" is numeric.', $segments[0]));
        }

        $last = array_pop($segments);
        $node = &$_SESSION;
        $walked = [];
        foreach ($segments as $segment) {
            $walked[] = $segment;
            if (!array_key_exists($segment, $node)) {
                $node[$segment] = [];
            } elseif (!is_array($node[$segment])) {
                throw new InvalidArgumentException(
                    sprintf('Key "%s" holds a value which is not an array.', implode('.', $walked))
                );
            }

            $node = &$node[$segment];
        }

        $node[$last] = $value;
    }

    #endregion implements ISession

    /**
     * Starts the session unless it is already active.
     */
    private function start(): void
    {
        if (session_status() === \PHP_SESSION_ACTIVE || $this->loaded) {
            return;
        }

        session_start($this->startOptions());
        $this->loaded = true;
    }

    /**
     * Builds the options `session_start()` is called with.
     *
     * The keys of the array are setting names without the `session.` prefix, which is the form `session_start()`
     * accepts; a prefixed key is rejected by PHP.
     *
     * @return array The options for `session_start()`.
     *
     * @phpstan-return array<string,bool|float|int|string>
     */
    private function startOptions(): array
    {
        $options = [
            'cookie_lifetime' => $this->options->cookieLifetime,
            'cookie_path' => $this->options->cookiePath,
            'cookie_domain' => $this->options->cookieDomain,
            'cookie_secure' => $this->options->cookieSecure,
            'cookie_httponly' => $this->options->cookieHttpOnly,
            'cookie_samesite' => $this->options->cookieSameSite->value,
            'cookie_partitioned' => $this->options->cookiePartitioned,
            'use_strict_mode' => $this->options->useStrictMode,
            'use_only_cookies' => $this->options->useOnlyCookies,
        ];
        if ($this->options->name !== null) {
            $options['name'] = $this->options->name;
        }
        if ($this->options->savePath !== null) {
            $options['save_path'] = $this->options->savePath;
        }
        if ($this->options->gcMaxLifetime !== null) {
            $options['gc_maxlifetime'] = $this->options->gcMaxLifetime;
        }
        if ($this->options->serializeHandler !== null) {
            $options['serialize_handler'] = $this->options->serializeHandler->value;
        }
        if ($this->options->readAndClose) {
            $options['read_and_close'] = true;
        }
        foreach ($this->options->ini as $key => $value) {
            $options[$key] = $value;
        }

        return $options;
    }

    /**
     * Throws when the session was read and closed, and therefore cannot be written to.
     *
     * @throws BadMethodCallException if the session is read-only.
     */
    private function refuseWhenReadOnly(): void
    {
        if ($this->options->readAndClose) {
            throw new BadMethodCallException('A session read with readAndClose is read-only.');
        }
    }
}
