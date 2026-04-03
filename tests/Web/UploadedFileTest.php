<?php

declare(strict_types=1);

namespace Manychois\PhpStrongTests\Web;

use InvalidArgumentException;
use Manychois\PhpStrong\Web\Stream;
use Manychois\PhpStrong\Web\StreamFactory;
use Manychois\PhpStrong\Web\UploadedFile;
use ReflectionClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Unit tests for {@see UploadedFile}.
 */
final class UploadedFileTest extends TestCase
{
    #[Test]
    public function constructor_with_null_size_uses_stream_size(): void
    {
        $streams = new StreamFactory();
        $stream = $streams->createStream('12345');

        $file = new UploadedFile(
            $stream,
            size: null,
            error: \UPLOAD_ERR_OK,
            clientFilename: null,
            clientMediaType: null,
        );

        self::assertSame(5, $file->getSize());
    }

    #[Test]
    public function getters_reflect_constructor_arguments(): void
    {
        $streams = new StreamFactory();
        $stream = $streams->createStream('x');

        $file = new UploadedFile(
            $stream,
            size: 99,
            error: \UPLOAD_ERR_PARTIAL,
            clientFilename: 'doc.pdf',
            clientMediaType: 'application/pdf',
        );

        self::assertSame(99, $file->getSize());
        self::assertSame(\UPLOAD_ERR_PARTIAL, $file->getError());
        self::assertSame('doc.pdf', $file->getClientFilename());
        self::assertSame('application/pdf', $file->getClientMediaType());
    }

    #[Test]
    public function getStream_returns_readable_stream_until_moved(): void
    {
        $streams = new StreamFactory();
        $stream = $streams->createStream('body');
        $file = new UploadedFile(
            $stream,
            size: 4,
            error: \UPLOAD_ERR_OK,
            clientFilename: null,
            clientMediaType: null,
        );

        $out = $file->getStream();
        self::assertSame($stream, $out);
        self::assertSame('body', $out->read(8192));
    }

    #[Test]
    public function moveTo_writes_stream_contents_then_getStream_throws(): void
    {
        $streams = new StreamFactory();
        $stream = $streams->createStream("line1\nline2");
        $file = new UploadedFile(
            $stream,
            size: null,
            error: \UPLOAD_ERR_OK,
            clientFilename: 't.txt',
            clientMediaType: 'text/plain',
        );

        $target = tempnam(sys_get_temp_dir(), 'php-strong-uploaded-');
        self::assertNotFalse($target);
        unlink($target);
        try {
            $file->moveTo($target);
            self::assertFileExists($target);
            self::assertSame("line1\nline2", file_get_contents($target));

            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('Cannot operate on an uploaded file after it has been moved.');
            $file->getStream();
        } finally {
            if (is_file($target)) {
                unlink($target);
            }
        }
    }

    #[Test]
    public function moveTo_closes_underlying_stream(): void
    {
        $streams = new StreamFactory();
        $stream = $streams->createStream('closed-after-move');
        $file = new UploadedFile(
            $stream,
            size: null,
            error: \UPLOAD_ERR_OK,
            clientFilename: null,
            clientMediaType: null,
        );

        $target = tempnam(sys_get_temp_dir(), 'php-strong-uploaded-');
        self::assertNotFalse($target);
        unlink($target);
        try {
            $file->moveTo($target);
            self::assertFalse($stream->isReadable());
        } finally {
            if (is_file($target)) {
                unlink($target);
            }
        }
    }

    #[Test]
    public function moveTo_throws_InvalidArgumentException_when_target_path_is_empty(): void
    {
        $streams = new StreamFactory();
        $stream = $streams->createStream('data');
        $file = new UploadedFile(
            $stream,
            size: null,
            error: \UPLOAD_ERR_OK,
            clientFilename: null,
            clientMediaType: null,
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Target path for moving an uploaded file must not be empty.');
        $file->moveTo('');
    }

    #[Test]
    public function moveTo_twice_throws_after_first_move(): void
    {
        $streams = new StreamFactory();
        $stream = $streams->createStream('once');
        $file = new UploadedFile(
            $stream,
            size: null,
            error: \UPLOAD_ERR_OK,
            clientFilename: null,
            clientMediaType: null,
        );

        $target = tempnam(sys_get_temp_dir(), 'php-strong-uploaded-');
        self::assertNotFalse($target);
        unlink($target);
        $second = $target . '-b';
        try {
            $file->moveTo($target);
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('Cannot operate on an uploaded file after it has been moved.');
            $file->moveTo($second);
        } finally {
            if (is_file($target)) {
                unlink($target);
            }
            if (is_file($second)) {
                unlink($second);
            }
        }
    }

    #[Test]
    public function getStream_throws_when_no_stream_but_not_yet_moved(): void
    {
        $streams = new StreamFactory();
        $stream = $streams->createStream('x');
        $file = new UploadedFile(
            $stream,
            size: null,
            error: \UPLOAD_ERR_OK,
            clientFilename: null,
            clientMediaType: null,
        );

        $prop = (new ReflectionClass(UploadedFile::class))->getProperty('stream');
        $prop->setValue($file, null);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No stream is available for the uploaded file.');
        $file->getStream();
    }

    #[Test]
    public function moveTo_copies_non_seekable_stream_from_current_position(): void
    {
        $php = escapeshellarg(\PHP_BINARY);
        $payload = 'pipe-body';
        $code = 'fwrite(STDOUT, ' . var_export($payload, true) . ');';
        $inner = escapeshellarg($code);
        $resource = popen("{$php} -r {$inner}", 'r');
        self::assertNotFalse($resource);
        $stream = new Stream($resource);
        // Leave pointer mid-stream so non-seekable path does not rewind to start.
        self::assertSame('pipe', $stream->read(4));

        $file = new UploadedFile(
            $stream,
            size: null,
            error: \UPLOAD_ERR_OK,
            clientFilename: null,
            clientMediaType: null,
        );

        $target = tempnam(sys_get_temp_dir(), 'php-strong-uploaded-');
        self::assertNotFalse($target);
        unlink($target);
        try {
            $file->moveTo($target);
            self::assertSame('-body', file_get_contents($target));
        } finally {
            if (is_file($target)) {
                unlink($target);
            }
        }
    }

    #[Test]
    public function moveTo_throws_when_stream_was_cleared_before_move(): void
    {
        $streams = new StreamFactory();
        $stream = $streams->createStream('data');
        $file = new UploadedFile(
            $stream,
            size: null,
            error: \UPLOAD_ERR_OK,
            clientFilename: null,
            clientMediaType: null,
        );

        $prop = (new ReflectionClass(UploadedFile::class))->getProperty('stream');
        $prop->setValue($file, null);

        $target = tempnam(sys_get_temp_dir(), 'php-strong-uploaded-');
        self::assertNotFalse($target);
        unlink($target);
        try {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('No stream is available for the uploaded file.');
            $file->moveTo($target);
        } finally {
            if (is_file($target)) {
                unlink($target);
            }
        }
    }

    #[Test]
    public function moveTo_throws_when_target_cannot_be_opened_for_writing(): void
    {
        if (\DIRECTORY_SEPARATOR !== '/') {
            self::markTestSkipped('Uses POSIX directory-as-path behavior.');
        }

        $streams = new StreamFactory();
        $stream = $streams->createStream('data');
        $file = new UploadedFile(
            $stream,
            size: null,
            error: \UPLOAD_ERR_OK,
            clientFilename: null,
            clientMediaType: null,
        );

        set_error_handler(static fn (int $errno, string $errstr, string $errfile, int $errline): true => true);
        try {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('Could not open target path for writing:');
            $file->moveTo('/dev');
        } finally {
            restore_error_handler();
        }
    }

    #[Test]
    public function moveTo_rewinds_seekable_stream_so_full_payload_is_copied(): void
    {
        $streams = new StreamFactory();
        $stream = $streams->createStream('prefix-suffix');
        $stream->read(7); // position after "prefix"

        $file = new UploadedFile(
            $stream,
            size: null,
            error: \UPLOAD_ERR_OK,
            clientFilename: null,
            clientMediaType: null,
        );

        $target = tempnam(sys_get_temp_dir(), 'php-strong-uploaded-');
        self::assertNotFalse($target);
        unlink($target);
        try {
            $file->moveTo($target);
            self::assertSame('prefix-suffix', file_get_contents($target));
        } finally {
            if (is_file($target)) {
                unlink($target);
            }
        }
    }
}
