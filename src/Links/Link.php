<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\Links;

use Manychois\PhpStrong\Links\Internal\UriTemplate;
use Override;
use Psr\Link\EvolvableLinkInterface as IEvolvableLink;
use Stringable;

/**
 * An immutable PSR-13 link.
 *
 * Whether the link is templated is derived from its href and cannot be set independently.
 */
final class Link implements IEvolvableLink
{
    private string $href;
    private bool $templated;
    /**
     * @var list<string>
     */
    private array $rels = [];
    /**
     * @var array<string, string|int|float|bool|list<string>>
     */
    private array $attributes = [];

    /**
     * Initializes a link.
     *
     * @param string|Stringable $href The link target; a `Stringable` is cast to `string` immediately.
     * @param array $rels The relation types; duplicates are collapsed, keeping first-seen order.
     * @param array $attributes The attributes, keyed by name.
     *
     * @throws InvalidArgumentException if a relation type or an attribute is malformed.
     *
     * @phpstan-param list<string> $rels
     * @phpstan-param array<string, string|Stringable|int|float|bool|array<mixed>> $attributes
     */
    public function __construct(string|Stringable $href = '', array $rels = [], array $attributes = [])
    {
        $this->href = (string) $href;
        $this->templated = UriTemplate::isTemplate($this->href);

        foreach ($rels as $rel) {
            self::assertRel($rel);
            if (in_array($rel, $this->rels, true)) {
                continue;
            }

            $this->rels[] = $rel;
        }

        foreach ($attributes as $name => $value) {
            self::assertAttributeName($name);
            $this->attributes[$name] = self::normalizeAttributeValue($name, $value);
        }
    }

    /**
     * @throws InvalidArgumentException if the name is empty or blank.
     */
    private static function assertAttributeName(string $attribute): void
    {
        if (trim($attribute) === '') {
            throw new InvalidArgumentException('An attribute name must not be empty.');
        }
    }

    /**
     * @throws InvalidArgumentException if the relation type is empty, blank, or contains whitespace.
     */
    private static function assertRel(string $rel): void
    {
        if (trim($rel) === '') {
            throw new InvalidArgumentException('A link relation type must not be empty.');
        }

        if (preg_match('/\s/', $rel) === 1) {
            throw new InvalidArgumentException(
                sprintf('A link relation type must not contain whitespace, got "%s".', $rel)
            );
        }
    }

    /**
     * @return string|int|float|bool|list<string>
     *
     * @throws InvalidArgumentException if the value is not a primitive, a `Stringable`, or a list of strings.
     *
     * @phpstan-param string|Stringable|int|float|bool|array<mixed> $value
     */
    private static function normalizeAttributeValue(
        string $attribute,
        string|Stringable|int|float|bool|array $value
    ): string|int|float|bool|array {
        if ($value instanceof Stringable) {
            return (string) $value;
        }

        if (!is_array($value)) {
            return $value;
        }

        if (!array_is_list($value)) {
            throw new InvalidArgumentException(
                sprintf('Attribute "%s" must be a list of strings, got a keyed array.', $attribute)
            );
        }

        $strings = [];
        foreach ($value as $item) {
            if (!is_string($item)) {
                throw new InvalidArgumentException(
                    sprintf(
                        'Attribute "%s" must be a list of strings, got %s in the list.',
                        $attribute,
                        get_debug_type($item)
                    )
                );
            }

            $strings[] = $item;
        }

        return $strings;
    }

    #region implements IEvolvableLink

    /**
     * @inheritDoc
     *
     * @return array<string, string|int|float|bool|list<string>>
     */
    #[Override]
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function getHref(): string
    {
        return $this->href;
    }

    /**
     * @inheritDoc
     *
     * @return list<string>
     */
    #[Override]
    public function getRels(): array
    {
        return $this->rels;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function isTemplated(): bool
    {
        return $this->templated;
    }

    /**
     * @inheritDoc
     *
     * @throws InvalidArgumentException if the name is blank or the value is not a permitted type.
     *
     * @phpstan-param string|Stringable|int|float|bool|array<mixed> $value
     */
    #[Override]
    public function withAttribute(string $attribute, string|Stringable|int|float|bool|array $value): static
    {
        self::assertAttributeName($attribute);

        $clone = clone $this;
        $clone->attributes[$attribute] = self::normalizeAttributeValue($attribute, $value);

        return $clone;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function withHref(string|Stringable $href): static
    {
        $clone = clone $this;
        $clone->href = (string) $href;
        $clone->templated = UriTemplate::isTemplate($clone->href);

        return $clone;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function withoutAttribute(string $attribute): static
    {
        if (!array_key_exists($attribute, $this->attributes)) {
            return $this;
        }

        $clone = clone $this;
        unset($clone->attributes[$attribute]);

        return $clone;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function withoutRel(string $rel): static
    {
        $index = array_search($rel, $this->rels, true);
        if ($index === false) {
            return $this;
        }

        $rels = $this->rels;
        unset($rels[$index]);

        $clone = clone $this;
        $clone->rels = array_values($rels);

        return $clone;
    }

    /**
     * @inheritDoc
     *
     * @throws InvalidArgumentException if the relation type is blank or contains whitespace.
     */
    #[Override]
    public function withRel(string $rel): static
    {
        self::assertRel($rel);
        if (in_array($rel, $this->rels, true)) {
            return $this;
        }

        $clone = clone $this;
        $clone->rels[] = $rel;

        return $clone;
    }

    #endregion implements IEvolvableLink
}
