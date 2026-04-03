<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\Web;

use InvalidArgumentException;
use Override;
use Psr\Http\Message\StreamInterface as IStream;
use Psr\Http\Message\UploadedFileInterface as IUploadedFile;
use RuntimeException;

/**
 * PSR-7 representation of an uploaded file.
 */
final class UploadedFile implements IUploadedFile
{
    private bool $moved = false;

    private ?IStream $stream;

    /**
     * @param ?int $size Size from the upload metadata when known; otherwise taken from the stream.
     */
    public function __construct(
        IStream $stream,
        private ?int $size,
        private int $error,
        private ?string $clientFilename,
        private ?string $clientMediaType,
    ) {
        $this->stream = $stream;
        $this->size ??= $stream->getSize();
    }

    #region implements IUploadedFile

    /**
     * @inheritDoc
     */
    #[Override]
    public function getClientFilename(): ?string
    {
        return $this->clientFilename;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function getClientMediaType(): ?string
    {
        return $this->clientMediaType;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function getError(): int
    {
        return $this->error;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function getSize(): ?int
    {
        return $this->size ?? $this->stream?->getSize();
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function getStream(): IStream
    {
        $this->assertNotMoved();
        if ($this->stream === null) {
            throw new RuntimeException('No stream is available for the uploaded file.');
        }
        return $this->stream;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function moveTo(string $targetPath): void
    {
        $this->assertNotMoved();

        if ($targetPath === '') {
            throw new InvalidArgumentException('Target path for moving an uploaded file must not be empty.');
        }

        $stream = $this->stream;
        if ($stream === null) {
            throw new RuntimeException('No stream is available for the uploaded file.');
        }

        $this->copyStreamToFile($stream, $targetPath);
        $stream->close();
        $this->stream = null;
        $this->moved = true;
    }

    #endregion implements IUploadedFile

    private function assertNotMoved(): void
    {
        if ($this->moved) {
            throw new RuntimeException('Cannot operate on an uploaded file after it has been moved.');
        }
    }

    private function copyStreamToFile(IStream $stream, string $targetPath): void
    {
        $destination = fopen($targetPath, 'wb');
        if ($destination === false) {
            throw new RuntimeException(sprintf('Could not open target path for writing: %s.', $targetPath));
        }
        try {
            if ($stream->isSeekable()) {
                $stream->rewind();
            }
            while (!$stream->eof()) {
                $chunk = $stream->read(8192);
                if ($chunk === '') {
                    break;
                }
                if (fwrite($destination, $chunk) === false) {
                    throw new RuntimeException(sprintf('Failed writing to %s.', $targetPath));
                }
            }
        } finally {
            fclose($destination);
        }
    }
}
