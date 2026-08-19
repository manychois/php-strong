<?php

declare(strict_types=1);

namespace Manychois\PhpStrongTests\Container\Fixtures;

interface GreeterInterface
{
}

final class NoConstructor
{
}

final class Leaf
{
    public function __construct(public readonly NoConstructor $dep)
    {
    }
}

final class Greeter implements GreeterInterface
{
    public function __construct(
        public readonly Leaf $leaf,
        public readonly string $name = 'world',
        public readonly ?NoConstructor $optional = null,
        public readonly ?Leaf $nullable = null,
        int ...$rest,
    ) {
    }
}

final class NeedsScalar
{
    public function __construct(public readonly int $count)
    {
    }
}

final class NeedsUntyped
{
    public mixed $x;

    /**
     * @param mixed $x
     */
    public function __construct($x) // phpcs:ignore
    {
        $this->x = $x;
    }
}

final class NeedsInterface
{
    public function __construct(public readonly GreeterInterface $greeter)
    {
    }
}

final class NullableInterface
{
    public function __construct(public readonly ?GreeterInterface $greeter)
    {
    }
}

final class PrivateConstructor
{
    private function __construct()
    {
    }
}

abstract class AbstractThing
{
}

final class NeedsAbstract
{
    public function __construct(public readonly AbstractThing $thing)
    {
    }
}

final class CycleA
{
    public function __construct(public readonly CycleB $b)
    {
    }
}

final class CycleB
{
    public function __construct(public readonly CycleA $a)
    {
    }
}

final class NeedsCycle
{
    public function __construct(public readonly CycleA $a)
    {
    }
}

final class NewInInitializer
{
    public function __construct(public readonly NoConstructor $dep = new NoConstructor())
    {
    }
}

final class Throwing
{
    public function __construct()
    {
        throw new \LogicException('ctor failed');
    }
}
