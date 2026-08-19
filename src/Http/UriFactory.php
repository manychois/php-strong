<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\Http;

use InvalidArgumentException;
use Override;
use Psr\Http\Message\UriFactoryInterface as IUriFactory;
use Throwable;

/**
 * PSR-17 factory that builds {@see Uri} instances.
 */
class UriFactory implements IUriFactory
{
    #region implements IUriFactory

    /**
     * @inheritDoc
     */
    #[Override]
    public function createUri(string $uri = ''): Uri
    {
        try {
            return Uri::fromString($uri);
        } catch (Throwable $ex) {
            throw new InvalidArgumentException($ex->getMessage(), previous: $ex);
        }
    }

    #endregion implements IUriFactory
}
