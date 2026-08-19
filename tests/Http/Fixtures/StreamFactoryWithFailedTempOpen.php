<?php

declare(strict_types=1);

namespace Manychois\PhpStrongTests\Http\Fixtures;

use Manychois\PhpStrong\Http\StreamFactory;
use Override;

final class StreamFactoryWithFailedTempOpen extends StreamFactory
{
    /**
     * @return resource|false
     */
    #[Override]
    protected function createTempStreamResource()
    {
        return false;
    }
}
