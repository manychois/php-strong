<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\Http\Internal;

use Manychois\PhpStrong\Http\NetworkException;
use Psr\Http\Message\RequestInterface as IRequest;

/**
 * Sends HTTP requests over the wire on behalf of the client.
 *
 * @internal
 */
interface TransportInterface
{
    /**
     * Sends the request and returns the raw response.
     *
     * @param IRequest $request The request to send.
     * @param float $timeout The connection and response timeout, in seconds.
     *
     * @return RawResponse The raw response received.
     *
     * @throws NetworkException if the request cannot be completed due to a network failure.
     */
    public function send(IRequest $request, float $timeout): RawResponse;
}
