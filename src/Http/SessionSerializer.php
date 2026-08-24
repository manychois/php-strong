<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\Http;

/**
 * The handler PHP uses to serialize session data, i.e. the `session.serialize_handler` setting.
 */
enum SessionSerializer: string
{
    /**
     * The default handler of PHP, which stores entries as `key|serialized`.
     *
     * Because the key is delimited by a `|`, a numeric top-level key is silently dropped, and a top-level key which
     * contains a `|` fails the whole session write.
     */
    case Php = 'php';

    /**
     * The same format as `Php`, with the length of each key written before it rather than a delimiter after it.
     *
     * A top-level key may therefore contain a `|`, but one longer than 127 bytes is dropped, since its length is
     * written as a single byte. A numeric top-level key is dropped as well.
     */
    case PhpBinary = 'php_binary';

    /**
     * Serializes the session data as one array, with the plain `serialize()` format.
     *
     * It is the only handler which stores every key faithfully, numeric ones and those containing a `|` included, and
     * the one to choose when the session keys are not fully under your control.
     */
    case PhpSerialize = 'php_serialize';
}
