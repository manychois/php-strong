<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\Http;

/**
 * The `SameSite` attribute of a cookie, which tells the browser when to send it with a cross-site request.
 *
 * `None` is only honoured on a cookie which is also marked secure; browsers reject the combination otherwise.
 */
enum SameSite: string
{
    case Lax = 'Lax';
    case None = 'None';
    case Strict = 'Strict';
}
