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
}
