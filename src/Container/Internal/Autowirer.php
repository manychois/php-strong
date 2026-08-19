<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\Container\Internal;

use Closure;
use InvalidArgumentException;
use Manychois\PhpStrong\Container\ContainerException;
use Psr\Container\ContainerInterface as IContainer;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionParameter;

/**
 * Instantiates classes by resolving constructor parameters from a container.
 *
 * @internal
 */
final class Autowirer
{
    /**
     * Per class: its reflection and, per non-variadic constructor parameter, either a fixed argument value or a
     * closure producing it; populated on first use.
     *
     * @var array<class-string, array{ReflectionClass<object>, list<mixed>}>
     */
    private array $cache = [];

    /**
     * Verifies that `$class` can be instantiated by `instantiate()`.
     *
     * @param string $class Fully qualified class name.
     *
     * @throws InvalidArgumentException if the class does not exist or is not instantiable.
     */
    public static function assertInstantiable(string $class): void
    {
        if (!class_exists($class) && !interface_exists($class)) {
            throw new InvalidArgumentException(sprintf('Class "%s" does not exist.', $class));
        }
        if (!(new ReflectionClass($class))->isInstantiable()) {
            throw new InvalidArgumentException(sprintf('Class "%s" is not instantiable.', $class));
        }
    }

    /**
     * Creates an instance of `$class`, resolving each constructor parameter in this order: a service registered
     * under the parameter's type, the parameter's default value, a recursively built instance of an unregistered
     * instantiable class, `null` for nullable types.
     *
     * @param string $class Fully qualified, instantiable class name.
     * @param IContainer $container Source of registered dependencies.
     * @param list<string> $building Classes currently being built by recursion; used to detect cycles.
     *
     * @return object The new instance.
     *
     * @throws ContainerException if a parameter cannot be resolved or a cycle is detected.
     *
     * @phpstan-param class-string $class
     */
    public function instantiate(string $class, IContainer $container, array $building = []): object
    {
        if (in_array($class, $building, true)) {
            throw new ContainerException(
                sprintf('Circular dependency detected: %s -> %s.', implode(' -> ', $building), $class),
            );
        }
        $building[] = $class;

        [$ref, $resolvers] = $this->cache[$class] ??= $this->reflect($class, $container);
        $args = [];
        foreach ($resolvers as $resolver) {
            $args[] = $resolver instanceof Closure ? $resolver($container, $building) : $resolver;
        }

        return $ref->newInstanceArgs($args);
    }

    /**
     * @return array{ReflectionClass<object>, list<mixed>} The class and, per non-variadic constructor parameter, a
     * fixed value or a resolver closure.
     *
     * @phpstan-param class-string $class
     */
    private function reflect(string $class, IContainer $container): array
    {
        $ref = new ReflectionClass($class);
        $resolvers = [];
        foreach ($ref->getConstructor()?->getParameters() ?? [] as $param) {
            if ($param->isVariadic()) {
                break;
            }
            $resolvers[] = $this->resolver($param, $class, $container);
        }

        return [$ref, $resolvers];
    }

    /**
     * Decides once how a parameter is resolved: a fixed value (constant default or `null`) or a closure for values
     * that must be produced per call. The decision is stable because the container's definitions and parent cannot
     * change after construction.
     *
     * @return mixed The fixed value, or a resolver closure with signature `Closure(IContainer, list<string>): mixed`.
     */
    private function resolver(ReflectionParameter $param, string $class, IContainer $container): mixed
    {
        $type = $param->getType();
        $typeName = $type instanceof ReflectionNamedType && !$type->isBuiltin() ? $type->getName() : null;

        if ($typeName !== null && $container->has($typeName)) {
            return static fn (IContainer $c): mixed => $c->get($typeName);
        }
        if ($param->isDefaultValueAvailable()) {
            $default = $param->getDefaultValue();

            // Objects come from `new` in the initializer and must be fresh for every instance.
            return is_object($default) ? static fn (): mixed => $param->getDefaultValue() : $default;
        }
        if ($typeName !== null && class_exists($typeName) && (new ReflectionClass($typeName))->isInstantiable()) {
            return function (IContainer $c, array $building) use ($typeName): object {
                /** @var list<string> $building */
                return $this->instantiate($typeName, $c, $building);
            };
        }
        if ($type?->allowsNull() === true) {
            return null;
        }

        $message = sprintf(
            'Cannot autowire parameter $%s of %s::__construct(): no registered service, default value or instantiable'
            . ' class for type %s.',
            $param->getName(),
            $class,
            $type === null ? 'mixed' : (string) $type,
        );

        return static fn (): never => throw new ContainerException($message);
    }
}
