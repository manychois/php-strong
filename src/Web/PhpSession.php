<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\Web;

use Manychois\PhpStrong\ArrayReader;
use Manychois\PhpStrong\ArrayReaderInterface as IArrayReader;
use Manychois\PhpStrong\Collections\MapInterface as IMap;
use Manychois\PhpStrong\Internal\AbstractArrayReader;
use Manychois\PhpStrong\Web\PhpSessionInterface as IPhpSession;
use Override;

/**
 * Represents the PHP session and a wrapper of `$_SESSION`.
 */
class PhpSession extends AbstractArrayReader implements IPhpSession
{
    /**
     * @var array<string,mixed> The options to pass to `session_start()`.
     */
    private readonly array $options;

    /**
     * Initializes a PHP session.
     *
     * @param array<string,mixed> $options The options to pass to `session_start()`.
     */
    public function __construct(array $options = [])
    {
        parent::__construct();
        $this->options = $options;
    }

    private function autoStart(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start($this->options);
        }
    }

    #region extends AbstractArrayReader

    /**
     * @inheritDoc
     */
    #[Override]
    public function at(string $path): IArrayReader
    {
        $value = $this->get($path);
        if (is_array($value) || is_object($value)) {
            // @phpstan-ignore argument.type
            return new ArrayReader($value);
        }
        throw $this->createMismatchException($path, 'array or object', $value);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    protected function getRoot(): array|object
    {
        $this->autoStart();
        // @phpstan-ignore return.type
        return $_SESSION;
    }

    #endregion extends AbstractArrayReader

    #region implements IPhpSession

    /**
     * @inheritDoc
     */
    #[Override]
    public function clear(): void
    {
        $this->autoStart();
        $_SESSION = [];
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function remove(string $name): void
    {
        $this->autoStart();
        unset($_SESSION[$name]);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function set(string $name, mixed $value): void
    {
        $this->autoStart();
        $_SESSION[$name] = $value;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function with(array|IMap $overrides): IPhpSession
    {
        $this->autoStart();
        if ($overrides instanceof IMap) {
            $overrides = $overrides->asArray();
        }
        foreach ($overrides as $key => $value) {
            if ($value === null) {
                unset($_SESSION[$key]);
            } else {
                $_SESSION[$key] = $value;
            }
        }
        return new self($this->options);
    }

    #endregion implements IPhpSession
}
