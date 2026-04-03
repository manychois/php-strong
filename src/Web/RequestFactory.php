<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\Web;

use Override;
use Psr\Http\Message\RequestFactoryInterface as IRequestFactory;
use Psr\Http\Message\RequestInterface as IRequest;
use Psr\Http\Message\ServerRequestFactoryInterface as IServerRequestFactory;
use Psr\Http\Message\ServerRequestInterface as IServerRequest;
use Psr\Http\Message\UriInterface as IUri;

/**
 * PSR-17 factory for outbound {@see OutRequest} and incoming {@see InRequest} messages.
 */
class RequestFactory implements IRequestFactory, IServerRequestFactory
{
    /**
     * Asserts that the array has string keys.
     *
     * @param array<mixed> $serverParams
     *
     * @return bool
     *
     * @phpstan-assert array<string, mixed> $serverParams
     */
    private static function assertStringKeyArray(array $serverParams): bool
    {
        foreach ($serverParams as $name => $value) {
            if (!is_string($name)) {
                return false;
            }
        }
        return true;
    }

    #region implements IRequestFactory

    /**
     * @inheritDoc
     *
     * @param IUri|string $uri
     */
    #[Override]
    public function createRequest(string $method, $uri): IRequest
    {
        return new OutRequest(
            method: $method,
            uri: $uri,
        );
    }

    #endregion implements IRequestFactory

    #region implements IServerRequestFactory

    /**
     * @inheritDoc
     *
     * Per PSR-17, the method and URI are not derived from `serverParams`; that array is stored
     * as returned by {@see InRequest::getServerParams()}.
     *
     * @param IUri|string $uri
     * @param array<mixed> $serverParams
     */
    #[Override]
    public function createServerRequest(string $method, $uri, array $serverParams = []): IServerRequest
    {
        self::assertStringKeyArray($serverParams);
        return new InRequest(
            method: $method,
            uri: $uri,
            headers: [],
            body: (new StreamFactory())->createStream(),
            protocolVersion: '1.1',
            requestTarget: null,
            serverParams: $serverParams,
            cookieParams: [],
            queryParams: [],
            uploadedFiles: [],
            parsedBody: null,
            attributes: [],
        );
    }

    #endregion implements IServerRequestFactory
}
