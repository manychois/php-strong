<?php

declare(strict_types=1);

namespace Manychois\PhpStrongTests\Logging;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use stdClass;
use Manychois\PhpStrong\Logging\MessageInterpolator;

final class MessageInterpolatorTest extends TestCase
{
    #[Test]
    public function interpolate_replacesPlaceholders(): void
    {
        $result = (new MessageInterpolator())->interpolate('User {name} ({id}) active={active} x={missing}', [
            'name' => 'Bob',
            'id' => 42,
            'active' => true,
        ]);
        self::assertSame('User Bob (42) active=true x={missing}', $result);
    }

    #[Test]
    public function interpolate_stringifiesSpecialValues(): void
    {
        $date = new DateTimeImmutable('2026-01-02T03:04:05+00:00');
        $obj = new stdClass();
        $obj->a = 1;
        $result = (new MessageInterpolator())->interpolate('{n} {d} {o} {a} {f}', [
            'n' => null,
            'd' => $date,
            'o' => $obj,
            'a' => [1, 'x'],
            'f' => 1.5,
        ]);
        self::assertSame('null 2026-01-02T03:04:05+00:00 {"a":1} [1,"x"] 1.5', $result);
    }

    #[Test]
    public function interpolate_fallsBackToType_whenJsonFails(): void
    {
        $result = (new MessageInterpolator())->interpolate('{v}', ['v' => ["\xB1"]]);
        self::assertSame('[array]', $result);
    }

    #[Test]
    public function interpolate_returnsMessageUnchanged_withoutBraces(): void
    {
        self::assertSame('plain', (new MessageInterpolator())->interpolate('plain', ['a' => 1]));
    }
}
