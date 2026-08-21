<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\Links;

use Override;
use Psr\Link\EvolvableLinkProviderInterface as IEvolvableLinkProvider;
use Psr\Link\LinkInterface as ILink;

/**
 * An immutable PSR-13 collection of links.
 *
 * Membership is decided by object identity, as PSR-13 requires: two distinct links with equal state are two
 * members of the collection.
 */
final class LinkProvider implements IEvolvableLinkProvider
{
    /**
     * @var list<ILink>
     */
    private array $links = [];

    /**
     * Initializes a provider holding the given links, in the order given.
     *
     * @param ILink ...$links The links to hold; entries identical to one already held are collapsed.
     */
    public function __construct(ILink ...$links)
    {
        foreach ($links as $link) {
            if (in_array($link, $this->links, true)) {
                continue;
            }

            $this->links[] = $link;
        }
    }

    #region implements IEvolvableLinkProvider

    /**
     * @inheritDoc
     *
     * @return list<ILink>
     */
    #[Override]
    public function getLinks(): array
    {
        return $this->links;
    }

    /**
     * @inheritDoc
     *
     * @return list<ILink>
     */
    #[Override]
    public function getLinksByRel(string $rel): array
    {
        $matches = [];
        foreach ($this->links as $link) {
            if (!in_array($rel, $link->getRels(), true)) {
                continue;
            }

            $matches[] = $link;
        }

        return $matches;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function withLink(ILink $link): static
    {
        if (in_array($link, $this->links, true)) {
            return $this;
        }

        $clone = clone $this;
        $clone->links[] = $link;

        return $clone;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function withoutLink(ILink $link): static
    {
        $index = array_search($link, $this->links, true);
        if ($index === false) {
            return $this;
        }

        $links = $this->links;
        unset($links[$index]);

        $clone = clone $this;
        $clone->links = array_values($links);

        return $clone;
    }

    #endregion implements IEvolvableLinkProvider
}
