<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\Http;

use Psr\Http\Message\RequestInterface as IRequest;
use Psr\Http\Message\ResponseInterface as IResponse;
use RuntimeException;

/**
 * Sends a response to whatever is listening: a SAPI in production, a recorder in a functional test.
 *
 * One method, and one reason to exist: an application under test needs to capture a response without reaching into
 * PHP's global header state.
 */
interface ResponseEmitterInterface
{
    /**
     * Sends the response: status line, then headers, then body.
     *
     * @param IResponse $response The response to send.
     * @param ?IRequest $request The request being answered, read only to detect `HEAD`, which must receive no body.
     * Pass `null` when the request is known not to be a `HEAD` request.
     *
     * @throws RuntimeException if output has already started, since the headers can no longer be sent, or if
     * the body stream fails while being read, after part of the response has already been written.
     */
    public function emit(IResponse $response, ?IRequest $request = null): void;
}
