<?php

declare(strict_types=1);

namespace Manychois\PhpStrongTests\Container;

use ArrayObject;
use Countable;
use Exception;
use InvalidArgumentException;
use Manychois\PhpStrong\Container\StrongContainerWrapper;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface as IContainer;
use stdClass;
use UnexpectedValueException;

/**
 * Unit tests for StrongContainerWrapper.
 */
final class StrongContainerWrapperTest extends TestCase
{
    #[Test]
    public function get_delegatesToInnerContainer(): void
    {
        $value = new stdClass();
        /** @var IContainer&MockObject $inner */
        $inner = $this->createMock(IContainer::class);
        $inner->expects(self::once())
            ->method('get')
            ->with('service.id')
            ->willReturn($value);

        $wrapper = new StrongContainerWrapper($inner);

        self::assertSame($value, $wrapper->get('service.id'));
    }

    #[Test]
    public function getObject_returnsEntry_whenInstanceOfExpectedClass(): void
    {
        $value = new stdClass();
        /** @var IContainer&MockObject $inner */
        $inner = $this->createMock(IContainer::class);
        $inner->method('get')
            ->with('key')
            ->willReturn($value);

        $wrapper = new StrongContainerWrapper($inner);

        self::assertSame($value, $wrapper->getObject('key', stdClass::class));
    }

    #[Test]
    public function getObject_returnsEntry_whenInstanceOfExpectedInterface(): void
    {
        $value = new ArrayObject();
        /** @var IContainer&MockObject $inner */
        $inner = $this->createMock(IContainer::class);
        $inner->method('get')
            ->with('c')
            ->willReturn($value);

        $wrapper = new StrongContainerWrapper($inner);

        self::assertSame($value, $wrapper->getObject('c', Countable::class));
    }

    #[Test]
    public function getObject_returnsEntry_whenInstanceOfSuperClass(): void
    {
        $value = new InvalidArgumentException('x');
        /** @var IContainer&MockObject $inner */
        $inner = $this->createMock(IContainer::class);
        $inner->method('get')
            ->with('e')
            ->willReturn($value);

        $wrapper = new StrongContainerWrapper($inner);

        self::assertSame($value, $wrapper->getObject('e', Exception::class));
    }

    #[Test]
    public function getObject_throwsUnexpectedValue_whenEntryIsNotObject(): void
    {
        /** @var IContainer&MockObject $inner */
        $inner = $this->createMock(IContainer::class);
        $inner->method('get')
            ->with('bad')
            ->willReturn(3);

        $wrapper = new StrongContainerWrapper($inner);

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage(
            'Container entry "bad" is not an object; got int'
        );

        $wrapper->getObject('bad', stdClass::class);
    }

    #[Test]
    public function getObject_throwsUnexpectedValue_whenWrongType(): void
    {
        $value = new stdClass();
        /** @var IContainer&MockObject $inner */
        $inner = $this->createMock(IContainer::class);
        $inner->method('get')
            ->with('wrong')
            ->willReturn($value);

        $wrapper = new StrongContainerWrapper($inner);

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage(
            'Container entry "wrong" is not an instance of InvalidArgumentException; got stdClass'
        );

        $wrapper->getObject('wrong', InvalidArgumentException::class);
    }

    #[Test]
    public function has_delegatesToInnerContainer(): void
    {
        /** @var IContainer&MockObject $inner */
        $inner = $this->createMock(IContainer::class);
        $inner->expects(self::once())
            ->method('has')
            ->with('x')
            ->willReturn(true);

        $wrapper = new StrongContainerWrapper($inner);

        self::assertTrue($wrapper->has('x'));
    }
}
