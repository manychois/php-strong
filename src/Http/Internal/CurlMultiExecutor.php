<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\Http\Internal;

use CurlHandle;
use CurlMultiHandle;
use Manychois\PhpStrong\Http\ClientException;

/**
 * Runs multiple cURL transfers concurrently and reports each completion to a callback.
 *
 * @internal
 */
final class CurlMultiExecutor
{
    private readonly CurlMultiHandle $multiHandle;

    /**
     * @var array<int,callable(int,string,list<string>,string):void> Completion callbacks
     * keyed by the spl_object_id of the easy handle.
     */
    private array $callbacks = [];

    /**
     * @var array<int,CurlHandle> Active easy handles keyed by their spl_object_id.
     */
    private array $handles = [];

    /**
     * @var array<int,list<string>> Collected response header lines keyed by the
     * spl_object_id of the easy handle.
     */
    private array $headerLines = [];

    /**
     * Creates a new executor with its own cURL multi handle.
     */
    public function __construct()
    {
        $this->multiHandle = curl_multi_init();
    }

    /**
     * Counts the number of active transfers currently attached to this executor.
     *
     * @return int The number of transfers awaiting completion or collection.
     *
     * @internal
     */
    public function activeCount(): int
    {
        return count($this->handles);
    }

    /**
     * Attaches a transfer to this executor.
     *
     * @param CurlHandle $handle The fully configured easy handle.
     * @param callable $onComplete Called once when the transfer finishes, with the cURL
     * result code, an error message ('' on success), the collected header lines, and the body.
     *
     * @phpstan-param callable(int,string,list<string>,string):void $onComplete
     *
     * @throws ClientException if the handle could not be registered with the multi handle.
     */
    public function add(CurlHandle $handle, callable $onComplete): void
    {
        $id = spl_object_id($handle);
        $this->callbacks[$id] = $onComplete;
        $this->handles[$id] = $handle;
        $this->headerLines[$id] = [];
        curl_setopt(
            $handle,
            \CURLOPT_HEADERFUNCTION,
            function (CurlHandle $h, string $line): int {
                $trimmed = trim($line);
                $id = spl_object_id($h);
                if ($trimmed !== '' && isset($this->headerLines[$id])) {
                    $this->headerLines[$id][] = $trimmed;
                }

                return strlen($line);
            },
        );
        $code = curl_multi_add_handle($this->multiHandle, $handle);
        if ($code !== \CURLM_OK) {
            unset($this->callbacks[$id], $this->handles[$id], $this->headerLines[$id]);

            throw new ClientException(
                sprintf('cURL multi error %d: %s', $code, curl_multi_strerror($code) ?? 'unknown error'),
            );
        }
    }

    /**
     * Performs one iteration of transfer processing: runs ready I/O, optionally waits
     * briefly for socket activity, then delivers every finished transfer to its callback.
     *
     * @param float $maxWait The maximum time to wait for socket activity, in seconds.
     */
    public function pump(float $maxWait = 0.05): void
    {
        $stillRunning = 0;
        curl_multi_exec($this->multiHandle, $stillRunning);
        if ($stillRunning > 0 && $maxWait > 0) {
            if (curl_multi_select($this->multiHandle, $maxWait) === -1) {
                usleep(1000);
            }
            curl_multi_exec($this->multiHandle, $stillRunning);
        }

        while (true) {
            $info = curl_multi_info_read($this->multiHandle);
            if ($info === false) {
                break;
            }

            $this->deliverCompletion($info);
        }
    }

    /**
     * Detaches a transfer, aborting it if it has not finished. Safe to call twice.
     *
     * @param CurlHandle $handle The easy handle to detach.
     */
    public function remove(CurlHandle $handle): void
    {
        $id = spl_object_id($handle);
        if (!array_key_exists($id, $this->handles)) {
            return;
        }

        curl_multi_remove_handle($this->multiHandle, $handle);
        unset($this->callbacks[$id], $this->handles[$id], $this->headerLines[$id]);
    }

    /**
     * Reports one finished transfer described by a `curl_multi_info_read()` entry to its
     * completion callback, ignoring entries that do not describe a tracked transfer.
     *
     * @param array $info One entry as returned by `curl_multi_info_read()`.
     *
     * @phpstan-param array<array-key,mixed> $info
     */
    private function deliverCompletion(array $info): void
    {
        $handle = $info['handle'];
        if (!$handle instanceof CurlHandle) {
            return;
        }

        $errno = $info['result'];
        if (!is_int($errno)) {
            return;
        }

        $id = spl_object_id($handle);
        if (!isset($this->callbacks[$id])) {
            return;
        }

        $callback = $this->callbacks[$id];
        $lines = $this->headerLines[$id];
        $error = '';
        if ($errno !== \CURLE_OK) {
            $detail = curl_error($handle);
            if ($detail === '') {
                $detail = curl_strerror($errno) ?? 'unknown error';
            }
            $error = sprintf('cURL error %d: %s', $errno, $detail);
        }
        $body = curl_multi_getcontent($handle) ?? '';
        $this->remove($handle);
        $callback($errno, $error, $lines, $body);
    }
}
