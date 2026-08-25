<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\Http;

use Manychois\PhpStrong\Http\Internal\AbstractMessage;
use Override;
use Psr\Http\Message\RequestInterface as IRequest;
use Psr\Http\Message\StreamInterface as IStream;
use Psr\Http\Message\UriInterface as IUri;

/**
 * A lightweight immutable PSR-7 outbound request implementation.
 */
class Request extends AbstractMessage implements IRequest
{
    private IUri $uri;

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
    public function withMethod(string $method): static
    {
        $clone = clone $this;
        $clone->method = $method;
        return $clone;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function withRequestTarget(string $requestTarget): static
    {
        $clone = clone $this;
        $clone->requestTarget = $requestTarget;
        return $clone;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function withUri(IUri $uri, bool $preserveHost = false): static
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
}
