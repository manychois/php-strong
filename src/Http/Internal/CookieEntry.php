<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\Http\Internal;

use DateTimeImmutable;
use Manychois\PhpStrong\Http\Cookie;

/**
 * One cookie held by a cookie store, together with everything the store needs which the cookie itself does not
 * carry: when it actually expires, whether it is bound to one exact host, and the order it arrived in.
 *
 * @internal
 */
final class CookieEntry
{
    /**
     * @param Cookie $cookie The cookie, with its domain and path resolved against the request it arrived on.
     * @param ?DateTimeImmutable $expiresAt The instant it expires, or `null` for a session cookie.
     * @param bool $hostOnly Whether it is sent only to the exact host which set it.
     * @param int $sequence The order it was stored in, used to break ties when ordering cookies for sending.
     */
    public function __construct(
        public readonly Cookie $cookie,
        public readonly ?DateTimeImmutable $expiresAt,
        public readonly bool $hostOnly,
        public readonly int $sequence,
    ) {
    }
}
