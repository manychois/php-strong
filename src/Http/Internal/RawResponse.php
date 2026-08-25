<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\Http\Internal;

use Manychois\PhpStrong\Http\ClientException;

/**
 * An HTTP response in its wire form, before conversion into a PSR-7 response.
 *
 * @internal
 */
final class RawResponse
{
    /**
     * @param array<string,list<string>> $headers
     */
    public function __construct(
        public readonly string $protocolVersion,
        public readonly int $statusCode,
        public readonly string $reasonPhrase,
        public readonly array $headers,
        public readonly string $body,
    ) {
    }

    /**
     * Parses raw response header lines into a RawResponse.
     * When multiple header blocks are present (e.g. after a "100 Continue"),
     * only the final block is kept.
     *
     * @param list<string> $lines The header lines, without trailing CRLF.
     * @param string $body The response body.
     *
     * @return self The parsed response.
     *
     * @throws ClientException if no status line is present.
     */
    public static function fromHeaderLines(array $lines, string $body): self
    {
        $protocolVersion = null;
        $statusCode = 0;
        $reasonPhrase = '';
        $headers = [];
        foreach ($lines as $line) {
            $matched = preg_match('#^HTTP/(\d+(?:\.\d+)?) (\d{3})(?: (.*))?$#', $line, $matches);
            if ($matched === 1) {
                $protocolVersion = $matches[1];
                $statusCode = (int) $matches[2];
                $reasonPhrase = $matches[3] ?? '';
                $headers = [];

                continue;
            }

            $colonPos = strpos($line, ':');
            if ($colonPos === false || $colonPos === 0) {
                continue;
            }

            $name = substr($line, 0, $colonPos);
            $value = trim(substr($line, $colonPos + 1));
            $headers[$name][] = $value;
        }

        if ($protocolVersion === null) {
            throw new ClientException('Malformed HTTP response: no status line found.');
        }

        return new self($protocolVersion, $statusCode, $reasonPhrase, $headers, $body);
    }
}
