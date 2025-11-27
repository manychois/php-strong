<?php

declare(strict_types=1);

namespace Manychois\PhpStrongTests\Web;

use Manychois\PhpStrong\Web\PhpSession;
use PHPUnit\Framework\TestCase;

class PhpSessionTest extends TestCase
{
    /**
     * @runInSeparateProcess
     */
    public function testConstructor(): void
    {
        $session = new PhpSession();
        self::assertSame('PHPSESSID', $session->name);
        self::assertSame(\PHP_SESSION_NONE, \session_status());
    }

    /**
     * @runInSeparateProcess
     */
    public function testGetAndSet(): void
    {
        $session = new PhpSession();
        $session->set('key', 'value');
        self::assertSame('value', $session->get('key'));
        // phpcs:ignore SlevomatCodingStandard.Variables.DisallowSuperGlobalVariable.DisallowedSuperGlobalVariable
        self::assertSame('value', $_SESSION['key']);
    }

    /**
     * @runInSeparateProcess
     */
    public function testHas(): void
    {
        $session = new PhpSession();
        $session->set('key', 'value');
        self::assertTrue($session->has('key'));
        self::assertFalse($session->has('missing'));
    }

    /**
     * @runInSeparateProcess
     */
    public function testDelete(): void
    {
        $session = new PhpSession();
        $session->set('key', 'value');
        $session->delete('key');
        self::assertFalse($session->has('key'));
        // phpcs:ignore SlevomatCodingStandard.Variables.DisallowSuperGlobalVariable.DisallowedSuperGlobalVariable
        self::assertArrayNotHasKey('key', $_SESSION);
    }

    /**
     * @runInSeparateProcess
     */
    public function testClear(): void
    {
        $session = new PhpSession();
        $session->set('key1', 'value1');
        $session->set('key2', 'value2');
        $session->clear();
        // phpcs:ignore SlevomatCodingStandard.Variables.DisallowSuperGlobalVariable.DisallowedSuperGlobalVariable
        self::assertEmpty($_SESSION);
        self::assertFalse($session->has('key1'));
    }

    /**
     * @runInSeparateProcess
     */
    public function testDestroy(): void
    {
        $session = new PhpSession();
        $session->set('key', 'value');
        $session->destroy();
        // phpcs:ignore SlevomatCodingStandard.Variables.DisallowSuperGlobalVariable.DisallowedSuperGlobalVariable
        self::assertEmpty($_SESSION);
        self::assertSame(\PHP_SESSION_NONE, \session_status());
    }
}
