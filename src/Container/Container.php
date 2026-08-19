<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\Container;

use Manychois\PhpStrong\Container\Internal\Definition;
use Override;
use Psr\Container\ContainerInterface as IContainer;
use Throwable;

/**
 * PSR-11 container that lazily resolves services from closures registered via `ContainerBuilder`.
 * Definitions cannot change after construction; shared services are cached on first resolution. Identifiers not
 * registered here are delegated to the parent container, if any.
 */
class Container implements IContainer
{
    /**
     * @var array<string, Definition>
     */
    private readonly array $definitions;

    private readonly ?IContainer $parent;

    /**
     * @var array<string, mixed>
     */
    private array $shared = [];

    /**
     * Identifiers currently being resolved, in call order; used to detect circular dependencies.
     *
     * @var list<string>
     */
    private array $resolving = [];

    /**
     * @param array<string, Definition> $definitions Service definitions keyed by identifier.
     * @param ?IContainer $parent Container consulted for identifiers not in `$definitions`.
     *
     * @internal Use `ContainerBuilder::build()`.
     */
    public function __construct(array $definitions, ?IContainer $parent = null)
    {
        $this->definitions = $definitions;
        $this->parent = $parent;
    }

    #region implements IContainer

    /**
     * @inheritDoc
     *
     * @throws NotFoundException if the identifier is not registered.
     * @throws ContainerException if a circular dependency is detected or the factory throws.
     */
    #[Override]
    public function get(string $id): mixed
    {
        if (array_key_exists($id, $this->shared)) {
            return $this->shared[$id];
        }
        $definition = $this->definitions[$id] ?? null;
        if ($definition === null) {
            if ($this->parent?->has($id) === true) {
                return $this->parent->get($id);
            }

            throw new NotFoundException(sprintf('Service "%s" is not registered.', $id));
        }
        if (in_array($id, $this->resolving, true)) {
            throw new ContainerException(
                sprintf('Circular dependency detected: %s -> %s.', implode(' -> ', $this->resolving), $id),
            );
        }

        $this->resolving[] = $id;
        try {
            $value = ($definition->factory)($this);
        } catch (ContainerException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw new ContainerException(
                sprintf('Failed to resolve service "%s": %s', $id, $e->getMessage()),
                0,
                $e,
            );
        } finally {
            array_pop($this->resolving);
        }

        if ($definition->shared) {
            $this->shared[$id] = $value;
        }

        return $value;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function has(string $id): bool
    {
        return array_key_exists($id, $this->definitions) || $this->parent?->has($id) === true;
    }

    #endregion implements IContainer
}
