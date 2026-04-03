<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\Web;

use Override;
use Psr\Http\Message\ResponseFactoryInterface as IResponseFactory;
use Psr\Http\Message\ResponseInterface as IResponse;

/**
 * PSR-17 factory that builds {@see Response} instances.
 */
class ResponseFactory implements IResponseFactory
{
    #region implements IResponseFactory

    /**
     * @inheritDoc
     */
    #[Override]
    public function createResponse(int $code = 200, string $reasonPhrase = ''): IResponse
    {
        return new Response(
            statusCode: $code,
            reasonPhrase: $reasonPhrase,
        );
    }

    #endregion implements IResponseFactory
}
