<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\Http\Internal;

use InvalidArgumentException;
use Override;
use Psr\Http\Message\MessageInterface as IMessage;
use Psr\Http\Message\StreamInterface as IStream;

/**
 * Base immutable PSR-7 message implementation.
 */
abstract class AbstractMessage implements IMessage
{
    /**
     * A numeric header name is stored under an `int` key: PHP coerces numeric string array keys to integers, and
     * a lookup coerces identically, so the name still round-trips through {@see getHeader()}.
     *
     * @var array<array-key,list<string>>
     */
    private array $headers;

    /**
     * @var array<array-key,string>
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
    /**
     * Returns every header, keyed by the name as it was supplied.
     *
     * A header whose name is all digits, e.g. `123`, comes back under an `int` key. PHP coerces a numeric string
     * array key to an integer on write, so no implementation can return it as a string; cast the key before using
     * it as one.
     *
     * @return array<array-key,list<string>> The headers.
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
    public function withAddedHeader(string $name, $value): static
    {
        $clone = clone $this;
        $existing = $clone->getHeader($name);
        $clone->setHeader($name, array_values([...$existing, ...self::normalizeHeaderValues($name, $value)]));
        return $clone;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function withBody(IStream $body): static
    {
        $clone = clone $this;
        $clone->body = $body;
        return $clone;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function withHeader(string $name, $value): static
    {
        $clone = clone $this;
        $clone->setHeader($name, self::normalizeHeaderValues($name, $value));
        return $clone;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function withProtocolVersion(string $version): static
    {
        $clone = clone $this;
        $clone->protocolVersion = $version;
        return $clone;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function withoutHeader(string $name): static
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
        self::assertHeaderName($name);
        $normalized = self::normalizeHeaderName($name);
        if (array_key_exists($normalized, $this->normalizedHeaderNames)) {
            $old = $this->normalizedHeaderNames[$normalized];
            unset($this->headers[$old]);
        }

        $this->headers[$name] = $values;
        $this->normalizedHeaderNames[$normalized] = $name;
    }

    /**
     * Throws unless the name is a valid RFC 9110 field name, i.e. a non-empty token.
     *
     * @param string $name The header name to check.
     *
     * @throws InvalidArgumentException if the name is empty or carries a character a token may not contain.
     */
    private static function assertHeaderName(string $name): void
    {
        if (preg_match('/^[!#$%&\'*+\-.^_`|~0-9A-Za-z]+$/', $name) === 1) {
            return;
        }

        throw new InvalidArgumentException(sprintf('"%s" is not a valid header name.', $name));
    }

    /**
     * @param array<string,string|string[]> $headers
     *
     * @return array{
     *     0: array<array-key,list<string>>,
     *     1: array<array-key,string>,
     * }
     */
    private static function normalizeHeaders(array $headers): array
    {
        $normalizedHeaders = [];
        $normalizedHeaderNames = [];
        foreach ($headers as $name => $value) {
            $name = (string) $name;
            self::assertHeaderName($name);
            $values = self::normalizeHeaderValues($name, $value);
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
    private static function normalizeHeaderValues(string $name, string|array $value): array
    {
        $values = is_array($value) ? $value : [$value];
        if ($values === []) {
            throw new InvalidArgumentException(sprintf(
                'Header "%s" needs at least one value; an empty array was given.',
                $name,
            ));
        }

        $result = [];
        foreach ($values as $part) {
            $part = (string) $part;
            if (strpbrk($part, "\r\n\0") !== false) {
                throw new InvalidArgumentException(sprintf(
                    'Header "%s" has a value containing a carriage return, line feed or NUL.',
                    $name,
                ));
            }

            $result[] = $part;
        }
        return $result;
    }

    private static function normalizeHeaderName(string $name): string
    {
        return strtolower($name);
    }
}
