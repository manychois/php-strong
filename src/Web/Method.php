<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\Web;

use InvalidArgumentException;

/**
 * HTTP request methods from RFC 9110 (Sections 9.3–9.10), plus PATCH (RFC 5789).
 */
enum Method: string
{
    case Connect = 'CONNECT';
    case Delete = 'DELETE';
    case Get = 'GET';
    case Head = 'HEAD';
    case Options = 'OPTIONS';
    case Patch = 'PATCH';
    case Post = 'POST';
    case Put = 'PUT';
    case Trace = 'TRACE';

    /**
     * Resolves a method by name. Matching is case-insensitive; values follow the uppercase registry spelling.
     *
     * @throws InvalidArgumentException
     */
    public static function fromString(string $method): self
    {
        $normalized = strtoupper($method);

        return self::tryFrom($normalized) ?? throw new InvalidArgumentException(sprintf(
            'Unknown HTTP method: %s',
            $method,
        ));
    }
}
