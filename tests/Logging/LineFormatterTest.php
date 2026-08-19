<?php

declare(strict_types=1);

namespace Manychois\PhpStrongTests\Logging;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Manychois\PhpStrong\Logging\LogLevel;
use Manychois\PhpStrong\Logging\LineFormatter;
use Manychois\PhpStrong\Logging\Log;

final class LineFormatterTest extends TestCase
{
    #[Test]
    public function format_rendersLineWithUnusedContext(): void
    {
        $log = new Log('app', LogLevel::Warning, 'Hi {who}', ['who' => 'x', 'n' => 1], $this->time());
        $line = (new LineFormatter('Y-m-d'))->format($log);
        self::assertSame('[2026-01-02] app.WARNING: Hi x {"n":1}' . \PHP_EOL, $line);
    }

    #[Test]
    public function format_omitsContextBlock_whenEmpty(): void
    {
        $log = new Log('app', LogLevel::Info, 'ok', [], $this->time());
        self::assertSame('[2026-01-02] app.INFO: ok' . \PHP_EOL, (new LineFormatter('Y-m-d'))->format($log));
    }

    #[Test]
    public function format_appendsException(): void
    {
        $e = new RuntimeException('boom');
        $log = new Log('app', LogLevel::Error, 'fail', ['exception' => $e], $this->time());
        $line = (new LineFormatter('Y-m-d'))->format($log);
        self::assertStringStartsWith('[2026-01-02] app.ERROR: fail' . \PHP_EOL . 'RuntimeException: boom', $line);
    }

    private function time(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-01-02T03:04:05+00:00');
    }
}
