<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\Web;

use InvalidArgumentException;
use Override;
use Psr\Http\Message\UriInterface as IUri;
use RuntimeException;

/**
 * PSR-7 URI implementation.
 */
final class Uri implements IUri
{
    /**
     * Initializes an immutable URI value object.
     */
    public function __construct(
        private string $scheme = '',
        private string $userInfo = '',
        private string $host = '',
        private ?int $port = null,
        private string $path = '',
        private string $query = '',
        private string $fragment = '',
    ) {
    }

    /**
     * Parses a URI string into an immutable URI value object.
     *
     * @param string $uri The URI string to parse.
     */
    public static function fromString(string $uri): self
    {
        $parts = parse_url($uri);
        if ($parts === false) {
            throw new RuntimeException(sprintf('Malformed URI: %s', $uri));
        }

        $user = (string) ($parts['user'] ?? '');
        $pass = (string) ($parts['pass'] ?? '');
        $userInfo = $user;
        if ($pass !== '') {
            $userInfo = sprintf('%s:%s', $user, $pass);
        }

        return new self(
            scheme: (string) ($parts['scheme'] ?? ''),
            userInfo: $userInfo,
            host: (string) ($parts['host'] ?? ''),
            port: isset($parts['port']) ? (int) $parts['port'] : null,
            path: (string) ($parts['path'] ?? ''),
            query: (string) ($parts['query'] ?? ''),
            fragment: (string) ($parts['fragment'] ?? ''),
        );
    }

    #region implements IUri

    /**
     * @inheritDoc
     */
    #[Override]
    public function __toString(): string
    {
        $uri = '';
        if ($this->scheme !== '') {
            $uri .= sprintf('%s:', $this->scheme);
        }

        $authority = $this->getAuthority();
        if ($authority !== '') {
            $uri .= sprintf('//%s', $authority);
        }

        $uri .= $this->path;

        if ($this->query !== '') {
            $uri .= sprintf('?%s', $this->query);
        }
        if ($this->fragment !== '') {
            $uri .= sprintf('#%s', $this->fragment);
        }

        return $uri;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function getAuthority(): string
    {
        if ($this->host === '') {
            return '';
        }

        $authority = '';
        if ($this->userInfo !== '') {
            $authority .= sprintf('%s@', $this->userInfo);
        }
        $authority .= $this->host;

        if ($this->port !== null && self::isNonStandardPort($this->scheme, $this->port)) {
            $authority .= sprintf(':%d', $this->port);
        }
        return $authority;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function getFragment(): string
    {
        return $this->fragment;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function getHost(): string
    {
        return $this->host;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function getPort(): ?int
    {
        if ($this->port !== null && self::isNonStandardPort($this->scheme, $this->port)) {
            return $this->port;
        }
        return null;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function getQuery(): string
    {
        return $this->query;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function getScheme(): string
    {
        return $this->scheme;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function getUserInfo(): string
    {
        return $this->userInfo;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function withFragment(string $fragment): IUri
    {
        $clone = clone $this;
        $clone->fragment = ltrim($fragment, '#');
        return $clone;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function withHost(string $host): IUri
    {
        $clone = clone $this;
        $clone->host = strtolower($host);
        return $clone;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function withPath(string $path): IUri
    {
        $clone = clone $this;
        $clone->path = $path;
        return $clone;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function withPort(?int $port): IUri
    {
        if ($port !== null && ($port < 1 || $port > 65535)) {
            throw new InvalidArgumentException(sprintf('Invalid port number: %d', $port));
        }
        $clone = clone $this;
        $clone->port = $port;
        return $clone;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function withQuery(string $query): IUri
    {
        $clone = clone $this;
        $clone->query = ltrim($query, '?');
        return $clone;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function withScheme(string $scheme): IUri
    {
        $clone = clone $this;
        $clone->scheme = strtolower($scheme);
        return $clone;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function withUserInfo(string $user, ?string $password = null): IUri
    {
        $clone = clone $this;
        $clone->userInfo = $password !== null ? sprintf('%s:%s', $user, $password) : $user;
        return $clone;
    }

    #endregion implements IUri

    private static function isNonStandardPort(string $scheme, int $port): bool
    {
        return match (strtolower($scheme)) {
            'http' => $port !== 80,
            'https' => $port !== 443,
            default => true,
        };
    }
}
