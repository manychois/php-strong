<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\Http;

use InvalidArgumentException;

/**
 * Aggregates the transport-level options a {@see Client} applies to every request it sends.
 */
final class RequestOptions
{
    /**
     * @param float $timeout The total request timeout, in seconds.
     * @param float $connectTimeout The connection timeout, in seconds.
     * @param bool $followRedirects Whether redirect responses are followed automatically.
     * @param int $maxRedirects The maximum number of redirects to follow; effective only
     * when `$followRedirects` is true.
     * @param bool $verifyTls Whether the peer TLS certificate and host name are verified.
     * @param ?string $proxy The proxy URL to send requests through, e.g. `http://proxy.local:8080`.
     * @param ?string $userAgent The `User-Agent` to send when the request has no such header.
     * @param ?string $caFile The path to a certificate authority bundle file.
     * @param ?string $caPath The path to a directory of certificate authority files.
     *
     * @phpstan-param non-negative-int $maxRedirects
     */
    public function __construct(
        public readonly float $timeout = 30.0,
        public readonly float $connectTimeout = 10.0,
        public readonly bool $followRedirects = false,
        public readonly int $maxRedirects = 10,
        public readonly bool $verifyTls = true,
        public readonly ?string $proxy = null,
        public readonly ?string $userAgent = null,
        public readonly ?string $caFile = null,
        public readonly ?string $caPath = null,
    ) {
        if ($timeout <= 0) {
            throw new InvalidArgumentException(sprintf('Timeout must be greater than 0, got %f.', $timeout));
        }
        if ($connectTimeout <= 0) {
            throw new InvalidArgumentException(
                sprintf('Connect timeout must be greater than 0, got %f.', $connectTimeout),
            );
        }
        if ($maxRedirects < 0) {
            throw new InvalidArgumentException(
                sprintf('Max redirects must not be negative, got %d.', $maxRedirects),
            );
        }
        if ($proxy === '') {
            throw new InvalidArgumentException('Proxy must not be an empty string.');
        }
        if ($userAgent === '') {
            throw new InvalidArgumentException('User agent must not be an empty string.');
        }
        if ($caFile === '') {
            throw new InvalidArgumentException('CA file must not be an empty string.');
        }
        if ($caPath === '') {
            throw new InvalidArgumentException('CA path must not be an empty string.');
        }
    }
}
