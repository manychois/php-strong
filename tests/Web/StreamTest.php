<?php

declare(strict_types=1);

namespace Manychois\PhpStrongTests\Web;

use Manychois\PhpStrong\Web\Stream;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Unit tests for {@see Stream}.
 */
final class StreamTest extends TestCase
{
    #[Test]
    public function close_nulls_resource_and_getContents_throws(): void
    {
        $stream = self::memoryStream('x');
        $stream->close();
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Stream is detached.');
        $stream->getContents();
    }

    #[Test]
    public function constructor_throws_when_value_is_not_resource(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Expected a PHP stream resource; got int.');
        // @phpstan-ignore argument.type
        new Stream(42);
    }

    #[Test]
    public function detach_returns_resource_and_instance_is_detached(): void
    {
        $stream = self::memoryStream('a');
        $raw = $stream->detach();
        self::assertIsResource($raw);
        self::assertSame('a', stream_get_contents($raw));
        fclose($raw);

        $this->expectException(RuntimeException::class);
        $stream->getContents();
    }

    #[Test]
    public function eof_is_true_when_stream_is_detached(): void
    {
        $stream = self::memoryStream('z');
        $stream->detach();
        self::assertTrue($stream->eof());
    }

    #[Test]
    public function isReadable_returns_false_when_detached(): void
    {
        $stream = self::memoryStream('z');
        $stream->detach();
        self::assertFalse($stream->isReadable());
    }

    #[Test]
    public function isWritable_returns_false_when_detached(): void
    {
        $stream = self::memoryStream('z');
        $stream->detach();
        self::assertFalse($stream->isWritable());
    }

    #[Test]
    public function eof_is_false_at_start_of_memory_stream_with_data(): void
    {
        $stream = self::memoryStream('data');
        self::assertFalse($stream->eof());
    }

    #[Test]
    public function getContents_reads_from_current_position(): void
    {
        $stream = self::memoryStream('left-right');
        self::assertSame('lef', $stream->read(3));
        self::assertSame('t-right', $stream->getContents());
    }

    #[Test]
    public function getMetadata_returns_empty_array_when_detached_with_null_key(): void
    {
        $stream = self::memoryStream();
        $stream->detach();
        self::assertSame([], $stream->getMetadata());
    }

    #[Test]
    public function getMetadata_returns_null_for_unknown_key_on_attached_stream(): void
    {
        $stream = self::memoryStream();
        self::assertNull($stream->getMetadata('no_such_meta_key'));
    }

    #[Test]
    public function getMetadata_returns_null_when_detached_and_key_given(): void
    {
        $stream = self::memoryStream();
        $stream->detach();
        self::assertNull($stream->getMetadata('mode'));
    }

    #[Test]
    public function getMetadata_includes_mode_for_memory_stream(): void
    {
        $stream = self::memoryStream();
        $mode = $stream->getMetadata('mode');
        self::assertIsString($mode);
        self::assertStringContainsString('b', $mode);
    }

    #[Test]
    public function getSize_is_null_when_detached(): void
    {
        $stream = self::memoryStream('abc');
        $stream->detach();
        self::assertNull($stream->getSize());
    }

    #[Test]
    public function getSize_reports_length_for_memory_stream(): void
    {
        $stream = self::memoryStream('four');
        self::assertSame(4, $stream->getSize());
    }

    #[Test]
    public function isReadable_is_false_for_write_only_stream(): void
    {
        $stream = self::writeOnlyTempStream();
        self::assertFalse($stream->isReadable());
        $stream->close();
    }

    #[Test]
    public function isWritable_is_false_for_read_only_stream(): void
    {
        $stream = self::readOnlyTempStream('k');
        self::assertFalse($stream->isWritable());
        $stream->close();
    }

    #[Test]
    public function read_negative_length_throws(): void
    {
        $stream = self::memoryStream('x');
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Length must be non-negative.');
        $stream->read(-1);
    }

    #[Test]
    public function read_throws_when_stream_is_not_readable(): void
    {
        $stream = self::writeOnlyTempStream();
        try {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('Stream is not readable.');
            $stream->read(1);
        } finally {
            $stream->close();
        }
    }

    #[Test]
    public function read_zero_returns_empty_string(): void
    {
        $stream = self::memoryStream('abc');
        self::assertSame('', $stream->read(0));
        self::assertSame(0, $stream->tell());
    }

    #[Test]
    public function rewind_and_tell_track_position_on_seekable_stream(): void
    {
        $stream = self::memoryStream('012345');
        self::assertSame(0, $stream->tell());
        self::assertSame('012', $stream->read(3));
        self::assertSame(3, $stream->tell());
        $stream->rewind();
        self::assertSame(0, $stream->tell());
        self::assertSame('012345', $stream->getContents());
    }

    #[Test]
    public function seek_throws_when_stream_is_not_seekable(): void
    {
        $stream = self::pipeReadStream();
        try {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('Stream is not seekable.');
            $stream->seek(0);
        } finally {
            self::drainAndClose($stream);
        }
    }

    #[Test]
    public function toString_returns_contents_for_seekable_memory_stream(): void
    {
        $body = 'payload';
        $stream = self::memoryStream($body);
        self::assertSame($body, (string) $stream);
        self::assertSame(\strlen($body), $stream->tell());
        self::assertTrue($stream->eof());
    }

    #[Test]
    public function toString_returns_empty_after_detach(): void
    {
        $stream = self::memoryStream('nope');
        $stream->detach();
        self::assertSame('', (string) $stream);
    }

    #[Test]
    public function toString_returns_empty_when_rewind_throws_on_non_seekable_stream(): void
    {
        $stream = self::pipeReadStream();
        try {
            self::assertSame('', (string) $stream);
        } finally {
            self::drainAndClose($stream);
        }
    }

    #[Test]
    public function write_throws_when_stream_is_not_writable(): void
    {
        $stream = self::readOnlyTempStream('only-read');
        try {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('Stream is not writable.');
            $stream->write('x');
        } finally {
            $stream->close();
        }
    }

    #[Test]
    public function write_appends_and_getSize_updates(): void
    {
        $stream = self::memoryStream('');
        self::assertSame(3, $stream->write('foo'));
        self::assertSame(3, $stream->getSize());
        $stream->rewind();
        self::assertSame('foo', $stream->getContents());
    }

    /**
     * @return Stream Stream over `php://memory` with optional initial bytes; closed by caller via {@see Stream::close()}.
     */
    private static function memoryStream(string $content = '', string $mode = 'r+b'): Stream
    {
        $resource = fopen('php://memory', $mode);
        self::assertNotFalse($resource);
        if ($content !== '') {
            fwrite($resource, $content);
            rewind($resource);
        }
        return new Stream($resource);
    }

    /**
     * Read-only stream backed by a temporary file; file unlinked after open.
     */
    private static function readOnlyTempStream(string $content): Stream
    {
        $path = tempnam(sys_get_temp_dir(), 'php-strong-stream-ro');
        self::assertNotFalse($path);
        try {
            self::assertNotFalse(file_put_contents($path, $content));
            $resource = fopen($path, 'rb');
            self::assertNotFalse($resource);
            return new Stream($resource);
        } finally {
            unlink($path);
        }
    }

    /**
     * Discards remaining bytes then closes so subprocess pipes exit cleanly.
     */
    private static function drainAndClose(Stream $stream): void
    {
        try {
            while (!$stream->eof()) {
                $chunk = $stream->read(8192);
                if ($chunk === '') {
                    break;
                }
            }
        } catch (RuntimeException) {
            // Detached or not readable; still attempt close.
        }
        $stream->close();
    }

    /**
     * Non-seekable readable stream (subprocess stdout pipe).
     */
    private static function pipeReadStream(): Stream
    {
        $php = escapeshellarg(PHP_BINARY);
        $payload = 'test-body';
        $code = 'fwrite(STDOUT, ' . var_export($payload . "\n", true) . ');';
        $inner = escapeshellarg($code);
        $resource = popen("{$php} -r {$inner}", 'r');
        self::assertNotFalse($resource);
        return new Stream($resource);
    }

    /**
     * Writable-only temp file stream (`wb`); file unlinked after open.
     */
    private static function writeOnlyTempStream(): Stream
    {
        $path = tempnam(sys_get_temp_dir(), 'php-strong-stream-wo');
        self::assertNotFalse($path);
        $resource = fopen($path, 'wb');
        self::assertNotFalse($resource);
        unlink($path);
        return new Stream($resource);
    }
}
