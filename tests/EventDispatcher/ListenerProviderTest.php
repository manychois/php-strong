<?php

declare(strict_types=1);

namespace Manychois\PhpStrongTests\EventDispatcher;

use InvalidArgumentException;
use Manychois\PhpStrong\EventDispatcher\ListenerProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
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
}
