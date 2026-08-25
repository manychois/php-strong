<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\DependencyInjection;

use Closure;
use Manychois\PhpStrong\DependencyInjection\Internal\Autowirer;
use Manychois\PhpStrong\DependencyInjection\Internal\Definition;
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

    private readonly Autowirer $autowirer;

    /**
     * @var list<array{string, Closure}>
     *
     * @phpstan-var list<array{class-string, Closure(object, IContainer):void}>
     */
    private readonly array $awares;

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
     * @param list<array{string, Closure}> $awares Type-based configurers applied to produced objects.
     *
     * @internal Use `ContainerBuilder::build()`.
     *
     * @phpstan-param list<array{class-string, Closure(object, IContainer):void}> $awares
     */
    public function __construct(array $definitions, ?IContainer $parent = null, array $awares = [])
    {
        $this->definitions = $definitions;
        $this->parent = $parent;
        $this->autowirer = new Autowirer();
        $this->awares = $awares;
    }

    /**
     * Resolves a service and asserts it is an instance of `$class`, so callers get a precise static type.
     *
     * @template T of object
     *
     * @param string $class The service identifier, which must also be the expected class or interface.
     *
     * @return object The resolved instance.
     *
     * @throws NotFoundException if the identifier is not registered.
     * @throws ContainerException if resolution fails or the service is not an instance of `$class`.
     *
     * @phpstan-param class-string<T> $class
     *
     * @phpstan-return T
     */
    public function getInstance(string $class): object
    {
        $value = $this->get($class);
        if (!$value instanceof $class) {
            throw new ContainerException(sprintf('Service "%s" is not an instance of %s.', $class, $class));
        }

        return $value;
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
            $value = $definition->source instanceof Closure
                ? ($definition->source)($this)
                : $this->autowirer->instantiate($definition->source, $this);
            if ($definition->configurable && is_object($value)) {
                foreach ($this->awares as [$type, $configure]) {
                    if ($value instanceof $type) {
                        $configure($value, $this);
                    }
                }
            }
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
