<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\Http;

use BadMethodCallException;
use Override;
use Psr\Http\Client\ClientInterface as IClient;
use Psr\Http\Message\RequestInterface as IRequest;
use Psr\Http\Message\ResponseInterface as IResponse;

/**
 * Wraps any PSR-18 client so that cookies a remote host sets are remembered and sent back on later requests.
 *
 * Cookies become invisible to the calling code: the request goes out carrying whatever the {@see CookieStore}
 * holds for its URI, and whatever the response sets is absorbed before it is returned.
 */
final class CookieAwareClient implements IClient
{
    /**
     * Initializes a new instance of the CookieAwareClient class.
     *
     * @param IClient $inner The client which actually sends the request.
     * @param CookieStore $store The store which remembers the cookies.
     */
    public function __construct(
        private readonly IClient $inner,
        private readonly CookieStore $store,
    ) {
    }

    /**
     * Dispatches the request without waiting for the response, attaching the stored cookies on the way out and
     * absorbing whatever the response sets once the transfer settles.
     *
     * This method is an extension beyond PSR-18 and is available only when the wrapped client provides it.
     *
     * With several transfers in flight, cookies are absorbed in completion order, which cURL decides. Should two
     * concurrent responses set the same cookie, the last one to settle wins; making that deterministic would mean
     * serialising the requests, which is the opposite of what this method is for.
     *
     * @param IRequest $request The request to send.
     *
     * @return PendingRequest The handle for collecting the response.
     *
     * @throws BadMethodCallException if the wrapped client cannot send requests asynchronously.
     */
    public function sendAsync(IRequest $request): PendingRequest
    {
        if (!$this->inner instanceof Client) {
            throw new BadMethodCallException('The wrapped client does not support asynchronous requests.');
        }

        $prepared = $this->store->attachTo($request);
        $pending = $this->inner->sendAsync($prepared);
        $store = $this->store;
        $uri = $prepared->getUri();
        $pending->onResponse(static function (Response $response) use ($store, $uri): void {
            $store->absorb($response, $uri);
        });

        return $pending;
    }

    #region implements IClient

    /**
     * @inheritDoc
     */
    #[Override]
    public function sendRequest(IRequest $request): IResponse
    {
        $prepared = $this->store->attachTo($request);
        $response = $this->inner->sendRequest($prepared);
        $this->store->absorb($response, $prepared->getUri());

        return $response;
    }

    #endregion implements IClient
}
