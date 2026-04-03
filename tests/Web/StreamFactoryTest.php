<?php

declare(strict_types=1);

namespace Manychois\PhpStrongTests\Web;

use InvalidArgumentException;
use Manychois\PhpStrong\Web\Stream;
use Manychois\PhpStrong\Web\StreamFactory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Unit tests for {@see StreamFactory}.
 */
final class StreamFactoryTest extends TestCase
{
    #[Test]
    public function createStream_defaults_to_empty_readable_body(): void
    {
        $factory = new StreamFactory();
        $stream = $factory->createStream();

        self::assertSame('', (string) $stream);
        self::assertTrue($stream->isReadable());
        self::assertSame(0, $stream->getSize());
    }

    #[Test]
    public function createStream_writes_initial_content_and_rewinds(): void
    {
        $factory = new StreamFactory();
        $stream = $factory->createStream('hello');

        self::assertSame('hello', $stream->getContents());
    }

    #[Test]
    public function createStreamFromFile_opens_existing_path(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'php-strong-stream');
        self::assertNotFalse($path);
        try {
            self::assertSame(1, file_put_contents($path, 'x'));

            $factory = new StreamFactory();
            $stream = $factory->createStreamFromFile($path, 'rb');

            self::assertSame('x', $stream->getContents());
        } finally {
            unlink($path);
        }
    }

    #[Test]
    public function createStreamFromFile_throws_when_mode_is_empty(): void
    {
        $factory = new StreamFactory();
        $path = tempnam(sys_get_temp_dir(), 'php-strong-stream');
        self::assertNotFalse($path);
        try {
            $this->expectException(InvalidArgumentException::class);
            $this->expectExceptionMessage('File open mode must not be empty.');
            $factory->createStreamFromFile($path, '');
        } finally {
            unlink($path);
        }
    }

    #[Test]
    public function createStreamFromFile_throws_when_file_cannot_be_opened(): void
    {
        $factory = new StreamFactory();
        $missing = sys_get_temp_dir() . '/php-strong-stream-missing-' . bin2hex(random_bytes(8));

        set_error_handler(static fn (int $errno, string $errstr, string $errfile, int $errline): true => true);
        try {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('Could not open stream for');
            $factory->createStreamFromFile($missing, 'rb');
        } finally {
            restore_error_handler();
        }
    }

    #[Test]
    public function createStreamFromResource_wraps_handle(): void
    {
        $resource = fopen('php://memory', 'r+b');
        self::assertNotFalse($resource);
        fwrite($resource, 'abc');
        rewind($resource);

        $factory = new StreamFactory();
        $stream = $factory->createStreamFromResource($resource);

        self::assertInstanceOf(Stream::class, $stream);
        self::assertSame('abc', $stream->getContents());
        $stream->close();
    }
}
