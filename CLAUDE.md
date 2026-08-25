# php-strong

PHP 8.5+ library providing solid, strongly-typed implementations of PHP-FIG PSR interfaces. Namespace `Manychois\PhpStrong`, PSR-4 autoload (`src/` → tests in `tests/`).

## Commands

```bash
composer test                    # PHPUnit with coverage (XDEBUG_MODE=coverage)
composer phpcs                   # Style check (phpcs.xml)
composer phpcbf                  # Auto-fix style
composer phpstan                 # Static analysis, max level
composer code                    # phpcbf + phpcs + phpstan

./vendor/bin/phpunit tests/Http/ResponseTest.php                # single file
./vendor/bin/phpunit --filter '/::testMethodName$/' tests/X.php # single method
./vendor/bin/phpunit --filter ResponseTest                      # by class pattern
./vendor/bin/phpunit --testdox tests/Http/ResponseTest.php
```

## Quality gates

Before finishing any task, run in order and fix anything they report:
`composer phpcbf` → `composer phpcs` → `composer phpstan` → `composer test`.

## Code style

Full standard: @docs/internal/php-coding-standard.md

Non-negotiable:
- Alias interfaces on import: `use SequenceInterface as ISequence`
- `#[Override]` on every interface implementation
- Group methods in `#region implements IInterface` … `#endregion` blocks
- Sort methods alphabetically within each region (same visibility/static/final group)
- `readonly` properties; precise PHPDoc types (`list<T>`, `non-negative-int`)
- Return narrowed types where possible (concrete class or narrower interface over the PSR type)
- PHPDoc on all public/protected methods

## `Http` module seam

Only `SapiEmitter` may call `header()` or `headers_sent()` unqualified. Every other class in
`Manychois\PhpStrong\Http` calling either one gets silently intercepted by the test stubs in
`tests/Http/sapi-functions.php` — its tests pass, but it sends nothing in production. A class that genuinely
needs the real function must call it fully qualified as `\header()` or `\headers_sent()`.

## Public documentation

For user-facing docs (README, guides, API reference), follow Diátaxis: @docs/internal/diataxis-framework-reference.md — keep tutorials, how-tos, explanation, and reference separate.

## Architecture

Scope: solid, strongly-typed building blocks — concrete implementations of PSR interfaces, plus general-purpose utilities in the same problem areas (e.g. calendar types alongside the PSR-20 clocks in `Time/`). PSRs currently depended on: PSR-3 (`psr/log`), PSR-6 (`psr/cache`), PSR-7 (`psr/http-message`), PSR-11 (`psr/container`), PSR-13 (`psr/link`), PSR-14 (`psr/event-dispatcher`), PSR-15 (`psr/http-server-handler`, `psr/http-server-middleware`), PSR-16 (`psr/simple-cache`), PSR-17 (`psr/http-factory`), PSR-18 (`psr/http-client`), PSR-20 (`psr/clock`). Add the matching `psr/*` package to `composer.json` when implementing a new PSR.

Principles:
- One directory per problem area under `src/` (e.g. `Http/`, `DependencyInjection/`, `Time/`), named after the domain rather than the PSR number so non-PSR utilities can live beside the PSR classes; tests mirror it under `tests/`.
- Where a PSR governs the concern, implement its interface (or a small extension of one); utilities that no PSR covers still belong to that module. No framework coupling beyond `psr/*`.
- Immutable value objects where the PSR mandates it (PSR-7 `with*` methods return clones); `readonly` elsewhere.
- Composition over inheritance; no abstract base classes in the public API.
- Enums for closed sets (HTTP methods, status codes) with conversion to the string/int the PSR interface expects.
- Fail at the boundary: validate inputs in constructors/factories and throw `InvalidArgumentException` per the PSR contract rather than accepting loose data.
