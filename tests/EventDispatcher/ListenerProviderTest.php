<?php

declare(strict_types=1);

namespace Manychois\PhpStrongTests\EventDispatcher;

use InvalidArgumentException;
use Manychois\PhpStrong\EventDispatcher\EventDispatcher;
use Manychois\PhpStrong\EventDispatcher\ListenerProvider;
use Override;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface as IContainer;
use Psr\Container\NotFoundExceptionInterface as INotFoundException;
use RuntimeException;
use stdClass;

interface AnimalEventInterface
{
}

class AnimalEvent implements AnimalEventInterface
{
}

final class DogEvent extends AnimalEvent
{
}

final class ListenerProviderTest extends TestCase
{
    #[Test]
    public function on_returnsTheProviderForChaining(): void
    {
        $provider = new ListenerProvider();

        self::assertSame($provider, $provider->on(DogEvent::class, static function (DogEvent $e): void {
        }));
    }

    #[Test]
    public function getListenersForEvent_sortsByPriorityThenRegistrationOrder(): void
    {
        $calls = [];
        $record = static function (string $name) use (&$calls): callable {
            return static function (DogEvent $e) use (&$calls, $name): void {
                $calls[] = $name;
            };
        };

        $provider = new ListenerProvider();
        $provider->on(DogEvent::class, $record('low'), -5);
        $provider->on(DogEvent::class, $record('mid-first'), 10);
        $provider->on(DogEvent::class, $record('mid-second'), 10);
        $provider->on(DogEvent::class, $record('high'), 100);

        foreach ($provider->getListenersForEvent(new DogEvent()) as $listener) {
            $listener(new DogEvent());
        }

        self::assertSame(['high', 'mid-first', 'mid-second', 'low'], $calls);
    }

    #[Test]
    public function getListenersForEvent_matchesParentClassAndInterfaceUnderOnePrioritySort(): void
    {
        $calls = [];
        $record = static function (string $name) use (&$calls): callable {
            return static function (object $e) use (&$calls, $name): void {
                $calls[] = $name;
            };
        };

        $provider = new ListenerProvider();
        $provider->on(AnimalEventInterface::class, $record('interface'), 100);
        $provider->on(AnimalEvent::class, $record('parent'), 50);
        $provider->on(DogEvent::class, $record('exact'), 10);

        foreach ($provider->getListenersForEvent(new DogEvent()) as $listener) {
            $listener(new DogEvent());
        }

        self::assertSame(['interface', 'parent', 'exact'], $calls);
    }

    #[Test]
    public function getListenersForEvent_ignoresUnrelatedEvents(): void
    {
        $provider = new ListenerProvider();
        $provider->on(DogEvent::class, static function (DogEvent $e): void {
        });

        self::assertSame([], [...$provider->getListenersForEvent(new stdClass())]);
    }

    #[Test]
    public function on_rejectsAnUnknownEventType(): void
    {
        $provider = new ListenerProvider();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Event type "No\Such\Type" is not an existing class or interface.');

        $provider->on('No\Such\Type', static function (object $e): void {
        });
    }

    #[Test]
    public function on_callsAnInstanceMethodListenerDirectly(): void
    {
        $spy = new ListenerSpy();
        $provider = new ListenerProvider();
        $provider->on(DogEvent::class, [$spy, 'handle']);

        foreach ($provider->getListenersForEvent(new DogEvent()) as $listener) {
            $listener(new DogEvent());
        }

        self::assertSame(1, $spy->calls);
    }

    #[Test]
    public function on_rejectsAnInstanceListenerWithoutThatMethod(): void
    {
        $provider = new ListenerProvider();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The array listener is not callable.');

        $provider->on(DogEvent::class, [new ListenerSpy(), 'noSuchMethod']);
    }

    #[Test]
    public function on_rejectsAMalformedArrayListener(): void
    {
        $provider = new ListenerProvider();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('An array listener must be [$target, $method] with a non-empty method name.');

        $provider->on(DogEvent::class, ['only-one-element']);
    }

    #[Test]
    public function on_withoutContainerRejectsANonStaticServiceReference(): void
    {
        $provider = new ListenerProvider();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The array listener is not callable.');

        $provider->on(DogEvent::class, [ListenerSpy::class, 'handle']);
    }

    #[Test]
    public function on_withoutContainerAcceptsAStaticMethodReference(): void
    {
        ListenerSpy::$staticCalls = 0;
        $provider = new ListenerProvider();
        $provider->on(DogEvent::class, [ListenerSpy::class, 'handleStatically']);

        foreach ($provider->getListenersForEvent(new DogEvent()) as $listener) {
            $listener(new DogEvent());
        }

        self::assertSame(1, ListenerSpy::$staticCalls);
    }

    #[Test]
    public function on_serviceReferenceResolvesOnEveryDispatchNotAtRegistration(): void
    {
        $spy = new ListenerSpy();
        $container = new FakeContainer(['spy' => $spy]);
        $provider = new ListenerProvider($container);
        $provider->on(DogEvent::class, ['spy', 'handle']);

        self::assertSame(0, $container->gets, 'Registration must not resolve the service.');

        $dispatcher = new EventDispatcher($provider);
        $dispatcher->dispatch(new DogEvent());
        $dispatcher->dispatch(new DogEvent());

        self::assertSame(2, $spy->calls);
        self::assertSame(2, $container->gets, 'The service is resolved once per dispatch.');
    }

    #[Test]
    public function on_serviceReferenceReceivesTheDispatchedEvent(): void
    {
        $spy = new ListenerSpy();
        $provider = new ListenerProvider(new FakeContainer(['spy' => $spy]));
        $provider->on(DogEvent::class, ['spy', 'handle']);
        $event = new DogEvent();

        (new EventDispatcher($provider))->dispatch($event);

        self::assertSame($event, $spy->lastEvent);
    }

    #[Test]
    public function getListenersForEvent_unknownServiceIdSurfacesTheContainerException(): void
    {
        $provider = new ListenerProvider(new FakeContainer([]));
        $provider->on(DogEvent::class, ['missing', 'handle']);

        $this->expectException(INotFoundException::class);

        (new EventDispatcher($provider))->dispatch(new DogEvent());
    }

    #[Test]
    public function getListenersForEvent_nonObjectServiceThrowsAtDispatch(): void
    {
        $provider = new ListenerProvider(new FakeContainer(['spy' => 'not-an-object']));
        $provider->on(DogEvent::class, ['spy', 'handle']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Service "spy" cannot handle the event with method "handle".');

        (new EventDispatcher($provider))->dispatch(new DogEvent());
    }

    #[Test]
    public function getListenersForEvent_serviceWithoutTheMethodThrowsAtDispatch(): void
    {
        $provider = new ListenerProvider(new FakeContainer(['spy' => new ListenerSpy()]));
        $provider->on(DogEvent::class, ['spy', 'noSuchMethod']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Service "spy" cannot handle the event with method "noSuchMethod".');

        (new EventDispatcher($provider))->dispatch(new DogEvent());
    }

    #[Test]
    public function getListenersForEvent_repeatedCallsReturnTheSameOrder(): void
    {
        $provider = new ListenerProvider();
        $first = static function (DogEvent $e): void {
        };
        $second = static function (DogEvent $e): void {
        };
        $provider->on(DogEvent::class, $first, 10);
        $provider->on(DogEvent::class, $second);

        $one = [...$provider->getListenersForEvent(new DogEvent())];
        $two = [...$provider->getListenersForEvent(new DogEvent())];

        self::assertSame([$first, $second], $one);
        self::assertSame($one, $two);
    }

    #[Test]
    public function on_afterAResolvedDispatchIsVisibleOnTheNextCall(): void
    {
        $provider = new ListenerProvider();
        $provider->on(DogEvent::class, static function (DogEvent $e): void {
        });

        self::assertCount(1, [...$provider->getListenersForEvent(new DogEvent())]);

        $provider->on(DogEvent::class, static function (DogEvent $e): void {
        }, 100);

        self::assertCount(2, [...$provider->getListenersForEvent(new DogEvent())]);
    }

    #[Test]
    public function on_registeringAnotherTypeInvalidatesAnUnrelatedCachedList(): void
    {
        $provider = new ListenerProvider();
        $provider->on(DogEvent::class, static function (DogEvent $e): void {
        });

        self::assertCount(1, [...$provider->getListenersForEvent(new DogEvent())]);

        $provider->on(AnimalEventInterface::class, static function (object $e): void {
        });

        self::assertCount(2, [...$provider->getListenersForEvent(new DogEvent())]);
    }
}

final class ListenerSpy
{
    public static int $staticCalls = 0;

    public int $calls = 0;

    public ?object $lastEvent = null;

    public static function handleStatically(object $event): void
    {
        self::$staticCalls++;
    }

    public function handle(object $event): void
    {
        $this->calls++;
        $this->lastEvent = $event;
    }
}

final class FakeContainer implements IContainer
{
    public int $gets = 0;

    /**
     * @param array<string, mixed> $services
     */
    public function __construct(private readonly array $services)
    {
    }

    #[Override]
    public function get(string $id): mixed
    {
        $this->gets++;
        if (!array_key_exists($id, $this->services)) {
            throw new ServiceNotFoundException(sprintf('Service "%s" is not registered.', $id));
        }

        return $this->services[$id];
    }

    #[Override]
    public function has(string $id): bool
    {
        return array_key_exists($id, $this->services);
    }
}

final class ServiceNotFoundException extends RuntimeException implements INotFoundException
{
}
