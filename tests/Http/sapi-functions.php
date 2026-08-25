<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\Http;

use Manychois\PhpStrongTests\Http\SapiSpy;

/**
 * Shadows the global `header()` for every unqualified call made from this namespace.
 *
 * @param string $header The header line.
 * @param bool $replace Whether to replace an existing header of the same name.
 * @param int $response_code The status code to set, or 0 for none.
 */
function header(string $header, bool $replace = true, int $response_code = 0): void
{
    SapiSpy::header($header, $replace, $response_code);
}

/**
 * Shadows the global `headers_sent()` for every unqualified call made from this namespace.
 *
 * @param ?string $filename Receives the file which started the output.
 * @param ?int $line Receives the line which started the output.
 *
 * @return bool True if output has been marked as started; false otherwise.
 */
function headers_sent(?string &$filename = null, ?int &$line = null): bool
{
    return SapiSpy::headersSent($filename, $line);
}
