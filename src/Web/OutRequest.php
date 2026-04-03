<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\Web;

use Manychois\PhpStrong\Web\Internal\AbstractMessage;
use Override;
use Psr\Http\Message\RequestInterface as IRequest;
use Psr\Http\Message\StreamInterface as IStream;
use Psr\Http\Message\UriInterface as IUri;

/**
 * A lightweight immutable PSR-7 outbound request implementation.
 */
class OutRequest extends AbstractMessage implements IRequest
{
    /**
     * @param array<string,string|string[]> $headers
     */
    public function __construct(
        private string $method = 'GET',
        IUri|string|null $uri = null,
        array $headers = [],
        IStream|string|null $body = null,
        string $protocolVersion = '1.1',
        private ?string $requestTarget = null,
    ) {
        $this->uri = $uri instanceof IUri ? $uri : Uri::fromString($uri ?? '/');
        parent::__construct(
            headers: $headers,
            body: $body instanceof IStream ? $body : (new StreamFactory())->createStream($body ?? ''),
            protocolVersion: $protocolVersion,
        );

        if (!$this->hasHeader('Host')) {
            $host = self::hostHeaderFromUri($this->uri);
            if ($host !== null) {
                $this->setHeader('Host', [$host]);
            }
        }
    }

    /**
     * Creates an immutable incoming request from PHP superglobals.
     */
    public static function fromGlobals(): InRequest
    {
        /** @var array<string,mixed> $serverParams */
        $serverParams = [];
        foreach ($_SERVER as $name => $value) {
            if (is_string($name)) {
                $serverParams[$name] = $value;
            }
        }
        $requestUri = is_string($serverParams['REQUEST_URI'] ?? null)
            ? $serverParams['REQUEST_URI']
            : null;
        $rawBody = file_get_contents('php://input');

        return new InRequest(
            method: is_string($serverParams['REQUEST_METHOD'] ?? null)
                ? $serverParams['REQUEST_METHOD']
                : 'GET',
            uri: self::createUriFromServerParams($serverParams, $requestUri),
            headers: self::extractHeadersFromServerParams($serverParams),
            body: (new StreamFactory())->createStream(is_string($rawBody) ? $rawBody : ''),
            protocolVersion: self::extractProtocolVersion($serverParams),
            requestTarget: $requestUri,
            serverParams: $serverParams,
            cookieParams: $_COOKIE,
            queryParams: $_GET,
            uploadedFiles: [],
            parsedBody: $_POST !== [] ? $_POST : null,
            attributes: [],
        );
    }

    private IUri $uri;

    #region implements IRequest

    /**
     * @inheritDoc
     */
    #[Override]
    public function getMethod(): string
    {
        return $this->method;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function getRequestTarget(): string
    {
        if ($this->requestTarget !== null) {
            return $this->requestTarget;
        }

        $path = $this->uri->getPath();
        $query = $this->uri->getQuery();
        if ($path === '') {
            $path = '/';
        }
        if ($query !== '') {
            return sprintf('%s?%s', $path, $query);
        }
        return $path;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function getUri(): IUri
    {
        return $this->uri;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function withMethod(string $method): IRequest
    {
        $clone = clone $this;
        $clone->method = $method;
        return $clone;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function withRequestTarget(string $requestTarget): IRequest
    {
        $clone = clone $this;
        $clone->requestTarget = $requestTarget;
        return $clone;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function withUri(IUri $uri, bool $preserveHost = false): IRequest
    {
        $clone = clone $this;
        $clone->uri = $uri;

        $uriHost = self::hostHeaderFromUri($uri);
        $hasHost = $clone->hasHeader('Host');
        $hostLine = $clone->getHeaderLine('Host');

        if ($preserveHost && $hasHost && $hostLine !== '') {
            return $clone;
        }
        if ($uriHost === null) {
            return $clone;
        }

        $clone->setHeader('Host', [$uriHost]);
        return $clone;
    }

    #endregion implements IRequest

    private static function hostHeaderFromUri(IUri $uri): ?string
    {
        $host = $uri->getHost();
        if ($host === '') {
            return null;
        }

        $port = $uri->getPort();
        if ($port !== null) {
            return sprintf('%s:%d', $host, $port);
        }
        return $host;
    }

    /**
     * @param array<array-key,mixed> $serverParams Typically {@see $_SERVER}; may contain non-string keys,
     *                                            which are ignored when deriving headers.
     *
     * @return array<string,string>
     */
    private static function extractHeadersFromServerParams(array $serverParams): array
    {
        $headers = [];
        foreach ($serverParams as $name => $value) {
            if (!is_string($name)) {
                continue;
            }
            if (!is_scalar($value) && $value !== null) {
                continue;
            }

            $mappedHeaderName = null;
            if (str_starts_with($name, 'HTTP_')) {
                $mappedHeaderName = self::normalizeServerHeaderName(substr($name, 5));
            } elseif (in_array($name, ['CONTENT_LENGTH', 'CONTENT_MD5', 'CONTENT_TYPE'], true)) {
                $mappedHeaderName = self::normalizeServerHeaderName($name);
            }

            if ($mappedHeaderName !== null) {
                $headers[$mappedHeaderName] = (string) $value;
            }
        }

        if (
            !array_key_exists('Authorization', $headers)
            && is_scalar($serverParams['REDIRECT_HTTP_AUTHORIZATION'] ?? null)
        ) {
            $headers['Authorization'] = (string) $serverParams['REDIRECT_HTTP_AUTHORIZATION'];
        }

        return $headers;
    }

    /**
     * @param array<string,mixed> $serverParams
     */
    private static function createUriFromServerParams(array $serverParams, ?string $requestUri): IUri
    {
        if ($requestUri === null || $requestUri === '' || $requestUri === '*') {
            $requestUri = '/';
        }
        if (str_starts_with($requestUri, 'http://') || str_starts_with($requestUri, 'https://')) {
            return Uri::fromString($requestUri);
        }
        if (!str_starts_with($requestUri, '/')) {
            $requestUri = sprintf('/%s', $requestUri);
        }

        $scheme = 'http';
        if (($serverParams['HTTPS'] ?? null) === 'on' || ($serverParams['HTTPS'] ?? null) === '1') {
            $scheme = 'https';
        } elseif (is_string($serverParams['REQUEST_SCHEME'] ?? null) && $serverParams['REQUEST_SCHEME'] !== '') {
            $scheme = strtolower($serverParams['REQUEST_SCHEME']);
        }

        $host = null;
        if (is_string($serverParams['HTTP_HOST'] ?? null) && $serverParams['HTTP_HOST'] !== '') {
            $host = $serverParams['HTTP_HOST'];
        } elseif (is_string($serverParams['SERVER_NAME'] ?? null) && $serverParams['SERVER_NAME'] !== '') {
            $host = $serverParams['SERVER_NAME'];
            if (is_scalar($serverParams['SERVER_PORT'] ?? null)) {
                $port = (int) $serverParams['SERVER_PORT'];
                if (($scheme === 'http' && $port !== 80) || ($scheme === 'https' && $port !== 443)) {
                    $host = sprintf('%s:%d', $host, $port);
                }
            }
        } elseif (is_string($serverParams['SERVER_ADDR'] ?? null) && $serverParams['SERVER_ADDR'] !== '') {
            $host = $serverParams['SERVER_ADDR'];
        } else {
            $host = 'localhost';
        }

        return Uri::fromString(sprintf('%s://%s%s', $scheme, $host, $requestUri));
    }

    /**
     * @param array<string,mixed> $serverParams
     */
    private static function extractProtocolVersion(array $serverParams): string
    {
        if (is_string($serverParams['SERVER_PROTOCOL'] ?? null)) {
            $serverProtocol = $serverParams['SERVER_PROTOCOL'];
            if (preg_match('/^HTTP\/(?<version>[0-9]+(?:\.[0-9]+)?)$/', $serverProtocol, $matches) === 1) {
                return $matches['version'];
            }
        }
        return '1.1';
    }

    private static function normalizeServerHeaderName(string $name): string
    {
        return str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', $name))));
    }
}
