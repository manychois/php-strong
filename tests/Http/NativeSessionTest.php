<?php

declare(strict_types=1);

namespace Manychois\PhpStrongTests\Http;

use InvalidArgumentException;
use Manychois\PhpStrong\Http\NativeSession;
use Manychois\PhpStrong\Http\NativeSessionOptions;
use Manychois\PhpStrong\Http\SameSite;
use Manychois\PhpStrong\Http\SessionSerializer;
use Override;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class NativeSessionTest extends TestCase
{
    #[Override]
    protected function setUp(): void
    {
        session_save_path(sys_get_temp_dir());
        if (session_status() === \PHP_SESSION_ACTIVE) {
            $_SESSION = [];
            session_destroy();
        }
        $_SESSION = [];
    }

    #[Override]
    protected function tearDown(): void
    {
        if (session_status() === \PHP_SESSION_ACTIVE) {
            $_SESSION = [];
            session_destroy();
        }
        $_SESSION = [];
    }

    #[Test]
    public function constructingDoesNotStartTheSession(): void
    {
        $session = new NativeSession();

        static::assertFalse($session->isStarted());
        static::assertSame('', $session->id());
        static::assertSame(\PHP_SESSION_NONE, session_status());
    }

    #[Test]
    public function optionsAreAppliedWhenTheSessionStarts(): void
    {
        $session = new NativeSession(new NativeSessionOptions(
            name: 'app_session',
            savePath: sys_get_temp_dir(),
            cookieLifetime: 3600,
            cookiePath: '/app',
            cookieDomain: 'example.com',
            cookieSecure: true,
            cookieHttpOnly: true,
            cookieSameSite: SameSite::Strict,
            gcMaxLifetime: 1440,
            serializeHandler: SessionSerializer::PhpSerialize,
            ini: ['session.gc_probability' => 1],
        ));
        $session->set('a', 1);

        static::assertSame('app_session', session_name());
        static::assertSame(sys_get_temp_dir(), session_save_path());
        static::assertSame([
            'lifetime' => 3600,
            'path' => '/app',
            'domain' => 'example.com',
            'secure' => true,
            'partitioned' => false,
            'httponly' => true,
            'samesite' => 'Strict',
        ], session_get_cookie_params());
        static::assertSame('1440', ini_get('session.gc_maxlifetime'));
        static::assertSame('php_serialize', ini_get('session.serialize_handler'));
        static::assertSame('1', ini_get('session.gc_probability'));
        static::assertSame('1', ini_get('session.use_strict_mode'));
    }

    #[Test]
    public function readingStartsTheSession(): void
    {
        $session = new NativeSession();

        static::assertFalse($session->has('anything'));
        static::assertTrue($session->isStarted());
        static::assertNotSame('', $session->id());
    }

    #[Test]
    public function writingStartsTheSession(): void
    {
        $session = new NativeSession();
        $session->set('a', 1);

        static::assertTrue($session->isStarted());
        static::assertSame(1, $_SESSION['a']);
    }

    #[Test]
    public function valuesAreReadWithTheTypedAccessors(): void
    {
        $session = new NativeSession();
        $session->set('user', ['name' => 'Ann', 'age' => '42', 'admin' => 'yes']);

        static::assertSame('Ann', $session->string('user.name'));
        static::assertSame(42, $session->asInt('user.age'));
        static::assertTrue($session->asBool('user.admin'));
        static::assertNull($session->nullString('user.email'));
    }

    #[Test]
    public function setCreatesMissingSegmentsAsArrays(): void
    {
        $session = new NativeSession();
        $session->set('a.b.c', 'x');

        static::assertSame(['a' => ['b' => ['c' => 'x']]], $session->entries());
    }

    #[Test]
    public function setThrowsWhenASegmentHoldsANonArray(): void
    {
        $session = new NativeSession();
        $session->set('a', 'x');

        $this->expectException(InvalidArgumentException::class);

        $session->set('a.b', 'y');
    }

    #[Test]
    public function setWritesToAnExistingLiteralKey(): void
    {
        $session = new NativeSession();
        $session->set('a', ['b' => 'kept']);
        $_SESSION['a.b'] = 'x';
        $session->set('a.b', 'y');

        static::assertSame(['a' => ['b' => 'kept'], 'a.b' => 'y'], $session->entries());
    }

    #[Test]
    public function setRejectsANumericKey(): void
    {
        $session = new NativeSession();

        $this->expectException(InvalidArgumentException::class);

        $session->set('0', 'x');
    }

    #[Test]
    public function setRejectsANumericTopLevelSegment(): void
    {
        $session = new NativeSession();

        $this->expectException(InvalidArgumentException::class);

        $session->set('0.b', 'x');
    }

    #[Test]
    public function removeDeletesAValue(): void
    {
        $session = new NativeSession();
        $session->set('a.b', 'x');
        $session->set('a.c', 'y');
        $session->remove('a.b');

        static::assertSame(['a' => ['c' => 'y']], $session->entries());
    }

    #[Test]
    public function removeIgnoresAnAbsentPath(): void
    {
        $session = new NativeSession();
        $session->set('a', 'x');
        $session->remove('b.c');
        $session->remove('a.b');

        static::assertSame(['a' => 'x'], $session->entries());
    }

    #[Test]
    public function clearEmptiesTheDataAndKeepsTheSession(): void
    {
        $session = new NativeSession();
        $session->set('a', 'x');
        $id = $session->id();
        $session->clear();

        static::assertSame([], $session->entries());
        static::assertTrue($session->isStarted());
        static::assertSame($id, $session->id());
    }

    #[Test]
    public function regenerateChangesTheIdAndKeepsTheData(): void
    {
        $session = new NativeSession();
        $session->set('a', 'x');
        $old = $session->id();
        $session->regenerate();

        static::assertNotSame($old, $session->id());
        static::assertSame('x', $session->string('a'));
    }

    #[Test]
    public function destroyEndsTheSession(): void
    {
        $session = new NativeSession();
        $session->set('a', 'x');
        $session->destroy();

        static::assertFalse($session->isStarted());
        static::assertSame('', $session->id());
        static::assertSame([], $_SESSION);
    }

    #[Test]
    public function destroyIgnoresASessionWhichNeverStarted(): void
    {
        $session = new NativeSession();
        $session->destroy();

        static::assertFalse($session->isStarted());
    }

    #[Test]
    public function removeDeletesAnExistingLiteralKey(): void
    {
        $session = new NativeSession();
        $session->set('a', 'keep');
        $_SESSION['a.b'] = 'x';
        $session->remove('a.b');

        static::assertSame(['a' => 'keep'], $session->entries());
    }

    #[Test]
    public function entriesKeysAndCountDescribeTheSessionData(): void
    {
        $session = new NativeSession();
        $session->set('a', 1);
        $session->set('b', 2);

        static::assertSame(['a' => 1, 'b' => 2], $session->entries());
        static::assertSame(['a', 'b'], $session->keys());
        static::assertCount(2, $session);
    }

    #[Test]
    public function readerReturnsADetachedReaderOverTheSubtree(): void
    {
        $session = new NativeSession();
        $session->set('cart.item', 'book');
        $cart = $session->reader('cart');

        static::assertSame('book', $cart->string('item'));
        static::assertNotInstanceOf(NativeSession::class, $cart);
    }
}
