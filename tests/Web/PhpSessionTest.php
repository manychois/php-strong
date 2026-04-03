<?php

declare(strict_types=1);

namespace Manychois\PhpStrongTests\Web;

use Manychois\PhpStrong\ArrayReader;
use Manychois\PhpStrong\Collections\StringMap;
use Manychois\PhpStrong\Web\PhpSession;
use OutOfBoundsException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use stdClass;
use UnexpectedValueException;

/**
 * Unit tests for {@see PhpSession}.
 */
final class PhpSessionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->resetSession();
    }

    protected function tearDown(): void
    {
        $this->resetSession();
        parent::tearDown();
    }

    private function resetSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_abort();
        }
        $_SESSION = [];
    }

    #[Test]
    public function at_returns_ArrayReader_for_nested_structure(): void
    {
        $session = new PhpSession();
        $session->set('profile', ['city' => 'Paris']);
        $inner = $session->at('profile');
        self::assertInstanceOf(ArrayReader::class, $inner);
        self::assertSame('Paris', $inner->get('city'));
    }

    #[Test]
    public function at_throws_when_value_is_scalar(): void
    {
        $session = new PhpSession();
        $session->set('count', 3);
        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('The path "count" is not an array or object');
        $session->at('count');
    }

    #[Test]
    public function clear_removes_all_session_keys(): void
    {
        $session = new PhpSession();
        $session->set('a', 1);
        $session->set('b', 2);
        $session->clear();
        $another = new PhpSession();
        self::assertNull($another->getOrNull('a'));
        self::assertNull($another->getOrNull('b'));
    }

    #[Test]
    public function get_nested_path_reads_from_session(): void
    {
        $session = new PhpSession();
        $session->set('x', ['y' => ['z' => 7]]);
        self::assertSame(7, $session->get('x.y.z'));
    }

    #[Test]
    public function get_throws_OutOfBounds_when_path_missing(): void
    {
        $session = new PhpSession();
        $this->expectException(OutOfBoundsException::class);
        $this->expectExceptionMessage('Path "missing" not found');
        $session->get('missing');
    }

    #[Test]
    public function options_passed_to_constructor_apply_on_session_start(): void
    {
        $session = new PhpSession(['cache_limiter' => 'private']);
        $session->set('k', 1);
        self::assertSame('private', session_cache_limiter());
    }

    #[Test]
    public function remove_unsets_top_level_key(): void
    {
        $session = new PhpSession();
        $session->set('temp', 'gone');
        $session->remove('temp');
        self::assertNull($session->getOrNull('temp'));
    }

    #[Test]
    public function set_and_get_round_trip(): void
    {
        $session = new PhpSession();
        $obj = new stdClass();
        $obj->id = 9;
        $session->set('entity', $obj);
        self::assertSame($obj, $session->get('entity'));
    }

    #[Test]
    public function with_accepts_StringMap_overrides(): void
    {
        $session = new PhpSession();
        $session->set('keep', 1);
        $map = new StringMap([]);
        $map->add('newKey', 2);
        $next = $session->with($map);
        self::assertNotSame($session, $next);
        self::assertSame(1, $next->get('keep'));
        self::assertSame(2, $next->get('newKey'));
    }

    #[Test]
    public function with_applies_array_overrides_and_returns_new_instance(): void
    {
        $session = new PhpSession();
        $session->set('a', 0);
        $next = $session->with(['a' => 10, 'b' => 20]);
        self::assertNotSame($session, $next);
        self::assertSame(10, $next->get('a'));
        self::assertSame(20, $next->get('b'));
    }

    #[Test]
    public function with_null_unsets_keys(): void
    {
        $session = new PhpSession();
        $session->set('drop', 1);
        $session->set('keep', 2);
        $session->with(['drop' => null]);
        self::assertNull($session->getOrNull('drop'));
        self::assertSame(2, $session->get('keep'));
    }
}
