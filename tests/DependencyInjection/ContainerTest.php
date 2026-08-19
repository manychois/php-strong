<?php

declare(strict_types=1);

namespace Manychois\PhpStrongTests\DependencyInjection;

use Manychois\PhpStrong\DependencyInjection\ContainerBuilder;
use Manychois\PhpStrong\DependencyInjection\ContainerException;
use Manychois\PhpStrong\DependencyInjection\NotFoundException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerExceptionInterface as IContainerException;
use Psr\Container\ContainerInterface as IContainer;
use Psr\Container\NotFoundExceptionInterface as INotFoundException;
use RuntimeException;
use stdClass;

final class ContainerTest extends TestCase
{
    #[Test]
    public function get_sharedReturnsSameInstanceAndInvokesFactoryOnce(): void
    {
        $calls = 0;
        $container = (new ContainerBuilder())
            ->singleton('obj', static function () use (&$calls): stdClass {
                $calls++;

                return new stdClass();
            })
            ->build();

        self::assertSame(0, $calls);
        $first = $container->get('obj');
        self::assertSame($first, $container->get('obj'));
        self::assertSame(1, $calls);
    }

    #[Test]
    public function get_factoryReturnsNewInstanceEachTime(): void
    {
        $container = (new ContainerBuilder())
            ->factory('obj', static fn (): stdClass => new stdClass())
            ->build();

        self::assertNotSame($container->get('obj'), $container->get('obj'));
    }

    #[Test]
    public function get_singletonReturnsNullValue(): void
    {
        $value = new stdClass();
        $container = (new ContainerBuilder())->singleton('obj', static fn (): mixed => $value)->singleton('null', static fn (): mixed => null)->build();

        self::assertSame($value, $container->get('obj'));
        self::assertNull($container->get('null'));
        self::assertTrue($container->has('null'));
    }

    #[Test]
    public function get_sharedNullResultIsCached(): void
    {
        $calls = 0;
        $container = (new ContainerBuilder())
            ->singleton('n', static function () use (&$calls): mixed {
                $calls++;

                return null;
            })
            ->build();

        self::assertNull($container->get('n'));
        self::assertNull($container->get('n'));
        self::assertSame(1, $calls);
    }

    #[Test]
    public function get_passesContainerToFactory(): void
    {
        $container = (new ContainerBuilder())
            ->singleton('name', static fn (): string => 'world')
            ->singleton('greeting', static fn (IContainer $c): string => 'hello ' . $c->get('name'))
            ->build();

        self::assertSame('hello world', $container->get('greeting'));
    }

    #[Test]
    public function get_throwsNotFoundForUnknownId(): void
    {
        $container = (new ContainerBuilder())->build();

        try {
            $container->get('missing');
            self::fail('Expected exception.');
        } catch (NotFoundException $e) {
            self::assertInstanceOf(INotFoundException::class, $e);
            self::assertInstanceOf(ContainerException::class, $e);
            self::assertSame('Service "missing" is not registered.', $e->getMessage());
        }
    }

    #[Test]
    public function get_throwsOnCircularDependency(): void
    {
        $container = (new ContainerBuilder())
            ->singleton('a', static fn (IContainer $c): mixed => $c->get('b'))
            ->singleton('b', static fn (IContainer $c): mixed => $c->get('c'))
            ->factory('c', static fn (IContainer $c): mixed => $c->get('a'))
            ->build();

        try {
            $container->get('a');
            self::fail('Expected exception.');
        } catch (ContainerException $e) {
            self::assertInstanceOf(IContainerException::class, $e);
            self::assertNotInstanceOf(INotFoundException::class, $e);
            self::assertSame('Circular dependency detected: a -> b -> c -> a.', $e->getMessage());
        }
    }

    #[Test]
    public function get_wrapsFactoryException(): void
    {
        $inner = new RuntimeException('boom');
        $container = (new ContainerBuilder())
            ->singleton('bad', static fn (): never => throw $inner)
            ->build();

        try {
            $container->get('bad');
            self::fail('Expected exception.');
        } catch (ContainerException $e) {
            self::assertSame('Failed to resolve service "bad": boom', $e->getMessage());
            self::assertSame($inner, $e->getPrevious());
        }
    }

    #[Test]
    public function get_recoversAfterFailedResolution(): void
    {
        $attempt = 0;
        $container = (new ContainerBuilder())
            ->singleton('flaky', static function () use (&$attempt): string {
                $attempt++;
                if ($attempt === 1) {
                    throw new RuntimeException('first');
                }

                return 'ok';
            })
            ->build();

        try {
            $container->get('flaky');
        } catch (ContainerException) {
        }
        self::assertSame('ok', $container->get('flaky'));
    }

    #[Test]
    public function get_doesNotWrapNotFoundFromNestedGet(): void
    {
        $container = (new ContainerBuilder())
            ->singleton('a', static fn (IContainer $c): mixed => $c->get('missing'))
            ->build();

        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessage('Service "missing" is not registered.');
        $container->get('a');
    }

    #[Test]
    public function get_fallsBackToParent(): void
    {
        $pdo = new stdClass();
        $app = (new ContainerBuilder())->singleton('pdo', static fn (): mixed => $pdo)->build();
        $request = (new ContainerBuilder($app))
            ->singleton('session', static fn (IContainer $c): string => 'session:' . spl_object_id($c->get('pdo')))
            ->build();

        self::assertTrue($request->has('pdo'));
        self::assertFalse($request->has('missing'));
        self::assertSame($pdo, $request->get('pdo'));
        self::assertSame('session:' . spl_object_id($pdo), $request->get('session'));
        self::assertFalse($app->has('session'));
    }

    #[Test]
    public function get_childDefinitionShadowsParent(): void
    {
        $app = (new ContainerBuilder())->singleton('x', static fn (): string => 'app')->build();
        $request = (new ContainerBuilder($app))->singleton('x', static fn (): string => 'request')->build();

        self::assertSame('request', $request->get('x'));
        self::assertSame('app', $app->get('x'));
    }

    #[Test]
    public function get_childFactoriesReceiveChildContainer(): void
    {
        $app = (new ContainerBuilder())->build();
        $request = (new ContainerBuilder($app))
            ->singleton('req', static fn (): string => 'R')
            ->singleton('svc', static fn (IContainer $c): mixed => $c->get('req'))
            ->build();

        self::assertSame('R', $request->get('svc'));
    }

    #[Test]
    public function get_throwsNotFoundWhenMissingInParentToo(): void
    {
        $request = (new ContainerBuilder((new ContainerBuilder())->build()))->build();

        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessage('Service "missing" is not registered.');
        $request->get('missing');
    }

    #[Test]
    public function getInstance_returnsTypedObject(): void
    {
        $obj = new stdClass();
        $container = (new ContainerBuilder())->singleton(stdClass::class, static fn (): stdClass => $obj)->build();

        self::assertSame($obj, $container->getInstance(stdClass::class));
    }

    #[Test]
    public function getInstance_throwsWhenServiceIsNotInstanceOfClass(): void
    {
        $container = (new ContainerBuilder())->singleton(stdClass::class, static fn (): string => 'nope')->build();

        $this->expectException(ContainerException::class);
        $this->expectExceptionMessage('Service "stdClass" is not an instance of stdClass.');
        $container->getInstance(stdClass::class);
    }

    #[Test]
    public function getInstance_throwsNotFoundForUnknownId(): void
    {
        $container = (new ContainerBuilder())->build();

        $this->expectException(NotFoundException::class);
        $container->getInstance(stdClass::class);
    }

    #[Test]
    public function has_doesNotInvokeFactory(): void
    {
        $calls = 0;
        $container = (new ContainerBuilder())
            ->singleton('a', static function () use (&$calls): int {
                return ++$calls;
            })
            ->build();

        self::assertTrue($container->has('a'));
        self::assertSame(0, $calls);
    }
}
