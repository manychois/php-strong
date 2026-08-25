<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\Http;

use InvalidArgumentException;
use Manychois\PhpStrong\Http\ResponseEmitterInterface as IResponseEmitter;
use Override;
use Psr\Http\Message\RequestInterface as IRequest;
use Psr\Http\Message\ResponseInterface as IResponse;
use RuntimeException;

/**
 * Sends a PSR-7 response to the SAPI: status line, then headers, then body.
 *
 * The body is written in fixed-size chunks, so streaming a file of any size never approaches `memory_limit`.
 *
 * This class deliberately leaves output buffers alone. A buffer it did not open may hold output the caller intends
 * to keep, and a library is in no position to judge; a buffer which has already flushed surfaces instead as the
 * exception thrown before anything is written.
 */
final class SapiEmitter implements IResponseEmitter
{
    /**
     * Initializes a new instance of the SapiEmitter class.
     *
     * @param int $chunkSize The number of bytes read from the body stream per write. Must be at least 1.
     *
     * @throws InvalidArgumentException if the chunk size is below 1.
     *
     * @phpstan-param positive-int $chunkSize
     */
    public function __construct(private readonly int $chunkSize = 8_388_608)
    {
        if ($chunkSize < 1) {
            throw new InvalidArgumentException(sprintf(
                'The chunk size must be at least 1 byte; got %d.',
                $chunkSize,
            ));
        }
    }

    #region implements IResponseEmitter

    /**
     * @inheritDoc
     */
    #[Override]
    public function emit(IResponse $response, ?IRequest $request = null): void
    {
        $this->assertHeadersNotSent();
        $this->emitStatusLine($response);
        $this->emitHeaders($response);
        if (!$this->hasBody($response, $request)) {
            return;
        }
    }

    #endregion implements IResponseEmitter

    /**
     * Throws unless the headers can still be sent.
     */
    private function assertHeadersNotSent(): void
    {
        $file = '';
        $line = 0;
        if (!headers_sent($file, $line)) {
            return;
        }

        throw new RuntimeException(sprintf(
            'Cannot emit the response: output already started at %s:%d.',
            $file === '' ? 'an unknown file' : $file,
            $line,
        ));
    }

    /**
     * Sends every header of the response, merging rather than replacing the cookies PHP has already queued.
     *
     * `Set-Cookie` is emitted with `replace` false for every value, the first included. Replacing on the first value
     * would delete any `Set-Cookie` PHP itself has queued — most importantly the session cookie `session_start()`
     * writes inside {@see NativeSession}, which never appears in the response object and so cannot be recovered from
     * it. This method is the single point where PHP's own queued headers and the response's headers meet.
     *
     * @param IResponse $response The response whose headers are sent.
     */
    private function emitHeaders(IResponse $response): void
    {
        foreach ($response->getHeaders() as $name => $values) {
            $isSetCookie = strcasecmp($name, 'Set-Cookie') === 0;
            $first = true;
            foreach ($values as $value) {
                header($name . ': ' . $value, $first && !$isSetCookie);
                $first = false;
            }
        }
    }

    /**
     * Sends the status line, which also fixes the status code for every header sent after it.
     *
     * @param IResponse $response The response whose status is sent.
     */
    private function emitStatusLine(IResponse $response): void
    {
        $code = $response->getStatusCode();
        $reason = $response->getReasonPhrase();

        header(
            sprintf(
                'HTTP/%s %d%s',
                $response->getProtocolVersion(),
                $code,
                $reason === '' ? '' : ' ' . $reason,
            ),
            true,
            $code,
        );
    }

    /**
     * Checks whether HTTP allows this response to carry a body.
     *
     * RFC 9110 forbids a body on any 1xx, on 204, on 304, and on any response to a `HEAD` request. A body on a 304
     * desynchronises the connection for the next request on it, so the failure surfaces as a corrupted *later*
     * response — which is why this is not left to the caller.
     *
     * @param IResponse $response The response about to be sent.
     * @param ?IRequest $request The request being answered, or null when it is known not to be a `HEAD` request.
     *
     * @return bool True if the body may be sent; false if it must be suppressed.
     */
    private function hasBody(IResponse $response, ?IRequest $request): bool
    {
        $code = $response->getStatusCode();
        if ($code === 204 || $code === 304 || ($code >= 100 && $code < 200)) {
            return false;
        }

        return $request === null || strtoupper($request->getMethod()) !== 'HEAD';
    }
}
