<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\Http;

use Override;
use Psr\Http\Client\RequestExceptionInterface as IRequestException;
use Psr\Http\Message\RequestInterface as IRequest;
use Throwable;

/**
 * Thrown when a request cannot be sent because it is missing data or is otherwise malformed.
 */
class RequestException extends ClientException implements IRequestException
{
    /**
     * @param string $message The exception message.
     * @param IRequest $request The request that failed.
     * @param ?Throwable $previous The previous throwable, if any.
     */
    public function __construct(
        string $message,
        private readonly IRequest $request,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    #region implements IRequestException

    /**
     * @inheritDoc
     */
    #[Override]
    public function getRequest(): IRequest
    {
        return $this->request;
    }

    #endregion implements IRequestException
}
