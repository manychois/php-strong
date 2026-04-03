<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\Web;

use InvalidArgumentException;
use Override;
use Psr\Http\Message\ServerRequestInterface as IServerRequest;
use Psr\Http\Message\StreamInterface as IStream;
use Psr\Http\Message\UploadedFileInterface as IUploadedFile;
use Psr\Http\Message\UriInterface as IUri;

/**
 * A lightweight immutable PSR-7 incoming request implementation.
 */
class InRequest extends OutRequest implements IServerRequest
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
    public function withAttribute(string $name, $value): IServerRequest
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
    public function withCookieParams(array $cookies): IServerRequest
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
    public function withParsedBody($data): IServerRequest
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
    public function withQueryParams(array $query): IServerRequest
    {
        $clone = clone $this;
        $clone->queryParams = $query;
        return $clone;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function withoutAttribute(string $name): IServerRequest
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
    public function withUploadedFiles(array $uploadedFiles): IServerRequest
    {
        $clone = clone $this;
        $clone->uploadedFiles = self::normalizeUploadedFiles($uploadedFiles);
        return $clone;
    }

    #endregion implements IServerRequest

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
