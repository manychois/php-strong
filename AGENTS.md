# AGENTS.md - php-strong

A PHP 8.4+ utility library for strong-typed code. Namespace: `Manychois\PhpStrong`, PSR-4 autoload.

## Build/Test Commands

```bash
composer test                    # Run all tests with code coverage
composer phpcs                   # Check PSR-12 style compliance
composer phpcbf                  # Auto-fix code style violations
composer phpstan                 # Static analysis at max level
composer code                    # Run phpcbf + phpcs + phpstan (full quality check)

# Single test by function name
./vendor/bin/phpunit --filter testFunctionName tests/Path/To/TestFile.php

# Single test file
./vendor/bin/phpunit tests/Path/To/TestFile.php

# Single test method (exact match)
./vendor/bin/phpunit --filter '/::testMethodName$/' tests/Path/To/TestFile.php

# Tests matching a pattern
./vendor/bin/phpunit --filter '/test.*Order/' tests/
```

### Common Test Patterns
```bash
# Test all methods in a class
./vendor/bin/phpunit tests/Collections/ArrayListTest.php

# Run tests for a specific interface implementation
./vendor/bin/phpunit --filter ReadonlyListTest

# Debug test output
./vendor/bin/phpunit --testdox tests/Collections/ArrayListTest.php
```

## Code Style

See [PHP-CODING-STYLE.md](./PHP-CODING-STYLE.md) for code formatting guidelines.

## Directory Structure
```
src/Collections/Defaults/*.php    # Default implementations
src/Collections/*.php             # Collection classes/interfaces
src/*.php                         # Core interfaces
tests/                            # Mirrors src structure
```

## Quality Gates

Before completing any task, run:
1. `composer phpcbf` - auto-fix style
2. `composer phpcs` - verify PSR-12
3. `composer phpstan` - type safety
4. `composer test` - all tests pass

## Key Patterns for This Project

### Read-Optimized Methods in AbstractBaseList
- AbstractBaseList provides optimized implementations for read-type methods using direct array access
- Methods like `isEmpty()`, `contains()`, `first()`, `firstOrNull()`, `last()`, `lastOrNull()`, `slice()`, `skip()`, `take()`, `orderBy()`, `orderDescBy()`, `reverse()`, `shuffle()` use array functions instead of iterator-based approaches
- Both ArrayList and ReadonlyList inherit these optimizations automatically

### Constructor Requirements
- Use `final public function __construct(...)` for concrete collection classes
- Accept `iterable $source = []` as parameter for flexible construction from arrays or iterables

### Interface Implementation Guidelines
- Always use `#[Override]` attribute when implementing interface methods
- Place implementation in appropriate region comment (e.g., `#region implements ISequence`)
- Return narrowed types when appropriate (e.g., `IReadonlyList<T>` instead of `ISequence<T>`)
