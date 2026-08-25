<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\Http;

use InvalidArgumentException;
use Manychois\PhpStrong\Collections\DataReaderInterface as IDataReader;

/**
 * Represents the data of one visitor's session, read with the strong typing of a data reader.
 *
 * Every read member is inherited from `DataReaderInterface`, dot notation included, and the write members below
 * accept a key in the same notation.
 */
interface SessionInterface extends IDataReader
{
    /**
     * Removes every value, leaving the session itself alive.
     */
    public function clear(): void;

    /**
     * Ends the session and drops its data.
     */
    public function destroy(): void;

    /**
     * Returns the id of the session.
     *
     * @return string The session id, or an empty string when the session has not started.
     */
    public function id(): string;

    /**
     * Checks whether the session has started.
     *
     * @return bool True if the session has started; false otherwise.
     */
    public function isStarted(): bool;

    /**
     * Replaces the id of the session, keeping its data.
     *
     * Call this whenever the privileges of the visitor change, e.g. right after a sign-in, to defend against session
     * fixation.
     *
     * @param bool $deleteOldSession Whether to delete the data stored under the old id.
     */
    public function regenerate(bool $deleteOldSession = true): void;

    /**
     * Removes the value stored under a key.
     *
     * An absent key is ignored.
     *
     * @param string $key The key to remove, in dot notation for a nested value.
     */
    public function remove(string $key): void;

    /**
     * Stores a value under a key.
     *
     * @param string $key The key to write to, in dot notation for a nested value.
     * @param mixed $value The value to store.
     *
     * @throws InvalidArgumentException if the key cannot be written to.
     */
    public function set(string $key, mixed $value): void;
}
