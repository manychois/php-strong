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

- Remove unused imports (auto-fixed by phpcbf)
- Use `as` aliases for interfaces as a personal preference for shorter names

## Class Code Structure Order

1. Define `extends` then `implements` in class declaration, with each item in alphabetical order
2. Constants
3. Properties
4. Constructor
5. Organise methods into `#region` blocks based on `extends`/`implements`
6. Methods outside of regions are placed at top, then `#region` blocks in the order declared in the class declaration
7. Order methods within each region (and those outside regions) by:
   - static then instance
   - final then non-final
   - abstract public, public, abstract protected, protected, private
   - **Alphabetically within each category** (e.g., all public instance methods sorted A-Z)
8. Within each `#region implements IInterface` (or equivalent) block, **`public` property hooks** that implement members of that interface belong at the **top** of the region, **before** any methods—matching the class-level rule that properties come before methods. Remaining members in that region are methods ordered as in step 7.
9. Use `#[Override]` attribute wherever applicable, with `@inheritDoc` in docblock

**Example:** In a class where all methods are public instance methods with `#[Override]`, they must be sorted alphabetically: `add()`, `all()`, `any()`, `asArray()`, `asList()`, etc.

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

## PHPDoc inline layout

- Use **single spaces** between parts of a tag: after `@param`, between the type and `$name`, and before the description (e.g. `@param string $text The text.`). Do **not** pad types or names with extra spaces to column-align multiple `@param` or `@return` lines.
- Long descriptions may wrap to the next doc line; avoid indenting continuation text to match a fictional “description column.”
- Standard PHPCS rules in this project do not auto-fix horizontal padding inside tags; keep this style by convention.

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
 * Method description here, begins with a verb and ends with a period.
 * The verb should be in simple present tense ending with s.
 *
 * @param callable $predicate The predicate to check.
 *
 * @return int The first value.
 *
 * @throws UnderflowException if empty.
 * @throws RuntimeException if no match.
 *
 * @phpstan-param callable(T,non-negative-int):bool $predicate
 */
public function first(?callable $predicate = null): int { }
```

## PHPDoc Annotations

- `@template T` for generic types
- Use plain `int` in readable positions (params, returns, extends)
- Use `@phpstan-` prefixed tags for PHPStan-specific precision types
- Lowercase native types: `@return bool`
- Prefer shorter interface alias: `use Manychois\PhpStrong\Collections\SequenceInterface as ISequence;`
- Shorter nullable: `@param ?int $count`

## Intelephense Workarounds

Intelephense doesn't understand `int<0,max>`, so use plain `int` in `@param` for readability, then add a separate `@phpstan-param non-negative-int` instead.

## Callable Parameter Pattern

Prefer this style, general `callable` in `@param`, but specify precise signature in `@phpstan-param` for static analysis, e.g.:

```php
@param callable $predicate The predicate to check.
@phpstan-param callable(T,non-negative-int):bool $predicate
```

## Override Attribute

Use `#[Override]` on interface method implementations:
```php
/**
 * @inheritDoc
 */
#[Override]
public function getIterator(): Iterator { }
```

## Region Comments

```php
#region implements ISequence

/**
 * @inheritDoc
 */
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

When a hook implements an interface member inside `#region implements IInterface`, place that hook **first** in the region, then the methods (alphabetically per **Class Code Structure Order**). Ordinary fields stay in the class-level properties section above the constructor; only hooks declared as part of the interface implementation block go at the top of that region.

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
