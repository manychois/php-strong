<?php

declare(strict_types=1);

namespace Manychois\PhpStrongTests\Logging;

use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Manychois\PhpStrong\Logging\ConsoleFormatter;
use Manychois\PhpStrong\Logging\ConsoleHandler;
use Manychois\PhpStrong\Logging\LogLevel;
use Manychois\PhpStrong\Logging\Log;

final class ConsoleHandlerTest extends TestCase
{
    #[Test]
    public function handle_routesByLevel(): void
    {
        [$out, $err] = [$this->memory(), $this->memory()];
        $handler = new ConsoleHandler(LogLevel::Debug, false, null, $out, $err);
        $handler->handle($this->log(LogLevel::Info, 'hello'));
        $handler->handle($this->log(LogLevel::Notice, 'note'));
        $handler->handle($this->log(LogLevel::Warning, 'careful'));
        $handler->handle($this->log(LogLevel::Error, 'bad'));

        self::assertSame(
            '03:04:05 INFO      hello' . \PHP_EOL . '03:04:05 NOTICE    note' . \PHP_EOL,
            $this->contents($out),
        );
        self::assertSame(
            '03:04:05 WARNING   careful' . \PHP_EOL . '03:04:05 ERROR     bad' . \PHP_EOL,
            $this->contents($err),
        );
    }

    #[Test]
    public function handle_skipsBelowMinLevel(): void
    {
        [$out, $err] = [$this->memory(), $this->memory()];
        $handler = new ConsoleHandler(LogLevel::Error, false, null, $out, $err);
        $handler->handle($this->log(LogLevel::Warning, 'x'));
        self::assertSame('', $this->contents($out));
        self::assertSame('', $this->contents($err));
    }

    #[Test]
    public function handle_usesColors_whenForced(): void
    {
        [$out, $err] = [$this->memory(), $this->memory()];
        $handler = new ConsoleHandler(LogLevel::Debug, true, null, $out, $err);
        $handler->handle($this->log(LogLevel::Info, 'hi'));
        $handler->handle($this->log(LogLevel::Error, 'bad'));
        self::assertSame("03:04:05 \e[32mINFO     \e[0m hi" . \PHP_EOL, $this->contents($out));
        self::assertSame("03:04:05 \e[31mERROR    \e[0m \e[31mbad\e[0m" . \PHP_EOL, $this->contents($err));
    }

    #[Test]
    public function handle_disablesColors_forNonTty(): void
    {
        [$out, $err] = [$this->memory(), $this->memory()];
        $handler = new ConsoleHandler(LogLevel::Debug, null, null, $out, $err);
        $handler->handle($this->log(LogLevel::Error, 'bad'));
        self::assertSame('03:04:05 ERROR     bad' . \PHP_EOL, $this->contents($err));
    }

    #[Test]
    public function construct_opensDefaultStreams(): void
    {
        $handler = new ConsoleHandler(LogLevel::Emergency);
        $handler->handle($this->log(LogLevel::Debug, 'ignored'));
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function construct_acceptsStreamUrls(): void
    {
        $handler = new ConsoleHandler(LogLevel::Debug, false, null, 'php://memory', 'php://memory');
        $handler->handle($this->log(LogLevel::Info, 'hi'));
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function construct_throws_whenStreamCannotBeOpened(): void
    {
        $this->expectException(RuntimeException::class);
        new ConsoleHandler(LogLevel::Debug, false, null, '/dev/null/nope', 'php://memory');
    }

    #[Test]
    public function construct_throws_whenStreamIsInvalid(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new ConsoleHandler(LogLevel::Debug, false, null, 123);
    }

    #[Test]
    public function consoleFormatter_appendsContextAndException(): void
    {
        $formatter = new ConsoleFormatter(false, '');
        $context = ['op' => 'save', 'id' => 7, 'exception' => new RuntimeException('boom')];
        $log = $this->log(LogLevel::Error, 'fail {op}', $context);
        $line = $formatter->format($log);
        self::assertStringStartsWith('ERROR     fail save {"id":7}' . \PHP_EOL . 'RuntimeException: boom', $line);
    }

    /**
     * @param array<string, mixed> $context
     */
    private function log(LogLevel $level, string $message, array $context = []): Log
    {
        return new Log('app', $level, $message, $context, new DateTimeImmutable('2026-01-02T03:04:05+00:00'));
    }

    /**
     * @return resource
     */
    private function memory(): mixed
    {
        $stream = fopen('php://memory', 'w+');
        self::assertIsResource($stream);

        return $stream;
    }

    /**
     * @param resource $stream
     */
    private function contents(mixed $stream): string
    {
        rewind($stream);

        return (string) stream_get_contents($stream);
    }
}
