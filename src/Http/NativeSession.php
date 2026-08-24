<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\Http;

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
 * read or write of a value. `id()` and `isStarted()` deliberately do not start it.
 *
 * A key may be written in dot notation to reach a value nested inside the session data, e.g. `user.address.city`.
 * Writing to a path whose segments do not exist creates them as arrays.
 */
final class NativeSession extends AbstractDataReader implements ISession
{
    /**
     * Starts the session unless it is already active.
     */
    private function start(): void
    {
        if (session_status() === \PHP_SESSION_ACTIVE) {
            return;
        }

        session_start();
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
        $this->start();
        $_SESSION = [];
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function destroy(): void
    {
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
        $this->start();
        session_regenerate_id($deleteOldSession);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function remove(string $key): void
    {
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
}
