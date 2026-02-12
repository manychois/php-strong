<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\Web;

use Manychois\PhpStrong\ArrayAccessor;

/**
 * Represents a session that uses the native PHP session mechanism.
 */
class PhpSession extends ArrayAccessor implements PhpSessionInterface
{
    public readonly string $name;
    /**
     * @var array<string,mixed>
     */
    private array $initOptions;

    /**
     * Creates a new PhpSession instance.
     *
     * @param string              $name        The name of the session.
     * @param array<string,mixed> $initOptions The options to be used when initializing the session.
     *                                         Refer to `session_start` for available options.
     */
    public function __construct(string $name = 'PHPSESSID', array $initOptions = [])
    {
        $this->name = $name;
        $this->initOptions = $initOptions;

        $empty = [];

        parent::__construct($empty);
    }

    #region implements PhpSessionInterface

    /**
     * @inheritDoc
     */
    public function clear(): void
    {
        $this->startSession();
        // phpcs:ignore SlevomatCodingStandard.Variables.DisallowSuperGlobalVariable.DisallowedSuperGlobalVariable
        $_SESSION = [];
    }

    /**
     * @inheritDoc
     */
    public function destroy(): void
    {
        $this->startSession();
        // phpcs:ignore SlevomatCodingStandard.Variables.DisallowSuperGlobalVariable.DisallowedSuperGlobalVariable
        $_SESSION = [];
        $useCookie = \boolval(\ini_get('session.use_cookies'));
        if ($useCookie) {
            $params = \session_get_cookie_params();
            $sessionName = \session_name();
            \assert(\is_string($sessionName));
            \setcookie(
                $sessionName,
                '',
                \time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }
        \session_destroy();
    }

    #endregion implements PhpSessionInterface

    #region extends ArrayAccessor

    /**
     * @inheritDoc
     */
    public function get(string $key): mixed
    {
        $this->startSession();

        // phpcs:disable SlevomatCodingStandard.Variables.DisallowSuperGlobalVariable.DisallowedSuperGlobalVariable

        return $_SESSION[$key] ?? null;
        // phpcs:enable
    }

    /**
     * @inheritDoc
     */
    public function set(string $key, mixed $value): void
    {
        $this->startSession();
        // phpcs:ignore SlevomatCodingStandard.Variables.DisallowSuperGlobalVariable.DisallowedSuperGlobalVariable
        $_SESSION[$key] = $value;
    }

    /**
     * @inheritDoc
     */
    public function has(string $key): bool
    {
        $this->startSession();

        // phpcs:disable SlevomatCodingStandard.Variables.DisallowSuperGlobalVariable.DisallowedSuperGlobalVariable

        return \array_key_exists($key, $_SESSION);
        // phpcs:enable
    }

    /**
     * @inheritDoc
     */
    public function delete(string $key): void
    {
        $this->startSession();
        // phpcs:ignore SlevomatCodingStandard.Variables.DisallowSuperGlobalVariable.DisallowedSuperGlobalVariable
        unset($_SESSION[$key]);
    }

    #endregion extends ArrayAccessor

    private function startSession(): void
    {
        if (\session_status() === \PHP_SESSION_NONE) {
            \session_name($this->name);
            $options = \array_merge([
                'cookie_httponly' => true,
                'cookie_lifetime' => 0,
                'cookie_samesite' => 'Lax',
                'cookie_secure' => true,
                'use_only_cookies' => true,
                'use_strict_mode' => true,
            ], $this->initOptions);
            \session_start($options);
        }
        // phpcs:disable SlevomatCodingStandard.Variables.DisallowSuperGlobalVariable.DisallowedSuperGlobalVariable
        // @phpstan-ignore assign.propertyType
        $this->inner = &$_SESSION;
        // phpcs:enable
    }
}
