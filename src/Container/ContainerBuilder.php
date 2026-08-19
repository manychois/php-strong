<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\Container;

use Closure;
use InvalidArgumentException;
use Manychois\PhpStrong\Container\Internal\Definition;
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
     * @param ?IContainer $parent Container consulted for identifiers not registered on this builder; lets a
     * short-lived (e.g. per-request) container delegate to a long-lived application container.
     */
    public function __construct(
        private readonly ?IContainer $parent = null,
    ) {
    }

    /**
     * Creates the container from the definitions registered so far.
     * Later registrations on this builder do not affect the returned container.
     *
     * @return Container The built container.
     */
    public function build(): Container
    {
        return new Container($this->definitions, $this->parent);
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
