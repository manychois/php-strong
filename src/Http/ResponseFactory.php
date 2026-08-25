<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\Http;

use Override;
use Psr\Http\Message\ResponseFactoryInterface as IResponseFactory;

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
    public function createResponse(int $code = 200, string $reasonPhrase = ''): Response
    {
        return new Response(
            statusCode: $code,
            reasonPhrase: $reasonPhrase,
        );
    }

    #endregion implements IResponseFactory
}
