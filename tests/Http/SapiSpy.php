<?php

declare(strict_types=1);

namespace Manychois\PhpStrongTests\Http;

/**
 * Records the SAPI calls the shadowed functions in `tests/Http/sapi-functions.php` intercept.
 *
 * The state is static because a shadowed function has no object to reach through. Call `reset()` in `setUp()`
 * so one test cannot observe another's calls.
 */
final class SapiSpy
{
    /**
     * @var list<array{string,bool,int}>
     */
    private static array $recorded = [];
    private static bool $sent = false;
    private static string $sentFile = '';
    private static int $sentLine = 0;

    /**
     * Records one `header()` call.
     *
     * @param string $header The full header line.
     * @param bool $replace Whether the call asked to replace an existing header of the same name.
     * @param int $responseCode The status code the call carried, or 0 when it carried none.
     */
    public static function header(string $header, bool $replace, int $responseCode): void
    {
        self::$recorded[] = [$header, $replace, $responseCode];
    }

    /**
     * Answers a `headers_sent()` call with whatever `markSent()` last configured.
     *
     * @param ?string $filename Receives the file which started the output.
     * @param ?int $line Receives the line which started the output.
     *
     * @return bool True if output has been marked as started; false otherwise.
     */
    public static function headersSent(?string &$filename, ?int &$line): bool
    {
        $filename = self::$sentFile;
        $line = self::$sentLine;

        return self::$sent;
    }

    /**
     * Makes the next `headers_sent()` call report that output has already started.
     *
     * @param string $file The file to report.
     * @param int $line The line to report.
     */
    public static function markSent(string $file, int $line): void
    {
        self::$sent = true;
        self::$sentFile = $file;
        self::$sentLine = $line;
    }

    /**
     * Returns every recorded call in the order it was made.
     *
     * @return list<array{string,bool,int}> The recorded calls, each `[header, replace, responseCode]`.
     */
    public static function recorded(): array
    {
        return self::$recorded;
    }

    /**
     * Clears every recorded call and stops reporting that output has started.
     */
    public static function reset(): void
    {
        self::$recorded = [];
        self::$sent = false;
        self::$sentFile = '';
        self::$sentLine = 0;
    }
}
