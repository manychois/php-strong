<?php

declare(strict_types=1);

namespace Manychois\PhpStrongTests\Logging;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\InvalidArgumentException;
use Psr\Log\LogLevel as PsrLogLevel;
use Manychois\PhpStrong\Logging\LogLevel;

final class LogLevelTest extends TestCase
{
    #[Test]
    public function fromPsr_returnsCase_forPsrString(): void
    {
        self::assertSame(LogLevel::Warning, LogLevel::fromPsr(PsrLogLevel::WARNING));
        self::assertSame(LogLevel::Error, LogLevel::fromPsr('ERROR'));
        self::assertSame(LogLevel::Info, LogLevel::fromPsr(LogLevel::Info));
    }

    #[Test]
    public function fromPsr_throws_forUnknownLevel(): void
    {
        $this->expectException(InvalidArgumentException::class);
        LogLevel::fromPsr('verbose');
    }

    #[Test]
    public function fromPsr_throws_forNonString(): void
    {
        $this->expectException(InvalidArgumentException::class);
        LogLevel::fromPsr(3);
    }

    #[Test]
    public function atLeast_ordersBySeverity(): void
    {
        self::assertTrue(LogLevel::Emergency->atLeast(LogLevel::Debug));
        self::assertTrue(LogLevel::Warning->atLeast(LogLevel::Warning));
        self::assertFalse(LogLevel::Notice->atLeast(LogLevel::Warning));
    }
}
