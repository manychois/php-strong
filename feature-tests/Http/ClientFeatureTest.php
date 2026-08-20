<?php

declare(strict_types=1);

namespace Manychois\PhpStrongFeatureTests\Http;

use Manychois\PhpStrong\Http\Client;
use Manychois\PhpStrong\Http\NetworkException;
use Manychois\PhpStrong\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Feature tests for {@see Client} against a real PHP built-in server.
 */
final class ClientFeatureTest extends TestCase
{
    private static int $port;

    /**
     * @var ?resource
     */
    private static $serverProcess = null;

    public static function setUpBeforeClass(): void
    {
        self::$port = self::findFreePort();
        $command = [
            \PHP_BINARY,
            '-S',
            sprintf('127.0.0.1:%d', self::$port),
            __DIR__ . '/fixtures/server.php',
        ];
        $process = proc_open($command, [2 => ['pipe', 'w']], $pipes);
        if ($process === false) {
            throw new RuntimeException('Failed to start the PHP built-in server.');
        }

        self::$serverProcess = $process;
        self::waitUntilServerIsReady();
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$serverProcess === null) {
            return;
        }

        proc_terminate(self::$serverProcess);
        proc_close(self::$serverProcess);
        self::$serverProcess = null;
    }

    #[Test]
    public function get_request_returns_status_headers_and_body(): void
    {
        $client = new Client();

        $response = $client->sendRequest(new Request('GET', self::url('/hello')));

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('OK', $response->getReasonPhrase());
        self::assertSame('php-strong', $response->getHeaderLine('X-Server'));
        self::assertSame('Hello, world!', (string) $response->getBody());
        self::assertSame('1.1', $response->getProtocolVersion());
    }

    #[Test]
    public function post_request_sends_body_and_headers(): void
    {
        $client = new Client();
        $request = new Request('POST', self::url('/echo'), ['X-Custom' => 'abc'], 'payload');

        $response = $client->sendRequest($request);

        self::assertSame('POST|payload|abc', (string) $response->getBody());
    }

    #[Test]
    public function error_status_is_returned_as_a_response_not_an_exception(): void
    {
        $client = new Client();

        $response = $client->sendRequest(new Request('GET', self::url('/status?code=503')));

        self::assertSame(503, $response->getStatusCode());
        self::assertSame('status body', (string) $response->getBody());
    }

    #[Test]
    public function redirects_are_not_followed(): void
    {
        $client = new Client();

        $response = $client->sendRequest(new Request('GET', self::url('/redirect')));

        self::assertSame(302, $response->getStatusCode());
        self::assertSame('/hello', $response->getHeaderLine('Location'));
    }

    #[Test]
    public function repeated_headers_are_preserved(): void
    {
        $client = new Client();

        $response = $client->sendRequest(new Request('GET', self::url('/cookies')));

        self::assertSame(['a=1', 'b=2'], $response->getHeader('Set-Cookie'));
    }

    #[Test]
    public function head_request_returns_no_body(): void
    {
        $client = new Client();

        $response = $client->sendRequest(new Request('HEAD', self::url('/hello')));

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('', (string) $response->getBody());
    }

    #[Test]
    public function connection_to_a_closed_port_throws_NetworkException(): void
    {
        $client = new Client(timeout: 1.0);
        $request = new Request('GET', sprintf('http://127.0.0.1:%d/', self::findFreePort()));

        try {
            $client->sendRequest($request);
            self::fail('Expected NetworkException.');
        } catch (NetworkException $ex) {
            self::assertSame($request, $ex->getRequest());
            self::assertStringContainsString('cURL error', $ex->getMessage());
        }
    }

    #[Test]
    public function slow_response_throws_NetworkException_on_timeout(): void
    {
        $client = new Client(timeout: 0.2);

        $this->expectException(NetworkException::class);

        $client->sendRequest(new Request('GET', self::url('/slow')));
    }

    private static function url(string $pathAndQuery): string
    {
        return sprintf('http://127.0.0.1:%d%s', self::$port, $pathAndQuery);
    }

    private static function findFreePort(): int
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
        if ($socket === false) {
            throw new RuntimeException(sprintf('Failed to open a socket: %s', $error));
        }

        $name = stream_socket_get_name($socket, false);
        assert(is_string($name));
        fclose($socket);
        $colonPos = strrpos($name, ':');
        assert($colonPos !== false);

        return (int) substr($name, $colonPos + 1);
    }

    private static function waitUntilServerIsReady(): void
    {
        $deadline = microtime(true) + 5.0;
        while (microtime(true) < $deadline) {
            $socket = @fsockopen('127.0.0.1', self::$port, $errno, $error, 0.1);
            if ($socket !== false) {
                fclose($socket);

                return;
            }

            usleep(50_000);
        }

        throw new RuntimeException('The PHP built-in server did not become ready in time.');
    }
}
