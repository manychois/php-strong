# php-strong

A utility library for PHP 8.4+ that helps you write strong-typed code with confidence.

**Key Features:**

- **Type-Safe Array Access** - `ArrayAccessor` provides strongly-typed methods to retrieve array values (asString, asInt, asBool, etc.)

- **Comparison Interfaces** - `EqualInterface` and `ComparableInterface` enable value-based equality and ordering comparisons for custom objects, similar to Java/.NET patterns

- **Strong PSR Container** - `StrongContainerInterface` wraps PSR-11 containers with type-safe `getObject()` method, ensuring you get the exact class you expect

- **Object-Oriented Regex** - `Regex` class provides a clean, exception-based API for regular expressions with type-safe match results and captures

- **Type-Safe Sessions** - `PhpSession` extends `ArrayAccessor` to manage PHP sessions with strong typing, preventing session data type mismatches

- **PSR Clock Implementation** - `UtcClock` provides PSR-20 compliant time handling for testable, timezone-aware applications

**Why Use It?**

php-strong brings the reliability of static typing to PHP's dynamic features (arrays, sessions, containers), catching type errors at the point of access rather than deep in your application logic. Perfect for teams wanting C#/Java-style type safety in modern PHP.

**Installation:**

```bash
composer require manychois/php-strong
```
