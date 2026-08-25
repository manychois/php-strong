<?php

declare(strict_types=1);

namespace Manychois\PhpStrongTests\DependencyInjection;

use InvalidArgumentException;
use LogicException;
use Manychois\PhpStrong\DependencyInjection\ContainerBuilder;
use Manychois\PhpStrong\DependencyInjection\ContainerException;
use Manychois\PhpStrong\DependencyInjection\NotFoundException;
use Manychois\PhpStrongTests\DependencyInjection\Fixtures\AbstractThing;
use Manychois\PhpStrongTests\DependencyInjection\Fixtures\CycleA;
use Manychois\PhpStrongTests\DependencyInjection\Fixtures\CycleB;
use Manychois\PhpStrongTests\DependencyInjection\Fixtures\Greeter;
use Manychois\PhpStrongTests\DependencyInjection\Fixtures\GreeterInterface;
use Manychois\PhpStrongTests\DependencyInjection\Fixtures\Leaf;
use Manychois\PhpStrongTests\DependencyInjection\Fixtures\NeedsAbstract;
use Manychois\PhpStrongTests\DependencyInjection\Fixtures\NeedsCycle;
use Manychois\PhpStrongTests\DependencyInjection\Fixtures\NeedsInterface;
use Manychois\PhpStrongTests\DependencyInjection\Fixtures\NeedsScalar;
use Manychois\PhpStrongTests\DependencyInjection\Fixtures\NeedsUntyped;
use Manychois\PhpStrongTests\DependencyInjection\Fixtures\NewInInitializer;
use Manychois\PhpStrongTests\DependencyInjection\Fixtures\NoConstructor;
use Manychois\PhpStrongTests\DependencyInjection\Fixtures\NullableInterface;
use Manychois\PhpStrongTests\DependencyInjection\Fixtures\PrivateConstructor;
use Manychois\PhpStrongTests\DependencyInjection\Fixtures\Throwing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface as IContainer;

require_once __DIR__ . '/Fixtures/Autowire.php';

final class AutowireTest extends TestCase
{
    #[Test]
    public function autowire_classWithoutConstructor(): void
    {
        $container = (new ContainerBuilder())->autowire(NoConstructor::class)->build();

        self::assertTrue($container->has(NoConstructor::class));
        $obj = $container->get(NoConstructor::class);
        self::assertInstanceOf(NoConstructor::class, $obj);
        self::assertSame($obj, $container->get(NoConstructor::class));
    }

    #[Test]
    public function autowire_notSharedBuildsNewInstanceEachGet(): void
    {
        $container = (new ContainerBuilder())->autowire(NoConstructor::class, false)->build();

        self::assertNotSame($container->get(NoConstructor::class), $container->get(NoConstructor::class));
    }

    #[Test]
    public function autowire_newInInitializerDefaultIsFreshPerInstance(): void
    {
        $container = (new ContainerBuilder())->autowire(NewInInitializer::class, false)->build();

        $a = $container->get(NewInInitializer::class);
        $b = $container->get(NewInInitializer::class);
        self::assertInstanceOf(NewInInitializer::class, $a);
        self::assertInstanceOf(NewInInitializer::class, $b);
        self::assertNotSame($a->dep, $b->dep);
    }

    #[Test]
    public function autowire_resolvesRegisteredDependenciesViaGet(): void
    {
        $leaf = new Leaf(new NoConstructor());
        $container = (new ContainerBuilder())
            ->singleton(Leaf::class, static fn (): Leaf => $leaf)
            ->autowire(Greeter::class)
            ->build();

        $greeter = $container->get(Greeter::class);
        self::assertInstanceOf(Greeter::class, $greeter);
        self::assertSame($leaf, $greeter->leaf);
    }

    #[Test]
    public function autowire_usesDefaultsAndNullForUnregisteredOptionalParams(): void
    {
        $container = (new ContainerBuilder())->autowire(Greeter::class)->build();

        $greeter = $container->get(Greeter::class);
        self::assertInstanceOf(Greeter::class, $greeter);
        self::assertSame('world', $greeter->name);
        self::assertNull($greeter->optional);
        self::assertNull($greeter->nullable);
        self::assertInstanceOf(Leaf::class, $greeter->leaf);
    }

    #[Test]
    public function autowire_buildsUnregisteredInstantiableDependenciesRecursivelyWithoutCaching(): void
    {
        $container = (new ContainerBuilder())->autowire(Greeter::class)->autowire(Leaf::class)->build();

        $greeter = $container->get(Greeter::class);
        self::assertInstanceOf(Greeter::class, $greeter);
        self::assertSame($container->get(Leaf::class), $greeter->leaf);
        self::assertFalse($container->has(NoConstructor::class));

        $other = (new ContainerBuilder())->autowire(Leaf::class)->build();
        $leaf = $other->get(Leaf::class);
        self::assertInstanceOf(Leaf::class, $leaf);
        self::assertInstanceOf(NoConstructor::class, $leaf->dep);
    }

    #[Test]
    public function autowire_resolvesInterfaceParameterViaAlias(): void
    {
        $container = (new ContainerBuilder())
            ->autowire(Greeter::class)
            ->alias(GreeterInterface::class, Greeter::class)
            ->autowire(NeedsInterface::class)
            ->build();

        $needs = $container->get(NeedsInterface::class);
        self::assertInstanceOf(NeedsInterface::class, $needs);
        self::assertSame($container->get(Greeter::class), $needs->greeter);
        self::assertSame($container->get(GreeterInterface::class), $needs->greeter);
    }

    #[Test]
    public function autowire_nullableUnresolvableInterfaceBecomesNull(): void
    {
        $container = (new ContainerBuilder())->autowire(NullableInterface::class)->build();
        $obj = $container->get(NullableInterface::class);
        self::assertInstanceOf(NullableInterface::class, $obj);
        self::assertNull($obj->greeter);
    }

    #[Test]
    public function autowire_throwsOnUnresolvableScalar(): void
    {
        $container = (new ContainerBuilder())->autowire(NeedsScalar::class)->build();
        $this->expectException(ContainerException::class);
        $this->expectExceptionMessage(
            'Cannot autowire parameter $count of ' . NeedsScalar::class . '::__construct(): no registered service,'
            . ' default value or instantiable class for type int.',
        );
        $container->get(NeedsScalar::class);
    }

    #[Test]
    public function autowire_throwsOnUntypedParameter(): void
    {
        $container = (new ContainerBuilder())->autowire(NeedsUntyped::class)->build();
        $this->expectException(ContainerException::class);
        $this->expectExceptionMessage('Cannot autowire parameter $x of ' . NeedsUntyped::class . '::__construct()');
        $container->get(NeedsUntyped::class);
    }

    #[Test]
    public function autowire_throwsOnUnregisteredInterfaceDependency(): void
    {
        $container = (new ContainerBuilder())->autowire(NeedsInterface::class)->build();
        $this->expectException(ContainerException::class);
        $this->expectExceptionMessage('Cannot autowire parameter $greeter of ' . NeedsInterface::class);
        $container->get(NeedsInterface::class);
    }

    #[Test]
    public function autowire_throwsOnUnregisteredAbstractDependency(): void
    {
        $container = (new ContainerBuilder())->autowire(NeedsAbstract::class)->build();
        $this->expectException(ContainerException::class);
        $this->expectExceptionMessage('for type ' . AbstractThing::class . '.');
        $container->get(NeedsAbstract::class);
    }

    #[Test]
    public function autowire_detectsCycleAmongUnregisteredClasses(): void
    {
        $container = (new ContainerBuilder())->autowire(NeedsCycle::class)->build();
        $this->expectException(ContainerException::class);
        $this->expectExceptionMessage(
            'Circular dependency detected: ' . NeedsCycle::class . ' -> ' . CycleA::class . ' -> ' . CycleB::class
            . ' -> ' . CycleA::class . '.',
        );
        $container->get(NeedsCycle::class);
    }

    #[Test]
    public function autowire_detectsCycleThroughRegisteredClass(): void
    {
        $container = (new ContainerBuilder())->autowire(CycleA::class)->build();
        $this->expectException(ContainerException::class);
        $this->expectExceptionMessage('Circular dependency detected: ' . CycleA::class . ' -> ' . CycleA::class . '.');
        $container->get(CycleA::class);
    }

    #[Test]
    public function autowire_wrapsConstructorException(): void
    {
        $container = (new ContainerBuilder())->autowire(Throwing::class)->build();

        try {
            $container->get(Throwing::class);
            self::fail('Expected exception.');
        } catch (ContainerException $e) {
            self::assertSame('Failed to resolve service "' . Throwing::class . '": ctor failed', $e->getMessage());
            self::assertInstanceOf(LogicException::class, $e->getPrevious());
        }
    }

    #[Test]
    public function autowire_propagatesNotFoundFromNestedGet(): void
    {
        $container = (new ContainerBuilder())
            ->singleton(Leaf::class, static fn (IContainer $c): mixed => $c->get('missing'))
            ->autowire(Greeter::class)
            ->build();
        $this->expectException(NotFoundException::class);
        $container->get(Greeter::class);
    }

    #[Test]
    public function autowire_rejectsUnknownClass(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Class "Nope\Missing" does not exist.');
        (new ContainerBuilder())->autowire('Nope\Missing');
    }

    #[Test]
    public function autowire_rejectsNonInstantiableClass(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Class "' . PrivateConstructor::class . '" is not instantiable.');
        (new ContainerBuilder())->autowire(PrivateConstructor::class);
    }

    #[Test]
    public function autowire_rejectsInterface(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Class "' . GreeterInterface::class . '" is not instantiable.');
        (new ContainerBuilder())->autowire(GreeterInterface::class);
    }

    #[Test]
    public function autowire_rejectsDuplicateId(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Service "' . Leaf::class . '" is already registered.');
        (new ContainerBuilder())->autowire(Leaf::class)->autowire(Leaf::class);
    }
}
