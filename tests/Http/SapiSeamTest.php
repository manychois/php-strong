<?php

declare(strict_types=1);

namespace Manychois\PhpStrongTests\Http;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function Manychois\PhpStrong\Http\header;
use function Manychois\PhpStrong\Http\headers_sent;

/**
 * Unit tests for the shadowed SAPI functions and {@see SapiSpy}.
 */
final class SapiSeamTest extends TestCase
{
    protected function setUp(): void
    {
        SapiSpy::reset();
    }

    #[Test]
    public function theShadowedHeaderFunctionRecordsEveryCallInOrder(): void
    {
        header('X-First: 1');
        header('X-Second: 2', false);
        header('HTTP/1.1 404 Not Found', true, 404);

        static::assertSame([
            ['X-First: 1', true, 0],
            ['X-Second: 2', false, 0],
            ['HTTP/1.1 404 Not Found', true, 404],
        ], SapiSpy::recorded());
    }

    #[Test]
    public function headersSentReportsFalseUntilMarked(): void
    {
        $file = '';
        $line = 0;

        static::assertFalse(headers_sent($file, $line));
        static::assertSame('', $file);
        static::assertSame(0, $line);
    }

    #[Test]
    public function headersSentReportsTheMarkedFileAndLine(): void
    {
        SapiSpy::markSent('/app/public/index.php', 12);

        $file = '';
        $line = 0;

        static::assertTrue(headers_sent($file, $line));
        static::assertSame('/app/public/index.php', $file);
        static::assertSame(12, $line);
    }

    #[Test]
    public function resetClearsRecordedCallsAndTheSentFlag(): void
    {
        header('X-Gone: 1');
        SapiSpy::markSent('/somewhere.php', 3);

        SapiSpy::reset();

        $file = '';
        $line = 0;

        static::assertSame([], SapiSpy::recorded());
        static::assertFalse(headers_sent($file, $line));
    }
}
