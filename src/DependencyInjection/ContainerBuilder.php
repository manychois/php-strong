<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\DependencyInjection;

use Closure;
use InvalidArgumentException;
use Manychois\PhpStrong\DependencyInjection\Internal\Autowirer;
use Manychois\PhpStrong\DependencyInjection\Internal\Definition;
use Psr\Container\ContainerInterface as IContainer;

/**
 * Collects service definitions and produces an immutable `Container`, optionally delegating unknown identifiers
 * to a parent container.
 */
class ContainerBuilder
{
    /**
     * @var array<string, Definition>
     */
    private array $definitions = [];

    /**
     * Type-based configurers applied to objects produced by the container, in registration order.
     *
     * @var list<array{string, Closure}>
     *
     * @phpstan-var list<array{class-string, Closure(object, IContainer):void}>
     */
    private array $awares = [];

    /**
     * Alias identifier → target identifier, validated in `build()`.
     *
     * @var array<string, string>
     */
    private array $aliases = [];

    /**
     * @param ?IContainer $parent Container consulted for identifiers not registered on this builder; lets a
     * short-lived (e.g. per-request) container delegate to a long-lived application container.
     * @param bool $autowire Whether `get()` builds unregistered, instantiable classes by reflection instead of
     * throwing `NotFoundException`; such instances are never cached.
     */
    public function __construct(
        private readonly ?IContainer $parent = null,
        private readonly bool $autowire = false,
    ) {
    }

    /**
     * Registers a configurer run on every object produced by this container that is an instance of `$type`, e.g. to
     * inject a logger into `LoggerAwareInterface` implementations. Configurers run after the definition produces the
     * object and before a singleton is cached, in registration order.
     *
     * @param string $type Class or interface name to match with `instanceof`.
     * @param Closure $configure Receives the object and the container; must configure the object in place.
     *
     * @return static This builder.
     *
     * @throws InvalidArgumentException if `$type` is not an existing class or interface.
     *
     * @phpstan-param class-string $type
     * @phpstan-param Closure(object, IContainer):void $configure
     */
    public function aware(string $type, Closure $configure): static
    {
        if (!class_exists($type) && !interface_exists($type)) {
            throw new InvalidArgumentException(sprintf('Type "%s" does not exist.', $type));
        }
        $this->awares[] = [$type, $configure];

        return $this;
    }

    /**
     * Registers `$id` as another name for `$target`: `get($id)` returns `get($target)`, so the alias follows the
     * target's lifetime. The target may be registered later on this builder, or live in the parent container.
     *
     * @param string $id The alias identifier, e.g. an interface name.
     * @param string $target The identifier to forward to, e.g. a concrete class name.
     *
     * @return static This builder.
     *
     * @throws InvalidArgumentException if the identifier is already registered or equals the target.
     */
    public function alias(string $id, string $target): static
    {
        if ($id === $target) {
            throw new InvalidArgumentException(sprintf('Alias "%s" cannot target itself.', $id));
        }
        $this->aliases[$id] = $target;

        return $this->register($id, new Definition(static fn (IContainer $c): mixed => $c->get($target), false, false));
    }

    /**
     * Registers a service built by reflection: constructor parameters are resolved from the container, from their
     * default values, or by recursively instantiating unregistered concrete classes.
     *
     * @param string $id The class to instantiate, also used as the service identifier.
     * @param bool $shared Whether to build once and cache (singleton) or build on every `get()`.
     *
     * @return static This builder.
     *
     * @throws InvalidArgumentException if the identifier is already registered, or the class does not exist or is
     * not instantiable.
     *
     * @phpstan-param class-string $id
     */
    public function autowire(string $id, bool $shared = true): static
    {
        Autowirer::assertInstantiable($id);

        return $this->register($id, new Definition($id, $shared));
    }

    /**
     * Creates the container from the definitions registered so far.
     * Later registrations on this builder do not affect the returned container.
     *
     * @return Container The built container.
     *
     * @throws InvalidArgumentException if an alias targets an identifier that is neither registered here nor in the
     * parent.
     */
    public function build(): Container
    {
        foreach ($this->aliases as $id => $target) {
            if (!array_key_exists($target, $this->definitions) && $this->parent?->has($target) !== true) {
                throw new InvalidArgumentException(
                    sprintf('Alias "%s" targets unregistered service "%s".', $id, $target),
                );
            }
        }

        return new Container($this->definitions, $this->parent, $this->awares, $this->autowire);
    }

    /**
     * Registers a service whose factory is invoked on every `get()`.
     *
     * @param string $id The service identifier.
     * @param Closure $factory Receives the container and returns the service.
     *
     * @return static This builder.
     *
     * @throws InvalidArgumentException if the identifier is already registered.
     *
     * @phpstan-param Closure(IContainer):mixed $factory
     */
    public function factory(string $id, Closure $factory): static
    {
        return $this->register($id, new Definition($factory, false));
    }

    /**
     * Registers a service whose factory is invoked at most once; every `get()` returns the same result.
     *
     * @param string $id The service identifier.
     * @param Closure $factory Receives the container and returns the service.
     *
     * @return static This builder.
     *
     * @throws InvalidArgumentException if the identifier is already registered.
     *
     * @phpstan-param Closure(IContainer):mixed $factory
     */
    public function singleton(string $id, Closure $factory): static
    {
        return $this->register($id, new Definition($factory, true));
    }

    private function register(string $id, Definition $definition): static
    {
        if (array_key_exists($id, $this->definitions)) {
            throw new InvalidArgumentException(sprintf('Service "%s" is already registered.', $id));
        }
        $this->definitions[$id] = $definition;

        return $this;
    }
}
