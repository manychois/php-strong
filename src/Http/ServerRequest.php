<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\Http;

use InvalidArgumentException;
use Override;
use Psr\Http\Message\ServerRequestInterface as IServerRequest;
use Psr\Http\Message\StreamInterface as IStream;
use Psr\Http\Message\UploadedFileInterface as IUploadedFile;
use Psr\Http\Message\UriInterface as IUri;

/**
 * A lightweight immutable PSR-7 incoming request implementation.
 */
class ServerRequest extends Request implements IServerRequest
{
    /**
     * @var array<string,mixed>
     */
    private readonly array $serverParams;

    /**
     * Typically mirrors PHP's `$_COOKIE`. Keys are usually strings.
     * `withCookieParams()` is untyped in PSR-7, so this property uses `array-key`.
     *
     * @var array<array-key,mixed>
     */
    private array $cookieParams;

    /**
     * @var array<array-key,mixed>
     */
    private array $queryParams;

    /**
     * @var array<array-key,mixed>
     */
    private array $uploadedFiles;

    /**
     * @var array<string,mixed>
     */
    private array $attributes;

    /**
     * @var null|array<array-key,mixed>|object
     */
    private null|array|object $parsedBody;

    /**
     * @param array<string,string|string[]> $headers
     * @param array<string,mixed> $serverParams
     * @param array<array-key,mixed> $cookieParams
     * @param array<array-key,mixed> $queryParams
     * @param array<array-key,mixed> $uploadedFiles
     * @param null|array<array-key,mixed>|object $parsedBody
     * @param array<string,mixed> $attributes
     */
    public function __construct(
        string $method = 'GET',
        IUri|string|null $uri = null,
        array $headers = [],
        IStream|string|null $body = null,
        string $protocolVersion = '1.1',
        ?string $requestTarget = null,
        array $serverParams = [],
        array $cookieParams = [],
        array $queryParams = [],
        array $uploadedFiles = [],
        null|array|object $parsedBody = null,
        array $attributes = [],
    ) {
        parent::__construct(
            method: $method,
            uri: $uri,
            headers: $headers,
            body: $body,
            protocolVersion: $protocolVersion,
            requestTarget: $requestTarget,
        );

        $this->serverParams = $serverParams;
        $this->cookieParams = $cookieParams;
        $this->queryParams = $queryParams;
        $this->uploadedFiles = self::normalizeUploadedFiles($uploadedFiles);
        $this->parsedBody = $parsedBody;
        $this->attributes = $attributes;
    }

    /**
     * Creates an immutable incoming request from PHP superglobals.
     */
    public static function fromGlobals(): self
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

        return new self(
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

    #region implements IServerRequest

    /**
     * @inheritDoc
     */
    #[Override]
    public function getAttribute(string $name, $default = null): mixed
    {
        return $this->attributes[$name] ?? $default;
    }

    /**
     * @inheritDoc
     *
     * @return array<string,mixed>
     */
    #[Override]
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    /**
     * @inheritDoc
     *
     * @return array<array-key,mixed>
     */
    #[Override]
    public function getCookieParams(): array
    {
        return $this->cookieParams;
    }

    /**
     * @inheritDoc
     *
     * @return null|array<array-key,mixed>|object
     */
    #[Override]
    public function getParsedBody(): null|array|object
    {
        return $this->parsedBody;
    }

    /**
     * @inheritDoc
     *
     * @return array<array-key,mixed>
     */
    #[Override]
    public function getQueryParams(): array
    {
        return $this->queryParams;
    }

    /**
     * @inheritDoc
     *
     * @return array<string,mixed>
     */
    #[Override]
    public function getServerParams(): array
    {
        return $this->serverParams;
    }

    /**
     * @inheritDoc
     *
     * @return array<array-key,mixed>
     */
    #[Override]
    public function getUploadedFiles(): array
    {
        return $this->uploadedFiles;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function withAttribute(string $name, $value): static
    {
        $clone = clone $this;
        $clone->attributes[$name] = $value;
        return $clone;
    }

    /**
     * @inheritDoc
     *
     * @param array<mixed> $cookies
     */
    #[Override]
    public function withCookieParams(array $cookies): static
    {
        $clone = clone $this;
        $clone->cookieParams = $cookies;
        return $clone;
    }

    /**
     * @inheritDoc
     *
     * @param null|array<array-key,mixed>|object $data
     */
    #[Override]
    public function withParsedBody($data): static
    {
        if ($data !== null && !is_array($data) && !is_object($data)) {
            throw new InvalidArgumentException(sprintf(
                'Parsed body must be null, array, or object; got %s',
                get_debug_type($data),
            ));
        }

        $clone = clone $this;
        $clone->parsedBody = $data;
        return $clone;
    }

    /**
     * @inheritDoc
     *
     * @param array<array-key,mixed> $query
     */
    #[Override]
    public function withQueryParams(array $query): static
    {
        $clone = clone $this;
        $clone->queryParams = $query;
        return $clone;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function withoutAttribute(string $name): static
    {
        if (!array_key_exists($name, $this->attributes)) {
            return $this;
        }

        $clone = clone $this;
        unset($clone->attributes[$name]);
        return $clone;
    }

    /**
     * @inheritDoc
     *
     * @param array<array-key,mixed> $uploadedFiles
     */
    #[Override]
    public function withUploadedFiles(array $uploadedFiles): static
    {
        $clone = clone $this;
        $clone->uploadedFiles = self::normalizeUploadedFiles($uploadedFiles);
        return $clone;
    }

    #endregion implements IServerRequest

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
     *
     * @return array<string,string>
     */
    private static function extractHeadersFromServerParams(array $serverParams): array
    {
        $headers = [];
        foreach ($serverParams as $name => $value) {
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

    /**
     * @param array<array-key,mixed> $uploadedFiles
     *
     * @return array<array-key,mixed>
     */
    private static function normalizeUploadedFiles(array $uploadedFiles): array
    {
        foreach ($uploadedFiles as $key => $value) {
            if ($value instanceof IUploadedFile) {
                continue;
            }
            if (is_array($value)) {
                $uploadedFiles[$key] = self::normalizeUploadedFiles($value);
                continue;
            }
            throw new InvalidArgumentException(sprintf(
                'Uploaded files must contain only %s instances or nested arrays; got %s',
                IUploadedFile::class,
                get_debug_type($value),
            ));
        }
        return $uploadedFiles;
    }
}
