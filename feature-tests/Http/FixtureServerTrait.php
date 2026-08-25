<?php

declare(strict_types=1);

namespace Manychois\PhpStrongFeatureTests\Http;

use RuntimeException;

/**
 * Starts and stops the PHP built-in fixture server for feature tests.
 */
trait FixtureServerTrait
{
    private static int $port;

    /**
     * @var ?resource
     */
    private static $serverProcess = null;

    private static function startFixtureServer(): void
    {
        self::$port = self::findFreePort();
        $command = [
            \PHP_BINARY,
            '-S',
            sprintf('127.0.0.1:%d', self::$port),
            __DIR__ . '/fixtures/server.php',
        ];
        $env = getenv();
        $env['PHP_CLI_SERVER_WORKERS'] = '4';
        $process = proc_open($command, [2 => ['pipe', 'w']], $pipes, null, $env);
        if ($process === false) {
            throw new RuntimeException('Failed to start the PHP built-in server.');
        }

        self::$serverProcess = $process;
        self::waitUntilServerIsReady();
    }

    private static function stopFixtureServer(): void
    {
        if (self::$serverProcess === null) {
            return;
        }

        proc_terminate(self::$serverProcess);
        proc_close(self::$serverProcess);
        self::$serverProcess = null;
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
