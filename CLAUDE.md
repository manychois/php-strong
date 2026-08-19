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

Full standard: @documentation/internal/php-coding-standard.md

Non-negotiable:
- Alias interfaces on import: `use SequenceInterface as ISequence`
- `#[Override]` on every interface implementation
- Group methods in `#region implements IInterface` … `#endregion` blocks
- Sort methods alphabetically within each region (same visibility/static/final group)
- `readonly` properties; precise PHPDoc types (`list<T>`, `non-negative-int`)
- Return narrowed types where possible (concrete class or narrower interface over the PSR type)
- PHPDoc on all public/protected methods

## Public documentation

For user-facing docs (README, guides, API reference), follow Diátaxis: @documentation/internal/diataxis-framework-reference.md — keep tutorials, how-tos, explanation, and reference separate.

## Architecture

Scope: concrete implementations of PSR interfaces. Currently depended on: PSR-7 (`psr/http-message`), PSR-17 (`psr/http-factory`), PSR-11 (`psr/container`), PSR-20 (`psr/clock`). Add the matching `psr/*` package to `composer.json` when implementing a new PSR.

Principles:
- One directory per PSR concern under `src/` (e.g. `Http/`, `Container/`, `Clock/`), tests mirror it under `tests/`.
- Every public class implements a PSR interface (or a small extension of one); no framework coupling beyond `psr/*`.
- Immutable value objects where the PSR mandates it (PSR-7 `with*` methods return clones); `readonly` elsewhere.
- Composition over inheritance; no abstract base classes in the public API.
- Enums for closed sets (HTTP methods, status codes) with conversion to the string/int the PSR interface expects.
- Fail at the boundary: validate inputs in constructors/factories and throw `InvalidArgumentException` per the PSR contract rather than accepting loose data.
