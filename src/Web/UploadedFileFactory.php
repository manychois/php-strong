<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\Web;

use InvalidArgumentException;
use Override;
use Psr\Http\Message\StreamInterface as IStream;
use Psr\Http\Message\UploadedFileFactoryInterface as IUploadedFileFactory;
use Psr\Http\Message\UploadedFileInterface as IUploadedFile;

/**
 * PSR-17 factory that builds {@see UploadedFile} instances.
 */
class UploadedFileFactory implements IUploadedFileFactory
{
    #region implements IUploadedFileFactory

    /**
     * @inheritDoc
     */
    #[Override]
    public function createUploadedFile(
        IStream $stream,
        ?int $size = null,
        int $error = \UPLOAD_ERR_OK,
        ?string $clientFilename = null,
        ?string $clientMediaType = null,
    ): IUploadedFile {
        if (!$stream->isReadable()) {
            throw new InvalidArgumentException('The stream for an uploaded file must be readable.');
        }

        return new UploadedFile(
            stream: $stream,
            size: $size,
            error: $error,
            clientFilename: $clientFilename,
            clientMediaType: $clientMediaType,
        );
    }

    #endregion implements IUploadedFileFactory
}
