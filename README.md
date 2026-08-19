# php-strong

**php-strong** provides solid, strongly-typed implementations of the [PHP-FIG PSR](https://www.php-fig.org/psr/) interfaces, one sub-namespace per PSR. The goal is to cover every PSR that defines an interface.

Targets **PHP 8.5+**, namespace `Manychois\PhpStrong`, PSR-4 autoloading.

## Installation

```bash
composer require manychois/php-strong
```

## Implementations

| PSR | Namespace | Summary | Docs |
| --- | --------- | ------- | ---- |
| PSR-3 Logger | `Manychois\PhpStrong\Logging` | `Logger` dispatching immutable `Log` objects to handlers (stream, console, in-memory) with pluggable formatters and `{placeholder}` interpolation. | [docs/logging.md](docs/logging.md) |
| PSR-7 HTTP Message + PSR-17 Factories | `Manychois\PhpStrong\Http` | Immutable `Request`, `ServerRequest` (with `fromGlobals()`), `Response`, `Stream`, `UploadedFile`, `Uri`, `Method`/`StatusCode` enums, and the five PSR-17 factories. | [docs/http.md](docs/http.md) |
| PSR-11 Container | `Manychois\PhpStrong\Container` | `ContainerBuilder` (`singleton`/`factory`/opt-in `autowire`) producing an immutable, lazily-resolving `Container` with circular-dependency detection. | [docs/container.md](docs/container.md) |
| PSR-20 Clock | `Manychois\PhpStrong\Clock` | `UtcClock` (always UTC) and `TestClock` (frozen/advanceable instant for deterministic tests). | [docs/clock.md](docs/clock.md) |

More PSRs (6, 13, 14, 15, 16, 18) are planned.

## Development

```bash
composer code   # phpcbf + phpcs + phpstan
composer test   # phpunit with coverage
```

## License

MIT.
