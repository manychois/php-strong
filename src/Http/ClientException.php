<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\Http;

use Psr\Http\Client\ClientExceptionInterface as IClientException;
use RuntimeException;

/**
 * Base exception for PSR-18 HTTP client errors.
 */
class ClientException extends RuntimeException implements IClientException
{
}
