<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\Http;

use Manychois\PhpStrong\Http\Internal\CurlTransport;
use Manychois\PhpStrong\Http\Internal\TransportInterface as ITransport;
use Override;
use Psr\Http\Client\ClientInterface as IClient;
use Psr\Http\Message\RequestInterface as IRequest;

/**
 * A PSR-18 HTTP client backed by cURL.
 * Responses are returned regardless of their status code; redirects are not
 * followed unless enabled via {@see RequestOptions}.
 */
class Client implements IClient
{
    private readonly RequestOptions $options;
    private readonly ITransport $transport;

    /**
     * @param ?RequestOptions $options The options applied to every request; defaults to `new RequestOptions()`.
     * @param ?ITransport $transport The transport to send requests with; defaults to cURL.
     */
    public function __construct(
        ?RequestOptions $options = null,
        ?ITransport $transport = null,
    ) {
        $this->options = $options ?? new RequestOptions();
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
     * @throws RequestException if the request method is empty, the request URI has an
     * unsupported scheme or no host, or the request body cannot be read.
     * @throws NetworkException if the request cannot be completed due to a network failure.
     */
    #[Override]
    public function sendRequest(IRequest $request): Response
    {
        if ($request->getMethod() === '') {
            throw new RequestException('Request method must not be empty.', $request);
        }

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

        $raw = $this->transport->send($request, $this->options);

        return new Response($raw->statusCode, $raw->reasonPhrase, $raw->headers, $raw->body, $raw->protocolVersion);
    }

    #endregion implements IClient
}
