<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\Http;

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
