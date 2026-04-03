<?php

declare(strict_types=1);

namespace Manychois\PhpStrongTests\Web;

use Manychois\PhpStrong\Web\Response;
use Manychois\PhpStrong\Web\ResponseFactory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for {@see ResponseFactory}.
 */
final class ResponseFactoryTest extends TestCase
{
    #[Test]
    public function createResponse_defaults_to_200_with_standard_reason(): void
    {
        $factory = new ResponseFactory();
        $response = $factory->createResponse();

        self::assertInstanceOf(Response::class, $response);
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('OK', $response->getReasonPhrase());
    }

    #[Test]
    public function createResponse_accepts_status_and_custom_reason(): void
    {
        $factory = new ResponseFactory();
        $response = $factory->createResponse(404, 'Gone Fishing');

        self::assertSame(404, $response->getStatusCode());
        self::assertSame('Gone Fishing', $response->getReasonPhrase());
    }

    #[Test]
    public function createResponse_uses_default_reason_when_phrase_empty(): void
    {
        $factory = new ResponseFactory();
        $response = $factory->createResponse(500, '');

        self::assertSame(500, $response->getStatusCode());
        self::assertSame('Internal Server Error', $response->getReasonPhrase());
    }
}
