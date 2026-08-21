<?php

declare(strict_types=1);

namespace Manychois\PhpStrongTests\Links;

use Manychois\PhpStrong\Links\Link;
use Manychois\PhpStrong\Links\LinkProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class LinkProviderTest extends TestCase
{
    #[Test]
    public function construct_withoutArgumentsYieldsAnEmptyProvider(): void
    {
        self::assertSame([], (new LinkProvider())->getLinks());
    }

    #[Test]
    public function getLinks_keepsInsertionOrder(): void
    {
        $first = new Link('/a', ['next']);
        $second = new Link('/b', ['prev']);

        self::assertSame([$first, $second], (new LinkProvider($first, $second))->getLinks());
    }

    #[Test]
    public function construct_collapsesIdenticalInstances(): void
    {
        $link = new Link('/a');

        self::assertSame([$link], (new LinkProvider($link, $link))->getLinks());
    }

    #[Test]
    public function construct_keepsDistinctInstancesWithEqualState(): void
    {
        $first = new Link('/a', ['next']);
        $second = new Link('/a', ['next']);

        self::assertSame([$first, $second], (new LinkProvider($first, $second))->getLinks());
    }

    #[Test]
    public function getLinksByRel_returnsMatchingLinksInInsertionOrder(): void
    {
        $first = new Link('/a', ['next', 'alternate']);
        $second = new Link('/b', ['prev']);
        $third = new Link('/c', ['alternate']);
        $provider = new LinkProvider($first, $second, $third);

        self::assertSame([$first, $third], $provider->getLinksByRel('alternate'));
    }

    #[Test]
    public function getLinksByRel_returnsAnEmptyListWhenNothingMatches(): void
    {
        $provider = new LinkProvider(new Link('/a', ['next']));

        self::assertSame([], $provider->getLinksByRel('prev'));
    }

    #[Test]
    public function withLink_appendsAndLeavesTheOriginalUntouched(): void
    {
        $first = new Link('/a');
        $second = new Link('/b');
        $provider = new LinkProvider($first);

        $evolved = $provider->withLink($second);

        self::assertNotSame($provider, $evolved);
        self::assertSame([$first, $second], $evolved->getLinks());
        self::assertSame([$first], $provider->getLinks());
    }

    #[Test]
    public function withLink_isIdempotentForAnIdenticalInstance(): void
    {
        $link = new Link('/a');
        $provider = new LinkProvider($link);

        self::assertSame([$link], $provider->withLink($link)->getLinks());
    }

    #[Test]
    public function withLink_addsAnEqualButDistinctInstance(): void
    {
        $first = new Link('/a');
        $second = new Link('/a');

        self::assertSame([$first, $second], (new LinkProvider($first))->withLink($second)->getLinks());
    }

    #[Test]
    public function withoutLink_removesByIdentityAndKeepsTheResultAList(): void
    {
        $first = new Link('/a');
        $second = new Link('/b');
        $third = new Link('/c');
        $provider = new LinkProvider($first, $second, $third);

        $evolved = $provider->withoutLink($second);

        self::assertSame([$first, $third], $evolved->getLinks());
        self::assertSame([$first, $second, $third], $provider->getLinks());
    }

    #[Test]
    public function withoutLink_leavesAnEqualButDistinctInstanceInPlace(): void
    {
        $kept = new Link('/a');
        $provider = new LinkProvider($kept);

        self::assertSame([$kept], $provider->withoutLink(new Link('/a'))->getLinks());
    }

    #[Test]
    public function withoutLink_returnsNormallyForAnAbsentLink(): void
    {
        $link = new Link('/a');
        $provider = new LinkProvider($link);

        self::assertSame([$link], $provider->withoutLink(new Link('/b'))->getLinks());
    }
}
