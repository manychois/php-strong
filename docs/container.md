# PSR-11 Container — `Manychois\PhpStrong\Container`

An implementation of `Psr\Container\ContainerInterface` that resolves services lazily from closures. Definitions are
collected on a mutable `ContainerBuilder`; `build()` returns an immutable `Container`. There is no autowiring: every
service is registered explicitly.

```php
$container = (new ContainerBuilder())
    ->singleton(PDO::class, static fn (ContainerInterface $c): PDO => new PDO('sqlite::memory:'))
    ->factory('uuid', static fn (): string => bin2hex(random_bytes(16)))
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
| `build(): Container` | Snapshots the definitions; later registrations do not affect the built container. |

`new ContainerBuilder(?ContainerInterface $parent = null)` — identifiers not registered on the builder are delegated
to `$parent` (any PSR-11 container). See [Long-running processes](#long-running-processes).

To register an already-built value, wrap it: `->singleton('config', static fn (): array => $config)`.

Factories have the signature `Closure(ContainerInterface): mixed` and receive the container being resolved from.
Registering an identifier twice throws `InvalidArgumentException`.

## `Container`

| Method | Notes |
| ------ | ----- |
| `get(string $id): mixed` | Resolves the service from this container's definitions, else from the parent. |
| `has(string $id): bool` | Whether the identifier is registered here or in the parent; does not resolve anything. |

`get()` throws:

| Exception | When |
| --------- | ---- |
| `NotFoundException` (`NotFoundExceptionInterface`) | The identifier is not registered — also when thrown by a nested `get()` inside a factory. |
| `ContainerException` (`ContainerExceptionInterface`) | A circular dependency is detected (`Circular dependency detected: a -> b -> a.`), or a factory throws; the original exception is available via `getPrevious()`. |

`NotFoundException extends ContainerException`, so a single `catch (ContainerException)` covers both. A failed
resolution leaves no partial state: a later `get()` for the same identifier retries the factory.

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
