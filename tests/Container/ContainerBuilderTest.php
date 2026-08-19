<?php

declare(strict_types=1);

namespace Manychois\PhpStrongTests\Container;

use InvalidArgumentException;
use Manychois\PhpStrong\Container\Container;
use Manychois\PhpStrong\Container\ContainerBuilder;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

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
    public function build_laterRegistrationsDoNotAffectBuiltContainer(): void
    {
        $builder = new ContainerBuilder();
        $container = $builder->build();
        $builder->singleton('a', static fn (): int => 1);
        self::assertFalse($container->has('a'));
    }
}
