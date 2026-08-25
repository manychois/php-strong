<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\Http\Internal;

use CurlHandle;
use Manychois\PhpStrong\Http\NetworkException;
use Manychois\PhpStrong\Http\RequestException;
use Manychois\PhpStrong\Http\RequestOptions;
use Override;
use Psr\Http\Message\RequestInterface as IRequest;
use Throwable;

/**
 * A transport backed by the cURL extension.
 *
 * @internal
 */
final class CurlTransport implements TransportInterface
{
    /**
     * Converts a request and its options into a cURL option array.
     *
     * @param IRequest $request The request to convert.
     * @param RequestOptions $options The transport-level options to apply.
     *
     * @return array<int,mixed> The cURL options, keyed by CURLOPT_* constants.
     *
     * @throws RequestException if the request body cannot be read.
     */
    public static function buildOptions(IRequest $request, RequestOptions $options): array
    {
        $curlOptions = [
            \CURLOPT_URL => (string) $request->getUri(),
            \CURLOPT_CUSTOMREQUEST => $request->getMethod(),
            \CURLOPT_RETURNTRANSFER => true,
            \CURLOPT_FOLLOWLOCATION => $options->followRedirects,
            \CURLOPT_CONNECTTIMEOUT_MS => (int) ceil($options->connectTimeout * 1000),
            \CURLOPT_TIMEOUT_MS => (int) ceil($options->timeout * 1000),
            \CURLOPT_SSL_VERIFYPEER => $options->verifyTls,
            \CURLOPT_SSL_VERIFYHOST => $options->verifyTls ? 2 : 0,
            \CURLOPT_HTTP_VERSION => match ($request->getProtocolVersion()) {
                '1.0' => \CURL_HTTP_VERSION_1_0,
                '2', '2.0' => \CURL_HTTP_VERSION_2_0,
                default => \CURL_HTTP_VERSION_1_1,
            },
        ];
        if ($options->followRedirects) {
            $curlOptions[\CURLOPT_MAXREDIRS] = $options->maxRedirects;
        }
        if ($options->proxy !== null) {
            $curlOptions[\CURLOPT_PROXY] = $options->proxy;
        }
        if ($options->userAgent !== null && !$request->hasHeader('User-Agent')) {
            $curlOptions[\CURLOPT_USERAGENT] = $options->userAgent;
        }
        if ($options->caFile !== null) {
            $curlOptions[\CURLOPT_CAINFO] = $options->caFile;
        }
        if ($options->caPath !== null) {
            $curlOptions[\CURLOPT_CAPATH] = $options->caPath;
        }
        if ($request->getMethod() === 'HEAD') {
            $curlOptions[\CURLOPT_NOBODY] = true;
        }

        $headerLines = [];
        foreach ($request->getHeaders() as $name => $values) {
            $name = (string) $name;
            foreach ($values as $value) {
                $headerLines[] = $value === '' ? sprintf('%s;', $name) : sprintf('%s: %s', $name, $value);
            }
        }
        if ($headerLines !== []) {
            $curlOptions[\CURLOPT_HTTPHEADER] = $headerLines;
        }

        try {
            $body = (string) $request->getBody();
        } catch (Throwable $ex) {
            throw new RequestException('Failed to read the request body.', $request, $ex);
        }
        if ($body !== '') {
            $curlOptions[\CURLOPT_POSTFIELDS] = $body;
        }

        return $curlOptions;
    }

    #region implements TransportInterface

    /**
     * @inheritDoc
     */
    #[Override]
    public function send(IRequest $request, RequestOptions $options): RawResponse
    {
        $headerLines = [];
        $curlOptions = self::buildOptions($request, $options);
        $curlOptions[\CURLOPT_HEADERFUNCTION] =
            static function (CurlHandle $handle, string $line) use (&$headerLines): int {
                $trimmed = trim($line);
                if ($trimmed !== '') {
                    $headerLines[] = $trimmed;
                }

                return strlen($line);
            };

        $handle = curl_init();
        curl_setopt_array($handle, $curlOptions);
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
