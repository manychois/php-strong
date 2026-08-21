<?php

declare(strict_types=1);

namespace Manychois\PhpStrongTests\Events;

use LogicException;
use Manychois\PhpStrong\Events\EventDispatcher;
use Manychois\PhpStrong\Events\StoppableEventTrait;
use Override;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\ListenerProviderInterface as IListenerProvider;
use Psr\EventDispatcher\StoppableEventInterface as IStoppableEvent;
use stdClass;

final class EventDispatcherTest extends TestCase
{
    #[Test]
    public function dispatch_callsListenersInProviderOrderAndReturnsTheSameEvent(): void
    {
        $calls = [];
        $event = new stdClass();
        $provider = new FakeListenerProvider([
            static function (object $e) use (&$calls): void {
                $calls[] = 'first';
            },
            static function (object $e) use (&$calls): void {
                $calls[] = 'second';
            },
        ]);

        $returned = (new EventDispatcher($provider))->dispatch($event);

        self::assertSame($event, $returned);
        self::assertSame(['first', 'second'], $calls);
    }

    #[Test]
    public function dispatch_withoutListenersReturnsTheEventUntouched(): void
    {
        $event = new stdClass();

        self::assertSame($event, (new EventDispatcher(new FakeListenerProvider([])))->dispatch($event));
    }

    #[Test]
    public function dispatch_alreadyStoppedEventCallsNoListener(): void
    {
        $called = false;
        $event = new StoppableTestEvent();
        $event->stopPropagation();
        $provider = new FakeListenerProvider([
            static function (object $e) use (&$called): void {
                $called = true;
            },
        ]);

        (new EventDispatcher($provider))->dispatch($event);

        self::assertFalse($called);
    }

    #[Test]
    public function dispatch_stopPropagationPreventsLaterListeners(): void
    {
        $calls = [];
        $provider = new FakeListenerProvider([
            static function (StoppableTestEvent $e) use (&$calls): void {
                $calls[] = 'first';
                $e->stopPropagation();
            },
            static function (StoppableTestEvent $e) use (&$calls): void {
                $calls[] = 'second';
            },
        ]);

        (new EventDispatcher($provider))->dispatch(new StoppableTestEvent());

        self::assertSame(['first'], $calls);
    }

    #[Test]
    public function dispatch_nonStoppableEventRunsEveryListener(): void
    {
        $calls = 0;
        $listener = static function (object $e) use (&$calls): void {
            $calls++;
        };

        (new EventDispatcher(new FakeListenerProvider([$listener, $listener])))->dispatch(new stdClass());

        self::assertSame(2, $calls);
    }

    #[Test]
    public function dispatch_listenerExceptionPropagatesAndStopsLaterListeners(): void
    {
        $reached = false;
        $provider = new FakeListenerProvider([
            static function (object $e): void {
                throw new LogicException('listener failed');
            },
            static function (object $e) use (&$reached): void {
                $reached = true;
            },
        ]);

        try {
            (new EventDispatcher($provider))->dispatch(new stdClass());
            self::fail('Expected LogicException.');
        } catch (LogicException $ex) {
            self::assertSame('listener failed', $ex->getMessage());
        }

        self::assertFalse($reached);
    }
}

final class StoppableTestEvent implements IStoppableEvent
{
    use StoppableEventTrait;
}

final class FakeListenerProvider implements IListenerProvider
{
    /**
     * @param list<callable> $listeners
     */
    public function __construct(private readonly array $listeners)
    {
    }

    #[Override]
    public function getListenersForEvent(object $event): iterable
    {
        return $this->listeners;
    }
}
