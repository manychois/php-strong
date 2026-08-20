<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\Http;

use InvalidArgumentException;
use Manychois\PhpStrong\Http\Internal\CurlTransport;
use Manychois\PhpStrong\Http\Internal\TransportInterface as ITransport;
use Override;
use Psr\Http\Client\ClientInterface as IClient;
use Psr\Http\Message\RequestInterface as IRequest;

/**
 * A PSR-18 HTTP client backed by cURL.
 * Responses are returned regardless of their status code; redirects are not followed.
 */
class Client implements IClient
{
    private readonly ITransport $transport;

    /**
     * @param float $timeout The connection and response timeout, in seconds.
     * @param ?ITransport $transport The transport to send requests with; defaults to cURL.
     */
    public function __construct(
        private readonly float $timeout = 30.0,
        ?ITransport $transport = null,
    ) {
        if ($timeout <= 0) {
            throw new InvalidArgumentException(sprintf('Timeout must be greater than 0, got %f.', $timeout));
        }

        $this->transport = $transport ?? new CurlTransport();
    }

    #region implements IClient

    /**
     * Sends a PSR-7 request and returns the response.
     *
     * @param IRequest $request The request to send.
     *
     * @return Response The response received.
     *
     * @throws RequestException if the request URI has an unsupported scheme or no host.
     * @throws NetworkException if the request cannot be completed due to a network failure.
     */
    #[Override]
    public function sendRequest(IRequest $request): Response
    {
        $uri = $request->getUri();
        $scheme = $uri->getScheme();
        if (!in_array($scheme, ['http', 'https'], true)) {
            throw new RequestException(
                sprintf('Unsupported URI scheme "%s"; expected "http" or "https".', $scheme),
                $request,
            );
        }
        if ($uri->getHost() === '') {
            throw new RequestException('Request URI must include a host.', $request);
        }

        $raw = $this->transport->send($request, $this->timeout);

        return new Response($raw->statusCode, $raw->reasonPhrase, $raw->headers, $raw->body, $raw->protocolVersion);
    }

    #endregion implements IClient
}
