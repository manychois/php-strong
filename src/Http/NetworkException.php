<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\Http;

use Override;
use Psr\Http\Client\NetworkExceptionInterface as INetworkException;
use Psr\Http\Message\RequestInterface as IRequest;
use Throwable;

/**
 * Thrown when a request cannot be completed because of a network failure,
 * e.g. the target host cannot be resolved or the connection timed out.
 */
class NetworkException extends ClientException implements INetworkException
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

    #region implements INetworkException

    /**
     * @inheritDoc
     */
    #[Override]
    public function getRequest(): IRequest
    {
        return $this->request;
    }

    #endregion implements INetworkException
}
