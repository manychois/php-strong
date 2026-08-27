# PSR-11 Container — `Manychois\PhpStrong\DependencyInjection`

An implementation of `Psr\Container\ContainerInterface` that resolves services lazily from closures or by reflection.
Definitions are collected on a mutable `ContainerBuilder`; `build()` returns an immutable `Container`. Every service
identifier is registered explicitly — autowiring is opt-in per class.

```php
$container = (new ContainerBuilder())
    ->singleton(PDO::class, static fn (ContainerInterface $c): PDO => new PDO('sqlite::memory:'))
    ->factory('uuid', static fn (): string => bin2hex(random_bytes(16)))
    ->autowire(UtcClock::class)                     // built by reflection, singleton
    ->alias(ClockInterface::class, UtcClock::class) // interface → the same UtcClock instance
    ->build();

$container->get(PDO::class);  // created on first call, same instance afterwards
$container->get('uuid');      // new value on every call
$container->has('uuid');      // true; never invokes the factory
```

## `ContainerBuilder`

| Method | Notes |
| ------ | ----- |
| `singleton(string $id, Closure $factory): static` | Factory runs at most once; the result (including `null`) is cached. |
| `factory(string $id, Closure $factory): static` | Factory runs on every `get()`. |
| `aware(string $type, Closure $configure): static` | Runs `$configure($object, $container)` on every object produced here that is `instanceof $type`. See [Aware configurers](#aware-configurers). |
| `alias(string $id, string $target): static` | `get($id)` forwards to `get($target)`; follows the target's lifetime. Target must be registered (here or in the parent) by `build()`. |
| `autowire(string $id, bool $shared = true): static` | Instantiates the class named `$id` by reflection under that id; cached like `singleton()` unless `$shared` is `false`. See [Autowiring](#autowiring). |
| `build(): Container` | Snapshots the definitions; later registrations do not affect the built container. |

`new ContainerBuilder(?ContainerInterface $parent = null)` — identifiers not registered on the builder are delegated
to `$parent` (any PSR-11 container). See [Long-running processes](#long-running-processes).

To register an already-built value, wrap it: `->singleton('config', static fn (): array => $config)`.

Factories have the signature `Closure(ContainerInterface): mixed` and receive the container being resolved from.
Registering an identifier twice throws `InvalidArgumentException`; so does an alias targeting itself or, at
`build()`, an unregistered identifier.

To bind an interface to a class, `autowire(Impl::class)` then `alias(Interface::class, Impl::class)`.

## `Container`

| Method | Notes |
| ------ | ----- |
| `get(string $id): mixed` | Resolves the service from this container's definitions, else from the parent. |
| `getInstance(string $class): object` | `get($class)` with an `instanceof $class` check; returns the precise type for static analysis (`@return T` for `class-string<T>`). Throws `ContainerException` on mismatch. |
| `make(string $class): object` | Autowires a new instance of any instantiable class against the container, registered or not; never cached. Throws `ContainerException` if the class cannot be built. |
| `has(string $id): bool` | Whether the identifier is registered here or in the parent; does not resolve anything. |

`get()` throws:

| Exception | When |
| --------- | ---- |
| `NotFoundException` (`NotFoundExceptionInterface`) | The identifier is not registered — also when thrown by a nested `get()` inside a factory. |
| `ContainerException` (`ContainerExceptionInterface`) | A circular dependency is detected (`Circular dependency detected: a -> b -> a.`), or a factory throws; the original exception is available via `getPrevious()`. |

`NotFoundException extends ContainerException`, so a single `catch (ContainerException)` covers both. A failed
resolution leaves no partial state: a later `get()` for the same identifier retries the factory.

## Autowiring

`autowire()` checks at registration that the class exists and is instantiable (`InvalidArgumentException` otherwise),
then instantiates it lazily on first `get()`. Each constructor parameter is resolved by the first matching rule:

1. The parameter has a class/interface type that is registered in the container → `get(Type::class)`.
2. The parameter has a default value → the default.
3. The type is an unregistered, instantiable class → built recursively by the same rules (not cached; `autowire()` it
   too if it should be shared).
4. The type is nullable → `null`.
5. Otherwise → `ContainerException`
   (`Cannot autowire parameter $count of App\Foo::__construct(): no registered service, default value or
   instantiable class for type int.`).

Scalars, union/intersection types and untyped parameters therefore need a default value or must be wired through a
closure instead. Variadic parameters receive no arguments. Cycles among unregistered classes are reported as
`Circular dependency detected: A -> B -> A.`; cycles through registered identifiers are caught by `get()`.

## Aware configurers

`aware()` registers a type-based hook for setter injection, typically for PSR `*AwareInterface`s:

```php
->aware(LoggerAwareInterface::class, static function (LoggerAwareInterface $obj, ContainerInterface $c): void {
    $obj->setLogger($c->get(LoggerInterface::class));
})
```

- Runs after a definition (closure or `autowire`) produces an object and before a singleton is cached: once per
  singleton, on every `get()` for factories. Aliases do not re-run it; non-object values are skipped.
- Several rules may match one object; they run in registration order.
- Rules apply only to objects produced by the container they are registered on — a child container does not
  reconfigure its parent's services.
- Exceptions thrown by a configurer are wrapped in `ContainerException`, except one that already is a
  `ContainerException` (such as a `NotFoundException` from a nested `get()`), which propagates as it is. `get()`
  calls inside a configurer take part in circular-dependency detection.

## Long-running processes

In worker runtimes (FrankenPHP worker mode, RoadRunner, Swoole) the process survives many requests, so anything
registered with `singleton()` in a container built at boot lives for the whole process. Keep request-specific services out of that
container; instead build a short-lived child per request that delegates to the application container:

```php
$app = (new ContainerBuilder())
    ->singleton(PDO::class, static fn (): PDO => new PDO('sqlite::memory:'))
    ->build();

while (frankenphp_handle_request(static function () use ($app, $handler): void {
    $request = (new ContainerBuilder($app))
        ->singleton(ServerRequestInterface::class, static fn (): ServerRequest => ServerRequest::fromGlobals())
        ->singleton(Session::class, static fn (ContainerInterface $c): Session => new Session($c->get(PDO::class)))
        ->build();
    $handler->handle($request)->send();
})) {
}
```

- Child definitions shadow parent ones with the same identifier.
- Factories registered on the child receive the child, so they can resolve both request and application services.
- Factories registered on the parent receive the parent, so application services cannot depend on request services.
- The child is released when the callback returns; the parent's singletons persist.
