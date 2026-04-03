<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\Web;

use Override;
use Psr\Http\Message\StreamInterface as IStream;
use RuntimeException;

/**
 * PSR-7 stream backed by a PHP resource. Create instances with {@see StreamFactory} or `new Stream($resource)`.
 */
final class Stream implements IStream
{
    /**
     * @var resource|null
     */
    private $resource;

    /**
     * @param resource $resource
     */
    public function __construct(mixed $resource)
    {
        if (!is_resource($resource)) {
            throw new RuntimeException(sprintf(
                'Expected a PHP stream resource; got %s.',
                get_debug_type($resource),
            ));
        }
        $this->resource = $resource;
    }

    #region implements IStream

    /**
     * @inheritDoc
     */
    #[Override]
    public function __toString(): string
    {
        try {
            if (!$this->isAttached()) {
                return '';
            }
            $this->rewind();
            $contents = stream_get_contents($this->getResource());
            // `stream_get_contents` is documented to return false on failure; in practice user-space wrappers
            // often yield an empty string instead, so this branch is rarely exercised.
            // @codeCoverageIgnoreStart
            if ($contents === false) {
                return '';
            }
            // @codeCoverageIgnoreEnd
            return $contents;
        } catch (RuntimeException) {
            return '';
        }
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function close(): void
    {
        if ($this->resource !== null) {
            fclose($this->resource);
        }
        $this->resource = null;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function detach()
    {
        $resource = $this->resource;
        $this->resource = null;
        return $resource;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function eof(): bool
    {
        if (!$this->isAttached()) {
            return true;
        }
        return feof($this->getResource());
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function getContents(): string
    {
        $contents = stream_get_contents($this->getResource());
        // @codeCoverageIgnoreStart
        if ($contents === false) {
            throw new RuntimeException('Failed to read stream contents.');
        }
        // @codeCoverageIgnoreEnd
        return $contents;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function getMetadata(?string $key = null): mixed
    {
        if (!$this->isAttached()) {
            return $key === null ? [] : null;
        }

        $metadata = stream_get_meta_data($this->getResource());
        if ($key === null) {
            return $metadata;
        }
        return $metadata[$key] ?? null;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function getSize(): ?int
    {
        if (!$this->isAttached()) {
            return null;
        }
        $stats = fstat($this->getResource());
        if ($stats === false) {
            return null;
        }
        return isset($stats['size']) ? (int) $stats['size'] : null;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function isReadable(): bool
    {
        if (!$this->isAttached()) {
            return false;
        }
        $modeMetadata = $this->getMetadata('mode');
        // PHP stream metadata always exposes `mode` as a non-empty string; guard retained for static analysis.
        // @codeCoverageIgnoreStart
        if (!is_string($modeMetadata)) {
            return false;
        }
        // @codeCoverageIgnoreEnd
        $mode = $modeMetadata;
        return str_contains($mode, 'r') || str_contains($mode, '+');
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function isSeekable(): bool
    {
        return (bool) ($this->getMetadata('seekable') ?? false);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function isWritable(): bool
    {
        if (!$this->isAttached()) {
            return false;
        }
        $modeMetadata = $this->getMetadata('mode');
        // @codeCoverageIgnoreStart
        if (!is_string($modeMetadata)) {
            return false;
        }
        // @codeCoverageIgnoreEnd
        $mode = $modeMetadata;
        return strpbrk($mode, 'waxc+') !== false;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function read(int $length): string
    {
        if (!$this->isReadable()) {
            throw new RuntimeException('Stream is not readable.');
        }
        if ($length < 0) {
            throw new RuntimeException('Length must be non-negative.');
        }
        if ($length === 0) {
            return '';
        }
        $result = fread($this->getResource(), $length);
        if ($result === false) {
            throw new RuntimeException('Failed to read from stream.');
        }
        return $result;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function rewind(): void
    {
        $this->seek(0);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function seek(int $offset, int $whence = SEEK_SET): void
    {
        if (!$this->isSeekable()) {
            throw new RuntimeException('Stream is not seekable.');
        }
        $result = fseek($this->getResource(), $offset, $whence);
        if ($result !== 0) {
            throw new RuntimeException('Failed to seek in stream.');
        }
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function tell(): int
    {
        $position = ftell($this->getResource());
        // @codeCoverageIgnoreStart
        if ($position === false) {
            throw new RuntimeException('Failed to get stream position.');
        }
        // @codeCoverageIgnoreEnd
        return $position;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function write(string $string): int
    {
        if (!$this->isWritable()) {
            throw new RuntimeException('Stream is not writable.');
        }
        $written = fwrite($this->getResource(), $string);
        if ($written === false) {
            throw new RuntimeException('Failed to write to stream.');
        }
        return $written;
    }

    #endregion implements IStream

    private function isAttached(): bool
    {
        return $this->resource !== null && is_resource($this->resource);
    }

    /**
     * @return resource
     */
    private function getResource()
    {
        $resource = $this->resource;
        if (!is_resource($resource)) {
            throw new RuntimeException('Stream is detached.');
        }
        return $resource;
    }
}
