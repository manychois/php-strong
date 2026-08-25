<?php

declare(strict_types=1);

namespace Manychois\PhpStrongTests\DependencyInjection;

use InvalidArgumentException;
use Manychois\PhpStrong\DependencyInjection\Container;
use Manychois\PhpStrong\DependencyInjection\ContainerBuilder;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use stdClass;

final class ContainerBuilderTest extends TestCase
{
    #[Test]
    public function build_returnsContainerWithRegisteredIds(): void
    {
        $builder = new ContainerBuilder();
        $result = $builder
            ->singleton('a', static fn (): string => 'A')
            ->factory('b', static fn (): string => 'B')
            ->singleton('c', static fn (): string => 'C');
        self::assertSame($builder, $result);

        $container = $builder->build();
        self::assertInstanceOf(Container::class, $container);
        self::assertTrue($container->has('a'));
        self::assertTrue($container->has('b'));
        self::assertTrue($container->has('c'));
        self::assertFalse($container->has('d'));
    }

    #[Test]
    public function build_emptyBuilderYieldsEmptyContainer(): void
    {
        $container = (new ContainerBuilder())->build();
        self::assertFalse($container->has('a'));
    }

    #[Test]
    public function singleton_throwsOnDuplicateId(): void
    {
        $builder = (new ContainerBuilder())->singleton('a', static fn (): string => 'A');
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Service "a" is already registered.');
        $builder->singleton('a', static fn (): string => 'A');
    }

    #[Test]
    public function factory_throwsOnDuplicateId(): void
    {
        $builder = (new ContainerBuilder())->singleton('a', static fn (): int => 1);
        $this->expectException(InvalidArgumentException::class);
        $builder->factory('a', static fn (): string => 'A');
    }

    #[Test]
    public function alias_forwardsToTargetAndSharesItsInstance(): void
    {
        $container = (new ContainerBuilder())
            ->singleton('impl', static fn (): stdClass => new stdClass())
            ->alias('iface', 'impl')
            ->build();

        self::assertTrue($container->has('iface'));
        self::assertSame($container->get('impl'), $container->get('iface'));
    }

    #[Test]
    public function alias_followsTargetLifetime(): void
    {
        $container = (new ContainerBuilder())
            ->factory('impl', static fn (): stdClass => new stdClass())
            ->alias('iface', 'impl')
            ->build();

        self::assertNotSame($container->get('iface'), $container->get('iface'));
    }

    #[Test]
    public function alias_mayBeRegisteredBeforeTargetAndResolveViaParent(): void
    {
        $parent = (new ContainerBuilder())->singleton('p', static fn (): string => 'P')->build();
        $container = (new ContainerBuilder($parent))
            ->alias('a', 'b')
            ->singleton('b', static fn (): string => 'B')
            ->alias('pp', 'p')
            ->build();

        self::assertSame('B', $container->get('a'));
        self::assertSame('P', $container->get('pp'));
    }

    #[Test]
    public function alias_throwsOnDuplicateId(): void
    {
        $builder = (new ContainerBuilder())->singleton('a', static fn (): string => 'A');
        $this->expectException(InvalidArgumentException::class);
        $builder->alias('a', 'b');
    }

    #[Test]
    public function alias_throwsOnSelfReference(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Alias "a" cannot target itself.');
        (new ContainerBuilder())->alias('a', 'a');
    }

    #[Test]
    public function build_throwsWhenAliasTargetIsUnknown(): void
    {
        $builder = (new ContainerBuilder())->alias('a', 'missing');
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Alias "a" targets unregistered service "missing".');
        $builder->build();
    }

    #[Test]
    public function build_laterRegistrationsDoNotAffectBuiltContainer(): void
    {
        $builder = new ContainerBuilder();
        $container = $builder->build();
        $builder->singleton('a', static fn (): int => 1);
        self::assertFalse($container->has('a'));
    }
}
