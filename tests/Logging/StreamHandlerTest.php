<?php

declare(strict_types=1);

namespace Manychois\PhpStrongTests\Logging;

use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Manychois\PhpStrong\Logging\LogLevel;
use Manychois\PhpStrong\Logging\LineFormatter;
use Manychois\PhpStrong\Logging\Log;
use Manychois\PhpStrong\Logging\StreamHandler;

final class StreamHandlerTest extends TestCase
{
    #[Test]
    public function handle_writesToResource(): void
    {
        $stream = fopen('php://memory', 'w+');
        self::assertIsResource($stream);
        $handler = new StreamHandler($stream, LogLevel::Debug, new LineFormatter('Y'));
        $handler->handle($this->log(LogLevel::Info, 'a'));
        rewind($stream);
        self::assertSame('[2026] app.INFO: a' . \PHP_EOL, stream_get_contents($stream));
    }

    #[Test]
    public function handle_skipsLogsBelowMinLevel(): void
    {
        $stream = fopen('php://memory', 'w+');
        self::assertIsResource($stream);
        $handler = new StreamHandler($stream, LogLevel::Error);
        $handler->handle($this->log(LogLevel::Warning, 'a'));
        rewind($stream);
        self::assertSame('', stream_get_contents($stream));
    }

    #[Test]
    public function handle_createsFileAndDirectoryLazily(): void
    {
        $dir = sys_get_temp_dir() . '/tsi-log-' . bin2hex(random_bytes(4));
        $path = $dir . '/sub/app.log';
        $handler = new StreamHandler($path, LogLevel::Debug, new LineFormatter('Y'));
        self::assertFileDoesNotExist($path);
        $handler->handle($this->log(LogLevel::Info, 'one'));
        $handler->handle($this->log(LogLevel::Info, 'two'));
        self::assertSame(
            '[2026] app.INFO: one' . \PHP_EOL . '[2026] app.INFO: two' . \PHP_EOL,
            file_get_contents($path),
        );
        unlink($path);
        rmdir($dir . '/sub');
        rmdir($dir);
    }

    #[Test]
    public function handle_throws_whenStreamCannotBeOpened(): void
    {
        $handler = new StreamHandler('/dev/null/nope/app.log');
        $this->expectException(RuntimeException::class);
        $handler->handle($this->log(LogLevel::Info, 'a'));
    }

    #[Test]
    public function handle_throws_whenPathIsNotWritable(): void
    {
        $handler = new StreamHandler(sys_get_temp_dir());
        $this->expectException(RuntimeException::class);
        $handler->handle($this->log(LogLevel::Info, 'a'));
    }

    #[Test]
    public function construct_throws_whenStreamIsInvalid(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new StreamHandler(123);
    }

    private function log(LogLevel $level, string $message): Log
    {
        return new Log('app', $level, $message, [], new DateTimeImmutable('2026-01-01'));
    }
}
