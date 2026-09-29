<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\Http;

use Override;
use Psr\Http\Message\RequestFactoryInterface as IRequestFactory;
use Psr\Http\Message\ServerRequestFactoryInterface as IServerRequestFactory;
use Psr\Http\Message\UriInterface as IUri;

/**
 * PSR-17 factory for outbound {@see Request} and incoming {@see ServerRequest} messages.
 */
class RequestFactory implements IRequestFactory, IServerRequestFactory
{
    #region implements IRequestFactory

    /**
     * @inheritDoc
     *
     * @param IUri|string $uri
     */
    #[Override]
    public function createRequest(string $method, $uri): Request
    {
        return new Request(
            method: $method,
            uri: $uri,
        );
    }

    #endregion implements IRequestFactory

    #region implements IServerRequestFactory

    /**
     * @inheritDoc
     *
     * Per PSR-17, the method and URI are not derived from `serverParams`; that array is stored as returned by
     * {@see ServerRequest::getServerParams()}, minus any entry whose key is not a string (as
     * {@see ServerRequest::fromGlobals()} does for `$_SERVER`).
     *
     * @param IUri|string $uri
     * @param array<mixed> $serverParams
     */
    #[Override]
    public function createServerRequest(string $method, $uri, array $serverParams = []): ServerRequest
    {
        return new ServerRequest(
            method: $method,
            uri: $uri,
            headers: [],
            body: (new StreamFactory())->createStream(),
            protocolVersion: '1.1',
            requestTarget: null,
            serverParams: self::stringKeyed($serverParams),
            cookieParams: [],
            queryParams: [],
            uploadedFiles: [],
            parsedBody: null,
            attributes: [],
        );
    }

    #endregion implements IServerRequestFactory

    /**
     * @param array<mixed> $serverParams
     *
     * @return array<string,mixed> The entries whose key is a string.
     */
    private static function stringKeyed(array $serverParams): array
    {
        $result = [];
        foreach ($serverParams as $name => $value) {
            if (is_string($name)) {
                $result[$name] = $value;
            }
        }

        return $result;
    }
}
