<?php

declare(strict_types=1);

namespace Manychois\PhpStrongTests\DependencyInjection;

use Countable;
use InvalidArgumentException;
use Manychois\PhpStrong\DependencyInjection\ContainerBuilder;
use Manychois\PhpStrong\DependencyInjection\ContainerException;
use Manychois\PhpStrongTests\DependencyInjection\Fixtures\NoConstructor;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface as IContainer;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use RuntimeException;
use stdClass;

require_once __DIR__ . '/Fixtures/Autowire.php';

final class AwareTest extends TestCase
{
    #[Test]
    public function aware_configuresMatchingObjectsFromAnyDefinition(): void
    {
        $logger = new NullLogger();
        $container = (new ContainerBuilder())
            ->singleton(LoggerInterface::class, static fn (): LoggerInterface => $logger)
            ->singleton('closure', static fn (): object => self::loggerAware())
            ->autowire(NoConstructor::class)
            ->aware(LoggerAwareInterface::class, static function (LoggerAwareInterface $o, IContainer $c): void {
                $o->setLogger($c->get(LoggerInterface::class));
            })
            ->build();

        $obj = $container->get('closure');
        self::assertInstanceOf(LoggerAwareInterface::class, $obj);
        self::assertSame($logger, $obj->logger);
        self::assertInstanceOf(NoConstructor::class, $container->get(NoConstructor::class));
    }

    #[Test]
    public function aware_runsOncePerProducedInstance(): void
    {
        $calls = 0;
        $container = (new ContainerBuilder())
            ->singleton('single', static fn (): stdClass => new stdClass())
            ->factory('each', static fn (): stdClass => new stdClass())
            ->alias('aliased', 'single')
            ->aware(stdClass::class, static function () use (&$calls): void {
                $calls++;
            })
            ->build();

        $container->get('single');
        $container->get('single');
        $container->get('aliased');
        self::assertSame(1, $calls);
        $container->get('each');
        $container->get('each');
        self::assertSame(3, $calls);
    }

    #[Test]
    public function aware_appliesMultipleRulesInRegistrationOrder(): void
    {
        $container = (new ContainerBuilder())
            ->factory('obj', static fn (): stdClass => new stdClass())
            ->aware(stdClass::class, static function (stdClass $o): void {
                $o->trail = 'a';
            })
            ->aware(Countable::class, static function (): void {
                self::fail('Countable rule must not run for stdClass.');
            })
            ->aware(stdClass::class, static function (stdClass $o): void {
                $o->trail .= 'b';
            })
            ->build();

        self::assertSame('ab', $container->get('obj')->trail);
    }

    #[Test]
    public function aware_skipsNonObjectValues(): void
    {
        $container = (new ContainerBuilder())
            ->singleton('s', static fn (): string => 'text')
            ->aware(stdClass::class, static function (): void {
                self::fail('Must not run for non-objects.');
            })
            ->build();

        self::assertSame('text', $container->get('s'));
    }

    #[Test]
    public function aware_wrapsConfigurerException(): void
    {
        $container = (new ContainerBuilder())
            ->singleton('obj', static fn (): stdClass => new stdClass())
            ->aware(stdClass::class, static fn (): never => throw new RuntimeException('cfg'))
            ->build();

        try {
            $container->get('obj');
            self::fail('Expected exception.');
        } catch (ContainerException $e) {
            self::assertSame('Failed to resolve service "obj": cfg', $e->getMessage());
            self::assertInstanceOf(RuntimeException::class, $e->getPrevious());
        }
    }

    #[Test]
    public function aware_detectsCycleThroughConfigurer(): void
    {
        $container = (new ContainerBuilder())
            ->singleton('obj', static fn (): stdClass => new stdClass())
            ->aware(stdClass::class, static function (stdClass $o, IContainer $c): void {
                $c->get('obj');
            })
            ->build();

        $this->expectException(ContainerException::class);
        $this->expectExceptionMessage('Circular dependency detected: obj -> obj.');
        $container->get('obj');
    }

    #[Test]
    public function aware_childRulesDoNotApplyToParentServices(): void
    {
        $parent = (new ContainerBuilder())->singleton('p', static fn (): stdClass => new stdClass())->build();
        $child = (new ContainerBuilder($parent))
            ->singleton('c', static fn (): stdClass => new stdClass())
            ->aware(stdClass::class, static function (stdClass $o): void {
                $o->touched = true;
            })
            ->build();

        self::assertTrue($child->get('c')->touched);
        self::assertFalse(isset($child->get('p')->touched));
    }

    #[Test]
    public function aware_rejectsUnknownType(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Type "Nope\Missing" does not exist.');
        (new ContainerBuilder())->aware('Nope\Missing', static function (): void {
        });
    }

    private static function loggerAware(): LoggerAwareInterface
    {
        return new class implements LoggerAwareInterface {
            public ?LoggerInterface $logger = null;

            public function setLogger(LoggerInterface $logger): void
            {
                $this->logger = $logger;
            }
        };
    }
}
