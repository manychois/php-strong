<?php

declare(strict_types=1);

namespace Manychois\PhpStrongTests\Web;

use InvalidArgumentException;
use Manychois\PhpStrong\Web\StreamFactory;
use Manychois\PhpStrong\Web\UploadedFile;
use Manychois\PhpStrong\Web\UploadedFileFactory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for {@see UploadedFileFactory}.
 */
final class UploadedFileFactoryTest extends TestCase
{
    #[Test]
    public function createUploadedFile_builds_instance_from_readable_stream(): void
    {
        $streams = new StreamFactory();
        $stream = $streams->createStream('payload');

        $factory = new UploadedFileFactory();
        $file = $factory->createUploadedFile(
            $stream,
            size: 7,
            error: \UPLOAD_ERR_OK,
            clientFilename: 'a.txt',
            clientMediaType: 'text/plain',
        );

        self::assertInstanceOf(UploadedFile::class, $file);
        self::assertSame(7, $file->getSize());
        self::assertSame(\UPLOAD_ERR_OK, $file->getError());
        self::assertSame('a.txt', $file->getClientFilename());
        self::assertSame('text/plain', $file->getClientMediaType());
    }

    #[Test]
    public function createUploadedFile_throws_when_stream_not_readable(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'php-strong-upload');
        self::assertNotFalse($path);
        $handle = fopen($path, 'wb');
        self::assertNotFalse($handle);
        $stream = null;
        try {
            $streams = new StreamFactory();
            $stream = $streams->createStreamFromResource($handle);

            self::assertFalse($stream->isReadable());

            $factory = new UploadedFileFactory();
            $this->expectException(InvalidArgumentException::class);
            $this->expectExceptionMessage('The stream for an uploaded file must be readable.');
            $factory->createUploadedFile($stream);
        } finally {
            $stream?->close();
            unlink($path);
        }
    }
}
