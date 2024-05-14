<?php

declare(strict_types=1);

namespace Manychois\PhpStrongTests\Collections;

use Manychois\PhpStrong\Collections\ArrayList;
use PHPUnit\Framework\TestCase;

final class ArrayListTest extends TestCase
{
    public function testCount(): void
    {
        $list = new ArrayList([1, 3, 5, 7]);
        self::assertCount(4, $list);
    }

    public function testGetIterator(): void
    {
        /**
         * @var ArrayList<int> $list
         */
        $list = new ArrayList([1, 3, 5, 7]);
        foreach ($list as $index => $value) {
            self::assertSame($index * 2 + 1, $value);
        }
    }
}
