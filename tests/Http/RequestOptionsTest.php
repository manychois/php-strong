<?php

declare(strict_types=1);

namespace Manychois\PhpStrongTests\Http;

use InvalidArgumentException;
use Manychois\PhpStrong\Http\RequestOptions;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for {@see RequestOptions}.
 */
final class RequestOptionsTest extends TestCase
{
    #[Test]
    public function defaults_are_sensible(): void
    {
        $options = new RequestOptions();

        self::assertSame(30.0, $options->timeout);
        self::assertSame(10.0, $options->connectTimeout);
        self::assertFalse($options->followRedirects);
        self::assertSame(10, $options->maxRedirects);
        self::assertTrue($options->verifyTls);
        self::assertNull($options->proxy);
        self::assertNull($options->userAgent);
        self::assertNull($options->caFile);
        self::assertNull($options->caPath);
    }

    #[Test]
    public function constructor_accepts_custom_values(): void
    {
        $options = new RequestOptions(
            timeout: 5.5,
            connectTimeout: 1.5,
            followRedirects: true,
            maxRedirects: 3,
            verifyTls: false,
            proxy: 'http://proxy.local:8080',
            userAgent: 'php-strong/1.0',
            caFile: '/etc/ssl/ca.pem',
            caPath: '/etc/ssl/certs',
        );

        self::assertSame(5.5, $options->timeout);
        self::assertSame(1.5, $options->connectTimeout);
        self::assertTrue($options->followRedirects);
        self::assertSame(3, $options->maxRedirects);
        self::assertFalse($options->verifyTls);
        self::assertSame('http://proxy.local:8080', $options->proxy);
        self::assertSame('php-strong/1.0', $options->userAgent);
        self::assertSame('/etc/ssl/ca.pem', $options->caFile);
        self::assertSame('/etc/ssl/certs', $options->caPath);
    }

    /**
     * @return array<string,array{callable():RequestOptions,string}>
     */
    public static function provideInvalidValues(): array
    {
        return [
            'zero timeout' => [static fn () => new RequestOptions(timeout: 0.0), 'Timeout'],
            'negative timeout' => [static fn () => new RequestOptions(timeout: -1.0), 'Timeout'],
            'zero connect timeout' => [static fn () => new RequestOptions(connectTimeout: 0.0), 'Connect timeout'],
            'negative max redirects' => [static fn () => new RequestOptions(maxRedirects: -1), 'Max redirects'],
            'empty proxy' => [static fn () => new RequestOptions(proxy: ''), 'Proxy'],
            'empty user agent' => [static fn () => new RequestOptions(userAgent: ''), 'User agent'],
            'empty CA file' => [static fn () => new RequestOptions(caFile: ''), 'CA file'],
            'empty CA path' => [static fn () => new RequestOptions(caPath: ''), 'CA path'],
        ];
    }

    /**
     * @param callable():RequestOptions $create
     */
    #[Test]
    #[DataProvider('provideInvalidValues')]
    public function constructor_rejects_invalid_values(callable $create, string $messagePart): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($messagePart);

        $create();
    }
}
