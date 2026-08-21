<?php

declare(strict_types=1);

namespace Manychois\PhpStrongTests\EventDispatcher;

use Manychois\PhpStrong\EventDispatcher\StoppableEventTrait;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\StoppableEventInterface as IStoppableEvent;

final class StoppableEventTraitTest extends TestCase
{
    #[Test]
    public function isPropagationStopped_falseByDefault(): void
    {
        $event = new class implements IStoppableEvent {
            use StoppableEventTrait;
        };

        self::assertFalse($event->isPropagationStopped());
    }

    #[Test]
    public function stopPropagation_flipsTheFlagAndIsIdempotent(): void
    {
        $event = new class implements IStoppableEvent {
            use StoppableEventTrait;
        };

        $event->stopPropagation();
        self::assertTrue($event->isPropagationStopped());

        $event->stopPropagation();
        self::assertTrue($event->isPropagationStopped());
    }
}
