# AGENTS.md - php-strong

PHP 8.4+ utility library for strong-typed code. Namespace: `Manychois\PhpStrong`, PSR-4 autoload.

## Build/Test Commands

```bash
composer test                    # Run all tests with code coverage
composer phpcs                   # Check PSR-12 style compliance
composer phpcbf                  # Auto-fix code style violations
composer phpstan                 # Static analysis at max level
composer code                    # Run phpcbf + phpcs + phpstan (full quality check)

# Single test file
./vendor/bin/phpunit tests/Collections/ArrayListTest.php

# Single test method (exact match)
./vendor/bin/phpunit --filter '/::testMethodName$/' tests/Path/To/TestFile.php

# Tests matching a pattern
./vendor/bin/phpunit --filter '/test.*Order/' tests/
./vendor/bin/phpunit --filter ReadonlyListTest

# Debug test output
./vendor/bin/phpunit --testdox tests/Collections/ArrayListTest.php
```

## Code Style Guidelines

See [PHP-CODING-STYLE.md](./PHP-CODING-STYLE.md) for complete coding standards.

**Critical rules:**
- Always use interface aliases: `use SequenceInterface as ISequence`
- Use `#[Override]` attribute on all interface implementations
- Place methods in `#region implements IInterface` blocks
- **Sort methods alphabetically within each region** (same visibility/static/final category)
- Use `readonly` properties and precise types (`list<T>`, `non-negative-int`)

## Quality Gates

**Before completing any task, ALWAYS run:**
1. `composer phpcbf` - auto-fix style
2. `composer phpcs` - verify PSR-12
3. `composer phpstan` - type safety at max level
4. `composer test` - all tests pass

## Architecture Overview

### Interface Hierarchy
```
SequenceInterface (ISequence) - 30+ query/transformation methods
  ↓ extends
ReadonlyListInterface (IReadonlyList) + ArrayAccess - index-based access
  ↓ extends
ListInterface (IList) - mutation operations
```

### Concrete Implementations

**LazySequence** (implements ISequence)
- Lazy evaluation via generators, wraps any iterable
- Optimizes arrays with direct functions, returns new LazySequence instances

**ArrayList** (implements IList)
- Mutable array-backed (`list<T>`), eager evaluation
- Delegates ISequence methods to LazySequence, supports negative indices

**ReadonlyList** (implements IReadonlyList)
- Immutable wrapper via composition, wraps ArrayList internally
- Delegates reads, throws BadMethodCallException on mutations

### Design Patterns
1. **Lazy vs Eager**: LazySequence (generators) vs ArrayList (arrays)
2. **Delegation/Composition**: ReadonlyList→ArrayList, ArrayList→LazySequence
3. **No inheritance**: Pure interfaces, composition over inheritance
4. **Array optimization**: Fast paths for array sources

### Implementation Guidelines
- Use `#[Override]` on all interface methods
- Place methods in `#region implements IInterface` blocks
- Return narrowed types when appropriate (`IList<T>` vs `ISequence<T>`)
- Sort methods alphabetically within regions
- Document all public/protected methods with proper PHPDoc
