<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\Http\Internal;

use CurlHandle;
use Manychois\PhpStrong\Http\NetworkException;
use Override;
use Psr\Http\Message\RequestInterface as IRequest;

/**
 * A transport backed by the cURL extension.
 *
 * @internal
 */
final class CurlTransport implements TransportInterface
{
    /**
     * Converts a request into a cURL option array.
     *
     * @param IRequest $request The request to convert.
     * @param float $timeout The connection and response timeout, in seconds.
     *
     * @return array<int,mixed> The cURL options, keyed by CURLOPT_* constants.
     */
    public static function buildOptions(IRequest $request, float $timeout): array
    {
        $timeoutMs = (int) ceil($timeout * 1000);
        $options = [
            \CURLOPT_URL => (string) $request->getUri(),
            \CURLOPT_CUSTOMREQUEST => $request->getMethod(),
            \CURLOPT_RETURNTRANSFER => true,
            \CURLOPT_FOLLOWLOCATION => false,
            \CURLOPT_CONNECTTIMEOUT_MS => $timeoutMs,
            \CURLOPT_TIMEOUT_MS => $timeoutMs,
            \CURLOPT_HTTP_VERSION => match ($request->getProtocolVersion()) {
                '1.0' => \CURL_HTTP_VERSION_1_0,
                '2', '2.0' => \CURL_HTTP_VERSION_2_0,
                default => \CURL_HTTP_VERSION_1_1,
            },
        ];
        if ($request->getMethod() === 'HEAD') {
            $options[\CURLOPT_NOBODY] = true;
        }

        $headerLines = [];
        foreach ($request->getHeaders() as $name => $values) {
            foreach ($values as $value) {
                $headerLines[] = $value === '' ? sprintf('%s;', $name) : sprintf('%s: %s', $name, $value);
            }
        }
        if ($headerLines !== []) {
            $options[\CURLOPT_HTTPHEADER] = $headerLines;
        }

        $body = (string) $request->getBody();
        if ($body !== '') {
            $options[\CURLOPT_POSTFIELDS] = $body;
        }

        return $options;
    }

    #region implements TransportInterface

    /**
     * @inheritDoc
     */
    #[Override]
    public function send(IRequest $request, float $timeout): RawResponse
    {
        $headerLines = [];
        $options = self::buildOptions($request, $timeout);
        $options[\CURLOPT_HEADERFUNCTION] =
            static function (CurlHandle $handle, string $line) use (&$headerLines): int {
                $trimmed = trim($line);
                if ($trimmed !== '') {
                    $headerLines[] = $trimmed;
                }

                return strlen($line);
            };

        $handle = curl_init();
        curl_setopt_array($handle, $options);
        $body = curl_exec($handle);
        if (!is_string($body)) {
            throw new NetworkException(
                sprintf('cURL error %d: %s', curl_errno($handle), curl_error($handle)),
                $request,
            );
        }

        return RawResponse::fromHeaderLines($headerLines, $body);
    }

    #endregion implements TransportInterface
}
