<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\Web\Internal;

use Override;
use Psr\Http\Message\MessageInterface as IMessage;
use Psr\Http\Message\StreamInterface as IStream;

/**
 * Base immutable PSR-7 message implementation.
 */
abstract class AbstractMessage implements IMessage
{
    /**
     * @var array<string,list<string>>
     */
    private array $headers;

    /**
     * @var array<string,string>
     */
    private array $normalizedHeaderNames;

    /**
     * @param array<string,string|string[]> $headers
     */
    protected function __construct(
        array $headers,
        private IStream $body,
        private string $protocolVersion = '1.1',
    ) {
        [$this->headers, $this->normalizedHeaderNames] = self::normalizeHeaders($headers);
    }

    #region implements IMessage

    /**
     * @inheritDoc
     */
    #[Override]
    public function getBody(): IStream
    {
        return $this->body;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function getHeader(string $name): array
    {
        $normalized = self::normalizeHeaderName($name);
        if (!array_key_exists($normalized, $this->normalizedHeaderNames)) {
            return [];
        }

        $actual = $this->normalizedHeaderNames[$normalized];
        return $this->headers[$actual];
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function getHeaderLine(string $name): string
    {
        return implode(', ', $this->getHeader($name));
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function getProtocolVersion(): string
    {
        return $this->protocolVersion;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function hasHeader(string $name): bool
    {
        return array_key_exists(self::normalizeHeaderName($name), $this->normalizedHeaderNames);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function withAddedHeader(string $name, $value): IMessage
    {
        $clone = clone $this;
        $existing = $clone->getHeader($name);
        $clone->setHeader($name, array_values([...$existing, ...self::normalizeHeaderValues($value)]));
        return $clone;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function withBody(IStream $body): IMessage
    {
        $clone = clone $this;
        $clone->body = $body;
        return $clone;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function withHeader(string $name, $value): IMessage
    {
        $clone = clone $this;
        $clone->setHeader($name, self::normalizeHeaderValues($value));
        return $clone;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function withProtocolVersion(string $version): IMessage
    {
        $clone = clone $this;
        $clone->protocolVersion = $version;
        return $clone;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function withoutHeader(string $name): IMessage
    {
        $normalized = self::normalizeHeaderName($name);
        if (!array_key_exists($normalized, $this->normalizedHeaderNames)) {
            return $this;
        }

        $clone = clone $this;
        $actual = $clone->normalizedHeaderNames[$normalized];
        unset($clone->headers[$actual], $clone->normalizedHeaderNames[$normalized]);
        return $clone;
    }

    #endregion implements IMessage

    /**
     * @param list<string> $values
     */
    final protected function setHeader(string $name, array $values): void
    {
        $normalized = self::normalizeHeaderName($name);
        if (array_key_exists($normalized, $this->normalizedHeaderNames)) {
            $old = $this->normalizedHeaderNames[$normalized];
            unset($this->headers[$old]);
        }

        $this->headers[$name] = $values;
        $this->normalizedHeaderNames[$normalized] = $name;
    }

    /**
     * @param array<string,string|string[]> $headers
     *
     * @return array{
     *     0: array<string,list<string>>,
     *     1: array<string,string>,
     * }
     */
    private static function normalizeHeaders(array $headers): array
    {
        $normalizedHeaders = [];
        $normalizedHeaderNames = [];
        foreach ($headers as $name => $value) {
            $values = self::normalizeHeaderValues($value);
            $normalized = self::normalizeHeaderName($name);
            $normalizedHeaders[$name] = $values;
            $normalizedHeaderNames[$normalized] = $name;
        }
        return [$normalizedHeaders, $normalizedHeaderNames];
    }

    /**
     * @param string|string[] $value
     *
     * @return list<string>
     */
    private static function normalizeHeaderValues(string|array $value): array
    {
        $values = is_array($value) ? $value : [$value];
        $result = [];
        foreach ($values as $part) {
            $result[] = (string) $part;
        }
        return $result;
    }

    private static function normalizeHeaderName(string $name): string
    {
        return strtolower($name);
    }
}
