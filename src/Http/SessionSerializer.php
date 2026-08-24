<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\Http;

/**
 * The handler PHP uses to serialize session data, i.e. the `session.serialize_handler` setting.
 *
 * `Php` is the default of PHP itself. It stores entries as `key|serialized`, which silently drops a numeric top-level
 * key and fails the whole write when a key contains a `|`. `PhpSerialize` serializes the data array as a whole and
 * has neither limitation.
 */
enum SessionSerializer: string
{
    case Php = 'php';
    case PhpBinary = 'php_binary';
    case PhpSerialize = 'php_serialize';
}
