<?php

declare(strict_types=1);

namespace Manychois\PhpStrongTests\Collections;

use BadMethodCallException;
use Manychois\PhpStrong\Collections\DuplicationPolicy;
use Manychois\PhpStrong\Collections\ReadonlyMap;
use Manychois\PhpStrong\Collections\StringMap;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for ReadonlyMap.
 */
final class ReadonlyMapTest extends TestCase
{
    #[Test]
    public function arrayAccess_offsetExists_delegatesToInner(): void
    {
        $inner = new StringMap(['a' => '1']);
        $ro = new ReadonlyMap($inner);
        self::assertTrue($ro->offsetExists('a'));
        self::assertFalse($ro->offsetExists('missing'));
    }

    #[Test]
    public function arrayAccess_offsetGet_delegatesToInner(): void
    {
        $inner = new StringMap(['x' => 'y']);
        $ro = new ReadonlyMap($inner);
        self::assertSame('y', $ro->offsetGet('x'));
    }

    #[Test]
    public function arrayAccess_offsetSet_throwsBadMethodCall(): void
    {
        $ro = new ReadonlyMap(new StringMap(['k' => 'v']));
        $this->expectException(BadMethodCallException::class);
        $this->expectExceptionMessage('Cannot modify a readonly map.');
        $ro->offsetSet('k', 'new');
    }

    #[Test]
    public function arrayAccess_offsetUnset_throwsBadMethodCall(): void
    {
        $ro = new ReadonlyMap(new StringMap(['k' => 'v']));
        $this->expectException(BadMethodCallException::class);
        $this->expectExceptionMessage('Cannot modify a readonly map.');
        $ro->offsetUnset('k');
    }

    #[Test]
    public function asArray_delegatesToInner(): void
    {
        $data = ['a' => 'A', 'b' => 'B'];
        $ro = new ReadonlyMap(new StringMap($data));
        self::assertSame($data, $ro->asArray());
    }

    #[Test]
    public function count_delegatesToInner(): void
    {
        $ro = new ReadonlyMap(new StringMap(['x' => '1', 'y' => '2']));
        self::assertCount(2, $ro);
    }

    #[Test]
    public function duplicationPolicy_delegatesToInner(): void
    {
        $inner = new StringMap([], DuplicationPolicy::Ignore);
        $ro = new ReadonlyMap($inner);
        self::assertSame(DuplicationPolicy::Ignore, $ro->duplicationPolicy);
    }

    #[Test]
    public function entries_delegatesToInner(): void
    {
        $ro = new ReadonlyMap(new StringMap(['k' => 'v']));
        $entries = $ro->entries()->asArray();
        self::assertCount(1, $entries);
        self::assertSame('k', $entries[0]->key);
        self::assertSame('v', $entries[0]->value);
    }

    #[Test]
    public function flip_delegatesToInner(): void
    {
        $ro = new ReadonlyMap(new StringMap(['a' => '1', 'b' => '2']));
        self::assertSame(['1' => 'a', '2' => 'b'], iterator_to_array($ro->flip()));
    }

    #[Test]
    public function foreach_usesInnerIterator(): void
    {
        $ro = new ReadonlyMap(new StringMap(['a' => 'A']));
        $seen = [];
        foreach ($ro as $k => $v) {
            $seen[$k] = $v;
        }
        self::assertSame(['a' => 'A'], $seen);
    }

    #[Test]
    public function get_getIterator_delegatesToInner(): void
    {
        $ro = new ReadonlyMap(new StringMap(['q' => 'r']));
        self::assertSame('r', $ro->get('q'));
        self::assertSame(['q' => 'r'], iterator_to_array($ro->getIterator()));
    }

    #[Test]
    public function has_andNullGet_delegatesToInner(): void
    {
        $ro = new ReadonlyMap(new StringMap(['only' => 'one']));
        self::assertTrue($ro->has('only'));
        self::assertFalse($ro->has('absent'));
        self::assertSame('one', $ro->nullGet('only'));
        self::assertNull($ro->nullGet('absent'));
    }

    #[Test]
    public function innerMutation_isVisibleThroughReadonlyView(): void
    {
        $inner = new StringMap(['a' => '1']);
        $ro = new ReadonlyMap($inner);
        $inner->add('b', '2');
        self::assertSame(['a' => '1', 'b' => '2'], $ro->asArray());
        self::assertCount(2, $ro);
    }

    #[Test]
    public function keys_values_delegatesToInner(): void
    {
        $ro = new ReadonlyMap(new StringMap(['a' => 'x', 'b' => 'y']));
        self::assertSame(['a', 'b'], $ro->keys()->asArray());
        self::assertSame(['x', 'y'], $ro->values()->asArray());
    }
}
