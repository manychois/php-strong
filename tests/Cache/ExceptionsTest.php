<?php

declare(strict_types=1);

namespace Manychois\PhpStrongTests\Cache;

use InvalidArgumentException as SplInvalidArgumentException;
use Manychois\PhpStrong\Cache\CacheException;
use Manychois\PhpStrong\Cache\InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheException as IPsrCacheException;
use Psr\Cache\InvalidArgumentException as IPsrInvalidArgument;
use Psr\SimpleCache\CacheException as IPsrSimpleCacheException;
use Psr\SimpleCache\InvalidArgumentException as IPsrSimpleInvalidArgument;
use RuntimeException;

final class ExceptionsTest extends TestCase
{
    #[Test]
    public function cacheException_isARuntimeExceptionAndAPsrCacheException(): void
    {
        $ex = new CacheException('boom');

        self::assertInstanceOf(RuntimeException::class, $ex);
        self::assertInstanceOf(IPsrCacheException::class, $ex);
        self::assertInstanceOf(IPsrSimpleCacheException::class, $ex);
        self::assertSame('boom', $ex->getMessage());
    }

    #[Test]
    public function invalidArgumentException_isAnSplInvalidArgumentAndAPsrInvalidArgument(): void
    {
        $ex = new InvalidArgumentException('bad key');

        self::assertInstanceOf(SplInvalidArgumentException::class, $ex);
        self::assertInstanceOf(IPsrInvalidArgument::class, $ex);
        self::assertInstanceOf(IPsrCacheException::class, $ex);
        self::assertInstanceOf(IPsrSimpleInvalidArgument::class, $ex);
        self::assertInstanceOf(IPsrSimpleCacheException::class, $ex);
        self::assertSame('bad key', $ex->getMessage());
    }
}
