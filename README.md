# php-strong

**php-strong** is a small PHP library that adds **typed boundaries** around places PHP is usually loose: nested arrays, the DI container, native sessions, and string matching. It does not replace the language type system; it gives you explicit, predictable APIs so invalid shapes fail **at the edge** (when you read a value or resolve a service) instead of far down the call stack.

It targets **PHP 8.5+**, uses the `Manychois\PhpStrong` namespace with PSR-4 autoloading, and integrates with **PSR-11** (containers) and **PSR-20** (clock) where relevant.

## What’s in the box

- **Collections** — Lazy and eager sequences (`LazySequence`), mutable lists (`ArrayList`), readonly list views (`ReadonlyList`), maps with typed keys (`StringMap`, `IntMap`, `ObjectMap`, and readonly variants), plus shared sequence/list interfaces and comparers for ordering. Suited to application code that wants clear generics-friendly collection APIs.

- **Typed array and object reading** — `ArrayReader` (and `ArrayReaderInterface`) walk **dot-separated paths** and expose readers such as `asInt`, `bool`, `string`, `object`, and `instanceOf` so nested structures are validated when accessed.

- **PSR-11 container** — `StrongContainerInterface` and `StrongContainerWrapper` wrap any `Psr\Container\ContainerInterface` and add `getObject($id, $class)` so resolved services are checked against an expected class or interface.

- **`Web`** — `PhpSession` (and `PhpSessionInterface`) treat `$_SESSION` like a typed reader: same style of safe access as `ArrayReader`, with session lifecycle helpers.

- **Time** — `UtcClock` implements PSR-20’s clock in UTC, with support for deterministic tests (e.g. frozen instants).

- **Text** — `Regex`, `MatchResult`, `Capture`, and `Utf8String` offer an object-oriented, exception-oriented approach to pattern matching and UTF-8 strings; small helpers like `StringSide` and value types such as `DayOfWeek` live alongside them.

## Why use it?

If you like **C#- or Java-style rigor** at API boundaries—without fighting PHP’s arrays and superglobals—php-strong narrows `mixed` early: fewer surprises in business logic and better alignment with static analysis (e.g. PHPStan) on downstream code.

## Installation

```bash
composer require manychois/php-strong
```
