# PHP Coding Style Guide

PHP 8.4+ coding conventions.

## File Structure

```php
<?php
declare(strict_types=1);

namespace Vendor\Project\Xxx;

use Vendor\Project\Yyy\YyyInterface as IYyy;
use AnotherClass;

class Xxx implements IXxx { }
```

## Naming Conventions

| Element | Convention | Example |
|---------|-----------|---------|
| Classes | PascalCase | `ReadonlyList` |
| Interfaces | `XxxInterface` | `SequenceInterface` |
| Interface alias | `IXxx` | `use SequenceInterface as ISequence;` |
| Methods/Properties | camelCase | `firstOrNull`, `$source` |
| Constants | UPPER_SNAKE_CASE | `MAX_SIZE` |
| Regions | `#region implements IInterface` | |

## Import Rules

- Sort alphabetically within groups (PSR-12 order)
- Remove unused imports (auto-fixed by phpcbf)
- Use `as` aliases for interfaces as a personal preference for shorter names
- Group order: extends/implements, then `use` statements, then other imports

## DocBlock Spacing

- Require 1 empty line between different annotation types (e.g., `@param` and `@return`)
- Example:
  ```php
  /**
   * @param int $index The index.
   *
   * @return mixed The value.
   */
  ```

## DocBlocks

### Classes/Interfaces

Required on all:
```php
/**
 * Brief description.
 *
 * @template T
 *
 * @implements ISequence<T>
 */
class Xxx implements ISequence { }
```

### Methods

Required on public/protected:
```php
/**
 * @param callable(T,int):bool $predicate The predicate to check.
 *
 * @return int The first value.
 *
 * @throws UnderflowException if empty.
 * @throws RuntimeException if no match.
 *
 * @phpstan-param callable(T,int<0,max>):bool $predicate
 */
public function first(?callable $predicate = null): int { }
```

## PHPDoc Annotations

- `@template T` for generic types
- Use plain `int` in readable positions (params, returns, extends)
- Use `@phpstan-` prefixed tags for PHPStan-specific precision types
- Lowercase native types: `@return bool`
- Class names for objects: `@return SequenceInterface<T>`
- Nullable: `@param int|null $count`

## Callable Parameter Pattern

Intelephense doesn't understand `int<0,max>`, so use plain `int` in `@param` for readability, then add a separate `@phpstan-param` with the precise type:
```php
@param callable(T,int):bool $predicate The predicate to check.
@phpstan-param callable(T,int<0,max>):bool $predicate
```

## Override Attribute

Use `#[Override]` on interface method implementations:
```php
#[Override]
public function getIterator(): Iterator { }
```

## Region Comments

```php
#region implements ISequence

#[Override]
public function first(): mixed { }

#endregion implements ISequence
```

## Error Handling

- `InvalidArgumentException` - Invalid argument values
- `OutOfBoundsException` - Index out of bounds or invalid offset access
- `BadMethodCallException` - Unsupported operations (e.g., modifying readonly collections)
- `RuntimeException` - Runtime errors
- `UnderflowException` - Empty structure operations
- Include descriptive messages: `throw new InvalidArgumentException('Size must be > 0');`

## Property Declarations

- Use `readonly`: `protected readonly iterable $source;`
- Document template types: `@var iterable<T>`

## PHP 8.4 Property Hooks

Use property hooks for simple getter/setter patterns:
```php
public mixed $key { get => $this->k; }
public mixed $value { get => $this->v; }
```

## Closures/Generators

- Space before `use`: `function () use (...) { }`
- Generators: `return new Sequence($generator());`

## Generics Pattern

```php
/**
 * @template T
 *
 * @implements ISequence<T>
 */
class Sequence implements ISequence {
    /**
     * @param iterable<T> $source
     */
    public function __construct(iterable $source) { }

    /**
     * @param callable(T,int):bool $predicate The predicate to check.
     *
     * @return SequenceInterface<T>
     *
     * @phpstan-param callable(T,int<0,max>):bool $predicate
     */
    public function filter(callable $predicate): SequenceInterface { }
}
```

## Abstract Classes and Inheritance

- Use `AbstractXxx` base classes for shared implementations
- Subclasses must implement required abstract methods
- Subclasses must define `$source` property with appropriate type

## Abstract Factory Pattern

```php
// In AbstractBase:
abstract protected function createReadonlyList(iterable $source): IReadonlyList;

public function reverse(): IReadonlyList
{
    return $this->createReadonlyList(array_reverse($this->source));
}

// In ConcreteClass:
#[Override]
protected function createReadonlyList(iterable $source): IReadonlyList
{
    return new static($source);
}
```

## Internal Namespace

- Place internal/helper classes in `src/Internal/`
- Mark classes with `/** @internal */` docblock comment
- Use `Internal` namespace only for code not meant for public API
- Traits in Internal namespace are implementation details (not public API)

## Composer Scripts for Code Quality

This project uses Composer scripts to enforce coding standards:

| Command | Purpose |
|---------|---------|
| `composer test` | Run PHPUnit with code coverage |
| `composer phpcs` | Check PSR-12 style compliance |
| `composer phpcbf` | Auto-fix code style violations |
| `composer phpstan` | Run static analysis at max level with phpstan-strict-rules |
| `composer code` | Run full quality check (phpcbf + phpcs + phpstan) |

## Quality Gates

Before completing any task, run:
1. `composer phpcbf` - auto-fix style
2. `composer phpcs` - verify PSR-12
3. `composer phpstan` - type safety
4. `composer test` - all tests pass
