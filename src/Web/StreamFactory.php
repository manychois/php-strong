<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\Web;

use InvalidArgumentException;
use Override;
use Psr\Http\Message\StreamFactoryInterface as IStreamFactory;
use Psr\Http\Message\StreamInterface as IStream;
use RuntimeException;

/**
 * PSR-17 factory that builds {@see Stream} instances.
 */
class StreamFactory implements IStreamFactory
{
    #region implements IStreamFactory

    /**
     * @inheritDoc
     */
    #[Override]
    public function createStream(string $content = ''): IStream
    {
        $resource = fopen('php://temp', 'r+b');
        if ($resource === false) {
            throw new RuntimeException('Failed to create temporary stream.');
        }
        if ($content !== '') {
            fwrite($resource, $content);
            rewind($resource);
        }
        return new Stream($resource);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function createStreamFromFile(string $filename, string $mode = 'r'): IStream
    {
        if ($mode === '') {
            throw new InvalidArgumentException('File open mode must not be empty.');
        }
        $resource = fopen($filename, $mode);
        if ($resource === false) {
            throw new RuntimeException(sprintf(
                'Could not open stream for %s with mode %s.',
                $filename,
                $mode,
            ));
        }
        return new Stream($resource);
    }

    /**
     * @inheritDoc
     *
     * @param resource $resource
     */
    #[Override]
    public function createStreamFromResource($resource): IStream
    {
        return new Stream($resource);
    }

    #endregion implements IStreamFactory
}
