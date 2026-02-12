<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\Web;

use Manychois\PhpStrong\ArrayAccessorInterface;

/**
 * Represents a session that uses the native PHP session mechanism.
 */
interface PhpSessionInterface extends ArrayAccessorInterface
{
    /**
     * Clears the session data.
     */
    public function clear(): void;

    /**
     * Destroys the session.
     * The session cookie is also cleared.
     */
    public function destroy(): void;
}
