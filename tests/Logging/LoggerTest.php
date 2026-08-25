<?php

declare(strict_types=1);

namespace Manychois\PhpStrongTests\Logging;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel as PsrLogLevel;
use Manychois\PhpStrong\Logging\ArrayHandler;
use Manychois\PhpStrong\Logging\LogLevel;
use Manychois\PhpStrong\Logging\Logger;
use Manychois\PhpStrong\Time\TestClock;

final class LoggerTest extends TestCase
{
    #[Test]
    public function log_dispatchesLogToHandlers(): void
    {
        $now = new DateTimeImmutable('2026-05-06T07:08:09+00:00');
        $a = new ArrayHandler();
        $b = new ArrayHandler();
        $logger = new Logger('web', [$a, $b], new TestClock($now));
        self::assertInstanceOf(LoggerInterface::class, $logger);

        $logger->log(PsrLogLevel::NOTICE, 'hello {x}', ['x' => 1]);

        self::assertCount(1, $a->logs);
        self::assertCount(1, $b->logs);
        $log = $a->logs[0];
        self::assertSame('web', $log->channel);
        self::assertSame(LogLevel::Notice, $log->level);
        self::assertSame('hello {x}', $log->message);
        self::assertSame(['x' => 1], $log->context);
        self::assertEquals($now, $log->time);
    }

    #[Test]
    public function levelMethods_useMatchingLevel(): void
    {
        $handler = new ArrayHandler();
        $logger = new Logger('app', [$handler]);
        $logger->debug('d');
        $logger->info('i');
        $logger->notice('n');
        $logger->warning('w');
        $logger->error('e');
        $logger->critical('c');
        $logger->alert('a');
        $logger->emergency('m');
        self::assertSame(
            array_map(static fn (LogLevel $l) => $l->value, LogLevel::cases()),
            array_map(static fn ($r) => $r->level->value, $handler->logs),
        );
    }

    #[Test]
    public function log_throws_forInvalidLevel(): void
    {
        $logger = new Logger();
        $this->expectException(InvalidArgumentException::class);
        $logger->log('nope', 'x');
    }

    #[Test]
    public function log_usesUtcClock_byDefault(): void
    {
        $handler = new ArrayHandler();
        $logger = new Logger('app', [$handler]);
        $before = new DateTimeImmutable();
        $logger->info('x');
        self::assertGreaterThanOrEqual($before, $handler->logs[0]->time);
        self::assertSame('UTC', $handler->logs[0]->time->getTimezone()->getName());
    }

    #[Test]
    public function pushHandler_addsHandler(): void
    {
        $logger = new Logger();
        $handler = new ArrayHandler();
        $logger->pushHandler($handler);
        $logger->info('x');
        self::assertCount(1, $handler->logs);
    }

    #[Test]
    public function withChannel_sharesHandlers(): void
    {
        $handler = new ArrayHandler();
        $logger = (new Logger('app', [$handler]))->withChannel('db');
        $logger->info('x');
        self::assertSame('db', $handler->logs[0]->channel);
    }

    #[Test]
    public function arrayHandler_respectsMinLevelAndClear(): void
    {
        $handler = new ArrayHandler(LogLevel::Warning);
        $logger = new Logger('app', [$handler]);
        $logger->info('skip');
        $logger->error('keep');
        self::assertCount(1, $handler->logs);
        $handler->clear();
        self::assertSame([], $handler->logs);
    }
}
