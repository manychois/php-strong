<?php

declare(strict_types=1);

namespace Manychois\PhpStrongTests\Web\Fixtures;

use Manychois\PhpStrong\Web\StreamFactory;
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
