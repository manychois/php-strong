# HTTP Response Emitter Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Send a PSR-7 `Response` to the SAPI — status line, headers, body — so the server role of this library finally reaches the browser.

**Architecture:** A one-method `ResponseEmitterInterface` with a single `SapiEmitter` implementation, mirroring the existing `SessionInterface`/`NativeSession` pair. `SapiEmitter` calls `header()` and `headers_sent()` *unqualified*, so a test-only declaration of those functions inside namespace `Manychois\PhpStrong\Http` intercepts them with zero indirection in production code. The body streams in fixed-size chunks so a large download never approaches `memory_limit`.

**Tech Stack:** PHP 8.5, PSR-7 (`psr/http-message`), PHPUnit 13, PHPStan max + strict rules, PHPCS with Slevomat.

**Spec:** `docs/superpowers/specs/2026-08-25-http-response-emitter-design.md`

## Global Constraints

- Namespace `Manychois\PhpStrong\Http`; tests in `Manychois\PhpStrongTests\Http` under `tests/Http/`.
- Every interface implementation carries `#[Override]` and lives inside a `#region implements IXxx` … `#endregion implements IXxx` block.
- Import same-namespace interfaces with an alias: `use Manychois\PhpStrong\Http\ResponseEmitterInterface as IResponseEmitter;` — this is what `NativeSession.php:12` already does for `SessionInterface`.
- Methods sort static-before-instance, public-before-private, alphabetically within each group. **Private methods go below the last `#endregion`, never above the first `#region`.**
- PHPDoc required on every public and protected method. One blank line between different annotation types (`@param` block, blank, `@return`, blank, `@throws`). Single spaces inside a tag — never column-align.
- PHPDoc prose wraps at 120 columns; fill the line, no early wraps.
- Global PHP constants take a leading backslash (`\PHP_EOL`). **Global functions must NOT be fully qualified in `SapiEmitter`** — see the seam constraint below.
- `readonly` promoted constructor properties; validate at the boundary and throw `InvalidArgumentException`.
- Quality gates, in order, before any task is done: `composer phpcbf` → `composer phpcs` → `composer phpstan` → `composer test`. All four must be clean.

### The seam constraint (applies to every task)

`SapiEmitter` MUST call `header()` and `headers_sent()` **unqualified**. Writing `\header()` breaks the test seam silently — the suite still passes, but it stops asserting anything about the real class.

This is verified safe against the linters:

- `phpcs.xml:91` sets `allowFallbackGlobalFunctions="true"`, so unqualified global function calls pass PHPCS.
- `phpcs.xml:4` scopes PHPCS to `./src`, and `phpstan.dist.neon` scopes PHPStan to `src`. Neither tool inspects `tests/`, so the stub files face no style gate. Match the project's style there anyway, by convention.

**No class in `Manychois\PhpStrong\Http` other than `SapiEmitter` may call `header()` or `headers_sent()` unqualified**, or it will silently hit the stub during tests.

## File Structure

| File | Responsibility |
| ---- | -------------- |
| `src/Http/ResponseEmitterInterface.php` (new) | One method: `emit()`. The substitution seam for downstream applications. |
| `src/Http/SapiEmitter.php` (new) | The only implementation. Guard, status line, headers, body — four private phases. |
| `tests/Http/SapiSpy.php` (new) | Static recorder the shadowed functions delegate to. |
| `tests/Http/sapi-functions.php` (new) | `header()` / `headers_sent()` declared in `Manychois\PhpStrong\Http`. |
| `tests/Http/SapiSeamTest.php` (new) | Proves the shadowing and the spy work, independent of the emitter. |
| `tests/Http/SapiEmitterTest.php` (new) | Everything about the emitter. |
| `composer.json` (modified) | New `autoload-dev.files` entry. Dev-only; `autoload` untouched. |
| `docs/http.md` (modified) | New `## Emitting a response` section. |
| `README.md` (modified) | `### Utilities` table, `Manychois\PhpStrong\Http` row (line 38). |
| `docs/superpowers/specs/2026-08-25-http-cookies-design.md` (modified) | Closing line on the hand-off note. |

---

### Task 1: The SAPI test seam

Nothing here touches `src/`. This task exists on its own because every later task's tests depend on it, and because a reviewer should be able to reject the seam without rejecting the emitter.

**Files:**
- Create: `tests/Http/SapiSpy.php`
- Create: `tests/Http/sapi-functions.php`
- Modify: `composer.json` (the `autoload-dev` block)
- Test: `tests/Http/SapiSeamTest.php`

**Interfaces:**
- Consumes: nothing.
- Produces: `Manychois\PhpStrongTests\Http\SapiSpy` with public statics `header(string $header, bool $replace, int $responseCode): void`, `headersSent(?string &$filename, ?int &$line): bool`, `markSent(string $file, int $line): void`, `recorded(): list<array{string,bool,int}>`, `reset(): void`. Also the functions `Manychois\PhpStrong\Http\header()` and `Manychois\PhpStrong\Http\headers_sent()`.

- [ ] **Step 1: Add the `files` entry to `composer.json`**

Find the `autoload-dev` block (it currently holds only `psr-4`) and add a sibling `files` key. The result must read exactly:

```json
  "autoload-dev": {
    "psr-4": {
      "Manychois\\PhpStrongTests\\": "tests/",
      "Manychois\\PhpStrongFeatureTests\\": "feature-tests/"
    },
    "files": [
      "tests/Http/sapi-functions.php"
    ]
  },
```

Do **not** touch the `autoload` block. Function declarations cannot be PSR-4 autoloaded, which is the entire reason this entry exists.

- [ ] **Step 2: Write `tests/Http/SapiSpy.php`**

```php
<?php

declare(strict_types=1);

namespace Manychois\PhpStrongTests\Http;

/**
 * Records the SAPI calls the shadowed functions in `tests/Http/sapi-functions.php` intercept.
 *
 * The state is static because a shadowed function has no object to reach through. Call `reset()` in `setUp()`
 * so one test cannot observe another's calls.
 */
final class SapiSpy
{
    /**
     * @var list<array{string,bool,int}>
     */
    private static array $recorded = [];
    private static bool $sent = false;
    private static string $sentFile = '';
    private static int $sentLine = 0;

    /**
     * Records one `header()` call.
     *
     * @param string $header The full header line.
     * @param bool $replace Whether the call asked to replace an existing header of the same name.
     * @param int $responseCode The status code the call carried, or 0 when it carried none.
     */
    public static function header(string $header, bool $replace, int $responseCode): void
    {
        self::$recorded[] = [$header, $replace, $responseCode];
    }

    /**
     * Answers a `headers_sent()` call with whatever `markSent()` last configured.
     *
     * @param ?string $filename Receives the file which started the output.
     * @param ?int $line Receives the line which started the output.
     *
     * @return bool True if output has been marked as started; false otherwise.
     */
    public static function headersSent(?string &$filename, ?int &$line): bool
    {
        $filename = self::$sentFile;
        $line = self::$sentLine;

        return self::$sent;
    }

    /**
     * Makes the next `headers_sent()` call report that output has already started.
     *
     * @param string $file The file to report.
     * @param int $line The line to report.
     */
    public static function markSent(string $file, int $line): void
    {
        self::$sent = true;
        self::$sentFile = $file;
        self::$sentLine = $line;
    }

    /**
     * Returns every recorded call in the order it was made.
     *
     * @return list<array{string,bool,int}> The recorded calls, each `[header, replace, responseCode]`.
     */
    public static function recorded(): array
    {
        return self::$recorded;
    }

    /**
     * Clears every recorded call and stops reporting that output has started.
     */
    public static function reset(): void
    {
        self::$recorded = [];
        self::$sent = false;
        self::$sentFile = '';
        self::$sentLine = 0;
    }
}
```

- [ ] **Step 3: Write `tests/Http/sapi-functions.php`**

The namespace here is `Manychois\PhpStrong\Http` — the namespace `SapiEmitter` lives in, **not** the tests' own `Manychois\PhpStrongTests\Http`. PHP resolves an unqualified call against the *calling* namespace first, so this is what makes the interception work.

```php
<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\Http;

use Manychois\PhpStrongTests\Http\SapiSpy;

/**
 * Shadows the global `header()` for every unqualified call made from this namespace.
 *
 * @param string $header The header line.
 * @param bool $replace Whether to replace an existing header of the same name.
 * @param int $response_code The status code to set, or 0 for none.
 */
function header(string $header, bool $replace = true, int $response_code = 0): void
{
    SapiSpy::header($header, $replace, $response_code);
}

/**
 * Shadows the global `headers_sent()` for every unqualified call made from this namespace.
 *
 * @param ?string $filename Receives the file which started the output.
 * @param ?int $line Receives the line which started the output.
 *
 * @return bool True if output has been marked as started; false otherwise.
 */
function headers_sent(?string &$filename = null, ?int &$line = null): bool
{
    return SapiSpy::headersSent($filename, $line);
}
```

`$response_code` keeps PHP's own snake_case parameter name deliberately, so a named-argument call would behave identically to the real function.

- [ ] **Step 4: Regenerate the autoloader**

Run: `composer dump-autoload`

Expected: `Generated autoload files` with no error. If it reports the file cannot be found, the path in Step 1 is wrong.

- [ ] **Step 5: Write the failing test**

Create `tests/Http/SapiSeamTest.php`:

```php
<?php

declare(strict_types=1);

namespace Manychois\PhpStrongTests\Http;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function Manychois\PhpStrong\Http\header;
use function Manychois\PhpStrong\Http\headers_sent;

/**
 * Unit tests for the shadowed SAPI functions and {@see SapiSpy}.
 */
final class SapiSeamTest extends TestCase
{
    protected function setUp(): void
    {
        SapiSpy::reset();
    }

    #[Test]
    public function theShadowedHeaderFunctionRecordsEveryCallInOrder(): void
    {
        header('X-First: 1');
        header('X-Second: 2', false);
        header('HTTP/1.1 404 Not Found', true, 404);

        static::assertSame([
            ['X-First: 1', true, 0],
            ['X-Second: 2', false, 0],
            ['HTTP/1.1 404 Not Found', true, 404],
        ], SapiSpy::recorded());
    }

    #[Test]
    public function headersSentReportsFalseUntilMarked(): void
    {
        $file = '';
        $line = 0;

        static::assertFalse(headers_sent($file, $line));
        static::assertSame('', $file);
        static::assertSame(0, $line);
    }

    #[Test]
    public function headersSentReportsTheMarkedFileAndLine(): void
    {
        SapiSpy::markSent('/app/public/index.php', 12);

        $file = '';
        $line = 0;

        static::assertTrue(headers_sent($file, $line));
        static::assertSame('/app/public/index.php', $file);
        static::assertSame(12, $line);
    }

    #[Test]
    public function resetClearsRecordedCallsAndTheSentFlag(): void
    {
        header('X-Gone: 1');
        SapiSpy::markSent('/somewhere.php', 3);

        SapiSpy::reset();

        $file = '';
        $line = 0;

        static::assertSame([], SapiSpy::recorded());
        static::assertFalse(headers_sent($file, $line));
    }
}
```

The `use function` imports matter: without them the test would call the *global* `header()`, which under the CLI SAPI silently does nothing and the assertions would fail for a confusing reason.

- [ ] **Step 6: Run the test to verify it fails**

Run: `./vendor/bin/phpunit tests/Http/SapiSeamTest.php`

Expected: FAIL — `Error: Class "Manychois\PhpStrongTests\Http\SapiSpy" not found` if you run this before Step 2, or PASS if Steps 1–4 are already done. This task builds the seam and its test together, so a green run here is the expected end state rather than a red one; the meaningful red-then-green cycles start in Task 2.

- [ ] **Step 7: Run the whole suite**

Run: `composer test`

Expected: `OK (1092 tests, …)` — the existing 1088 plus the 4 new ones. A failure anywhere else means the `files` autoload entry has broken something; the stub functions must not collide with any existing declaration.

- [ ] **Step 8: Run the gates**

Run: `composer phpcbf && composer phpcs && composer phpstan`

Expected: PHPCS clean, PHPStan `[OK]`. Neither tool reads `tests/`, so this proves only that nothing in `src/` regressed.

- [ ] **Step 9: Commit**

```bash
git add composer.json tests/Http/SapiSpy.php tests/Http/sapi-functions.php tests/Http/SapiSeamTest.php
git commit -m "test(http): add the SAPI function seam for the emitter"
```

Do not add `composer.lock` — no dependency changed.

---

### Task 2: The interface, the constructor, and the headers-sent guard

**Files:**
- Create: `src/Http/ResponseEmitterInterface.php`
- Create: `src/Http/SapiEmitter.php`
- Test: `tests/Http/SapiEmitterTest.php`

**Interfaces:**
- Consumes: `SapiSpy` from Task 1.
- Produces: `ResponseEmitterInterface::emit(IResponse $response, ?IRequest $request = null): void`; `SapiEmitter::__construct(int $chunkSize = 8_388_608)`. Later tasks fill in `emit()`'s phases and add the private methods `emitStatusLine()`, `emitHeaders()`, `hasBody()`, `emitBody()`.

- [ ] **Step 1: Write the failing test**

Create `tests/Http/SapiEmitterTest.php`:

```php
<?php

declare(strict_types=1);

namespace Manychois\PhpStrongTests\Http;

use InvalidArgumentException;
use Manychois\PhpStrong\Http\Response;
use Manychois\PhpStrong\Http\SapiEmitter;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Unit tests for {@see SapiEmitter}.
 */
final class SapiEmitterTest extends TestCase
{
    protected function setUp(): void
    {
        SapiSpy::reset();
    }

    #[Test]
    public function constructorRejectsAChunkSizeBelowOne(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The chunk size must be at least 1 byte; got 0.');

        new SapiEmitter(0);
    }

    #[Test]
    public function emitThrowsWhenOutputHasAlreadyStarted(): void
    {
        SapiSpy::markSent('/app/public/index.php', 12);
        $emitter = new SapiEmitter();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cannot emit the response: output already started at /app/public/index.php:12.');

        $emitter->emit(new Response());
    }

    #[Test]
    public function emitWritesNothingWhenOutputHasAlreadyStarted(): void
    {
        SapiSpy::markSent('/app/public/index.php', 12);
        $emitter = new SapiEmitter();

        try {
            $emitter->emit(new Response());
        } catch (RuntimeException) {
            // The throw is asserted by its own test; this one asserts nothing leaked out before it.
        }

        static::assertSame([], SapiSpy::recorded());
    }
}
```

The third test is not redundant with the second. The guard's whole purpose is that a failed emit leaves the response stream untouched, so the caller can emit something else; asserting only the exception would let an implementation that writes the status line *before* checking still pass.

- [ ] **Step 2: Run the test to verify it fails**

Run: `./vendor/bin/phpunit tests/Http/SapiEmitterTest.php`

Expected: FAIL — `Error: Class "Manychois\PhpStrong\Http\SapiEmitter" not found`.

- [ ] **Step 3: Write `src/Http/ResponseEmitterInterface.php`**

```php
<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\Http;

use Psr\Http\Message\RequestInterface as IRequest;
use Psr\Http\Message\ResponseInterface as IResponse;
use RuntimeException;

/**
 * Sends a response to whatever is listening: a SAPI in production, a recorder in a functional test.
 *
 * One method, and one reason to exist: an application under test needs to capture a response without reaching into
 * PHP's global header state.
 */
interface ResponseEmitterInterface
{
    /**
     * Sends the response: status line, then headers, then body.
     *
     * @param IResponse $response The response to send.
     * @param ?IRequest $request The request being answered, read only to detect `HEAD`, which must receive no body.
     * Pass `null` when the request is known not to be a `HEAD` request.
     *
     * @throws RuntimeException if output has already started, since the headers can no longer be sent.
     */
    public function emit(IResponse $response, ?IRequest $request = null): void;
}
```

`IRequest` is `Psr\Http\Message\RequestInterface`, which `ServerRequestInterface` extends — a `ServerRequest` is accepted directly, and typing it as the server variant here would narrow the interface for no gain.

- [ ] **Step 4: Write `src/Http/SapiEmitter.php`**

Only the guard is implemented; later tasks add the other three phases.

```php
<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\Http;

use InvalidArgumentException;
use Manychois\PhpStrong\Http\ResponseEmitterInterface as IResponseEmitter;
use Override;
use Psr\Http\Message\RequestInterface as IRequest;
use Psr\Http\Message\ResponseInterface as IResponse;
use RuntimeException;

/**
 * Sends a PSR-7 response to the SAPI: status line, then headers, then body.
 *
 * The body is written in fixed-size chunks, so streaming a file of any size never approaches `memory_limit`.
 *
 * This class deliberately leaves output buffers alone. A buffer it did not open may hold output the caller intends
 * to keep, and a library is in no position to judge; a buffer which has already flushed surfaces instead as the
 * exception thrown before anything is written.
 */
final class SapiEmitter implements IResponseEmitter
{
    /**
     * Initializes a new instance of the SapiEmitter class.
     *
     * @param int $chunkSize The number of bytes read from the body stream per write. Must be at least 1.
     *
     * @throws InvalidArgumentException if the chunk size is below 1.
     *
     * @phpstan-param positive-int $chunkSize
     */
    public function __construct(private readonly int $chunkSize = 8_388_608)
    {
        if ($chunkSize < 1) {
            throw new InvalidArgumentException(sprintf(
                'The chunk size must be at least 1 byte; got %d.',
                $chunkSize,
            ));
        }
    }

    #region implements IResponseEmitter

    /**
     * @inheritDoc
     */
    #[Override]
    public function emit(IResponse $response, ?IRequest $request = null): void
    {
        $this->assertHeadersNotSent();
    }

    #endregion implements IResponseEmitter

    /**
     * Throws unless the headers can still be sent.
     */
    private function assertHeadersNotSent(): void
    {
        $file = '';
        $line = 0;
        if (!headers_sent($file, $line)) {
            return;
        }

        throw new RuntimeException(sprintf(
            'Cannot emit the response: output already started at %s:%d.',
            $file === '' ? 'an unknown file' : $file,
            $line,
        ));
    }
}
```

`headers_sent()` is written **unqualified** — see the seam constraint. `$file` and `$line` are seeded before the call because PHP fills them by reference only when it returns true.

`$response` and `$request` are unused in `emit()` at this stage; nothing flags that. `phpcs.xml` enables no unused-parameter sniff and PHPStan does not report unused method parameters, so all four gates must be clean at the end of this task as at every other.

- [ ] **Step 5: Run the test to verify it passes**

Run: `./vendor/bin/phpunit tests/Http/SapiEmitterTest.php`

Expected: `OK (3 tests, …)`.

- [ ] **Step 6: Run the gates**

Run: `composer phpcbf && composer phpcs && composer phpstan && composer test`

Expected: PHPCS clean, PHPStan `[OK]`, `composer test` green.

- [ ] **Step 7: Commit**

```bash
git add src/Http/ResponseEmitterInterface.php src/Http/SapiEmitter.php tests/Http/SapiEmitterTest.php
git commit -m "feat(http): add SapiEmitter with the headers-sent guard"
```

---

### Task 3: The status line

**Files:**
- Modify: `src/Http/SapiEmitter.php`
- Test: `tests/Http/SapiEmitterTest.php`

**Interfaces:**
- Consumes: `SapiEmitter::emit()` and `assertHeadersNotSent()` from Task 2.
- Produces: private `emitStatusLine(IResponse $response): void`.

- [ ] **Step 1: Write the failing tests**

Append to `SapiEmitterTest`, keeping methods in the order they read best — PHPUnit does not care, and the file is a test class, not a `src/` class bound by the alphabetical rule.

```php
    #[Test]
    public function emitSendsTheStatusLineWithTheDefaultReasonPhrase(): void
    {
        (new SapiEmitter())->emit(new Response(404));

        static::assertSame(['HTTP/1.1 404 Not Found', true, 404], SapiSpy::recorded()[0]);
    }

    #[Test]
    public function emitSendsTheStatusLineWithACustomReasonPhrase(): void
    {
        (new SapiEmitter())->emit(new Response(418, 'I Am A Teapot'));

        static::assertSame(['HTTP/1.1 418 I Am A Teapot', true, 418], SapiSpy::recorded()[0]);
    }

    #[Test]
    public function emitOmitsTheTrailingSpaceWhenThereIsNoReasonPhrase(): void
    {
        (new SapiEmitter())->emit(new Response(599));

        static::assertSame(['HTTP/1.1 599', true, 599], SapiSpy::recorded()[0]);
    }

    #[Test]
    public function emitSendsTheProtocolVersionTheResponseCarries(): void
    {
        (new SapiEmitter())->emit(new Response(200, protocolVersion: '2'));

        static::assertSame(['HTTP/2 200 OK', true, 200], SapiSpy::recorded()[0]);
    }
```

Status 599 is chosen because `StatusCode::tryFrom(599)` returns `null`, so `Response` leaves the reason phrase empty — that is the only way to reach the no-phrase branch through the public constructor.

- [ ] **Step 2: Run the tests to verify they fail**

Run: `./vendor/bin/phpunit tests/Http/SapiEmitterTest.php`

Expected: FAIL on all four with `Undefined array key 0` — nothing has been recorded, because `emit()` still only runs the guard.

- [ ] **Step 3: Call the new phase from `emit()`**

Replace the body of `emit()` with:

```php
        $this->assertHeadersNotSent();
        $this->emitStatusLine($response);
```

- [ ] **Step 4: Add the private method**

Insert it below `assertHeadersNotSent()` — private methods live below the last `#endregion` and sort alphabetically, so `assertHeadersNotSent`, then `emitStatusLine`.

```php
    /**
     * Sends the status line, which also fixes the status code for every header sent after it.
     *
     * @param IResponse $response The response whose status is sent.
     */
    private function emitStatusLine(IResponse $response): void
    {
        $code = $response->getStatusCode();
        $reason = $response->getReasonPhrase();

        header(
            sprintf(
                'HTTP/%s %d%s',
                $response->getProtocolVersion(),
                $code,
                $reason === '' ? '' : ' ' . $reason,
            ),
            true,
            $code,
        );
    }
```

`http_response_code()` is deliberately not used: it cannot carry a reason phrase, and `Response::withStatus()` supports custom ones. The third argument to `header()` is what actually fixes the code, which makes the version token in the line advisory — HTTP/2 discards it rather than emitting a malformed line.

- [ ] **Step 5: Run the tests to verify they pass**

Run: `./vendor/bin/phpunit tests/Http/SapiEmitterTest.php`

Expected: `OK (7 tests, …)`.

- [ ] **Step 6: Run the gates**

Run: `composer phpcbf && composer phpcs && composer phpstan && composer test`

Expected: all clean.

- [ ] **Step 7: Commit**

```bash
git add src/Http/SapiEmitter.php tests/Http/SapiEmitterTest.php
git commit -m "feat(http): emit the status line with its reason phrase"
```

---

### Task 4: Headers, and the `Set-Cookie` merge rule

This is the task the whole spec exists to get right. Read the rule before writing code.

**Files:**
- Modify: `src/Http/SapiEmitter.php`
- Test: `tests/Http/SapiEmitterTest.php`

**Interfaces:**
- Consumes: `emitStatusLine()` from Task 3.
- Produces: private `emitHeaders(IResponse $response): void`.

- [ ] **Step 1: Write the failing tests**

```php
    #[Test]
    public function emitReplacesOnTheFirstValueAndAppendsOnTheRest(): void
    {
        $response = (new Response())
            ->withHeader('Vary', 'Accept')
            ->withAddedHeader('Vary', 'Accept-Encoding')
            ->withAddedHeader('Vary', 'Origin');

        (new SapiEmitter())->emit($response);

        static::assertSame([
            ['Vary: Accept', true, 0],
            ['Vary: Accept-Encoding', false, 0],
            ['Vary: Origin', false, 0],
        ], array_slice(SapiSpy::recorded(), 1));
    }

    #[Test]
    public function emitNeverReplacesOnSetCookieNotEvenTheFirstValue(): void
    {
        $response = (new Response())
            ->withHeader('Set-Cookie', 'a=1')
            ->withAddedHeader('Set-Cookie', 'b=2');

        (new SapiEmitter())->emit($response);

        static::assertSame([
            ['Set-Cookie: a=1', false, 0],
            ['Set-Cookie: b=2', false, 0],
        ], array_slice(SapiSpy::recorded(), 1));
    }

    #[Test]
    public function theSetCookieRuleIsCaseInsensitive(): void
    {
        $response = (new Response())->withHeader('set-cookie', 'a=1');

        (new SapiEmitter())->emit($response);

        static::assertSame([['set-cookie: a=1', false, 0]], array_slice(SapiSpy::recorded(), 1));
    }

    #[Test]
    public function everyCookieQueuedOnACookieBagSurvivesTheEmit(): void
    {
        $bag = CookieBag::fromRequest(new ServerRequest());
        $bag->set(new Cookie('sid', 'abc', path: '/'));
        $bag->set(new Cookie('theme', 'dark', path: '/'));

        (new SapiEmitter())->emit($bag->applyTo(new Response()));

        $recorded = array_slice(SapiSpy::recorded(), 1);

        static::assertCount(2, $recorded);
        foreach ($recorded as $call) {
            static::assertStringStartsWith('Set-Cookie: ', $call[0]);
            static::assertFalse($call[1], 'A Set-Cookie header must never be emitted with replace = true.');
        }
    }

    #[Test]
    public function emitPreservesHeaderNameCasingAndDoesNotTrimValues(): void
    {
        $response = (new Response())->withHeader('X-Weird-CASE', 'a, b');

        (new SapiEmitter())->emit($response);

        static::assertSame([['X-Weird-CASE: a, b', true, 0]], array_slice(SapiSpy::recorded(), 1));
    }
```

`array_slice(…, 1)` drops the status line, which every emit sends first.

The `CookieBag` test needs these imports added to the file's `use` block:

```php
use Manychois\PhpStrong\Http\Cookie;
use Manychois\PhpStrong\Http\CookieBag;
use Manychois\PhpStrong\Http\ServerRequest;
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `./vendor/bin/phpunit tests/Http/SapiEmitterTest.php`

Expected: FAIL on all five — the recorded list holds only the status line, so each slice is `[]`.

- [ ] **Step 3: Call the new phase from `emit()`**

```php
        $this->assertHeadersNotSent();
        $this->emitStatusLine($response);
        $this->emitHeaders($response);
```

- [ ] **Step 4: Add the private method**

Alphabetical order among the privates puts it between `assertHeadersNotSent()` and `emitStatusLine()`.

```php
    /**
     * Sends every header of the response, merging rather than replacing the cookies PHP has already queued.
     *
     * `Set-Cookie` is emitted with `replace` false for every value, the first included. Replacing on the first value
     * would delete any `Set-Cookie` PHP itself has queued — most importantly the session cookie `session_start()`
     * writes inside {@see NativeSession}, which never appears in the response object and so cannot be recovered from
     * it. This method is the single point where PHP's own queued headers and the response's headers meet.
     *
     * @param IResponse $response The response whose headers are sent.
     */
    private function emitHeaders(IResponse $response): void
    {
        foreach ($response->getHeaders() as $name => $values) {
            $isSetCookie = strcasecmp($name, 'Set-Cookie') === 0;
            $first = true;
            foreach ($values as $value) {
                header($name . ': ' . $value, $first && !$isSetCookie);
                $first = false;
            }
        }
    }
```

Values are emitted verbatim: no re-folding, no trimming, no normalisation of header-name casing. PSR-7 preserves the caller's casing on purpose.

- [ ] **Step 5: Run the tests to verify they pass**

Run: `./vendor/bin/phpunit tests/Http/SapiEmitterTest.php`

Expected: `OK (12 tests, …)`.

- [ ] **Step 6: Run the gates**

Run: `composer phpcbf && composer phpcs && composer phpstan && composer test`

Expected: all clean.

- [ ] **Step 7: Commit**

```bash
git add src/Http/SapiEmitter.php tests/Http/SapiEmitterTest.php
git commit -m "feat(http): emit headers, merging Set-Cookie rather than replacing it"
```

---

### Task 5: Body suppression

**Files:**
- Modify: `src/Http/SapiEmitter.php`
- Test: `tests/Http/SapiEmitterTest.php`

**Interfaces:**
- Consumes: `emitHeaders()` from Task 4.
- Produces: private `hasBody(IResponse $response, ?IRequest $request): bool`.

- [ ] **Step 1: Write the failing tests**

These assert the stream was never *read*, not merely that no output appeared — an implementation which reads the body and discards it would pass the weaker assertion while still realising a lazily-populated stream.

```php
    #[Test]
    #[DataProvider('provideStatusesForbiddingABody')]
    public function emitNeverTouchesTheBodyWhenTheStatusForbidsOne(int $status): void
    {
        $body = $this->createMock(IStream::class);
        $body->expects(static::never())->method('read');
        $body->expects(static::never())->method('rewind');
        $body->expects(static::never())->method('__toString');

        (new SapiEmitter())->emit(new Response($status, body: $body));
    }

    /**
     * @return iterable<string,array{int}>
     */
    public static function provideStatusesForbiddingABody(): iterable
    {
        yield '100 Continue' => [100];
        yield '199 unassigned informational' => [199];
        yield '204 No Content' => [204];
        yield '304 Not Modified' => [304];
    }

    #[Test]
    #[DataProvider('provideHeadMethodCasings')]
    public function emitNeverTouchesTheBodyWhenAnsweringAHeadRequest(string $method): void
    {
        $body = $this->createMock(IStream::class);
        $body->expects(static::never())->method('read');

        $request = new Request($method, 'https://example.com/');

        (new SapiEmitter())->emit(new Response(200, body: $body), $request);
    }

    /**
     * @return iterable<string,array{string}>
     */
    public static function provideHeadMethodCasings(): iterable
    {
        yield 'uppercase' => ['HEAD'];
        yield 'lowercase' => ['head'];
        yield 'mixed case' => ['Head'];
    }

    #[Test]
    public function emitStillSendsTheHeadersOfASuppressedBody(): void
    {
        $response = (new Response(304))->withHeader('ETag', '"v1"');

        (new SapiEmitter())->emit($response);

        static::assertSame([
            ['HTTP/1.1 304 Not Modified', true, 304],
            ['ETag: "v1"', true, 0],
        ], SapiSpy::recorded());
    }
```

New imports for the file:

```php
use Manychois\PhpStrong\Http\Request;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Http\Message\StreamInterface as IStream;
```

`Request` is used by the `HEAD` provider tests above and again in Task 6.

The negative case — a `GET` request whose body is *not* suppressed — belongs to Task 6, where the body phase exists to make it pass. Writing it here would mean shipping a test that asserts nothing.

- [ ] **Step 2: Run the tests to verify they fail**

Run: `./vendor/bin/phpunit tests/Http/SapiEmitterTest.php`

Expected: the 304-headers test FAILS if suppression is somehow already present; the mock-based tests currently PASS trivially, because `emit()` never touches the body at all yet. That is the honest state — they become meaningful the moment Task 6 adds the body phase, and they are the guard which stops Task 6 from emitting a body it must not. Do not skip them for looking easy.

- [ ] **Step 3: Call the new phase from `emit()`**

```php
        $this->assertHeadersNotSent();
        $this->emitStatusLine($response);
        $this->emitHeaders($response);
        if (!$this->hasBody($response, $request)) {
            return;
        }
```

- [ ] **Step 4: Add the private method**

Alphabetically the privates now read `assertHeadersNotSent`, `emitHeaders`, `emitStatusLine`, `hasBody`.

```php
    /**
     * Checks whether HTTP allows this response to carry a body.
     *
     * RFC 9110 forbids a body on any 1xx, on 204, on 304, and on any response to a `HEAD` request. A body on a 304
     * desynchronises the connection for the next request on it, so the failure surfaces as a corrupted *later*
     * response — which is why this is not left to the caller.
     *
     * @param IResponse $response The response about to be sent.
     * @param ?IRequest $request The request being answered, or null when it is known not to be a `HEAD` request.
     *
     * @return bool True if the body may be sent; false if it must be suppressed.
     */
    private function hasBody(IResponse $response, ?IRequest $request): bool
    {
        $code = $response->getStatusCode();
        if ($code === 204 || $code === 304 || ($code >= 100 && $code < 200)) {
            return false;
        }

        return $request === null || strtoupper($request->getMethod()) !== 'HEAD';
    }
```

`strtoupper()` is required — PSR-7 does not normalise the request method, so `head` must suppress exactly as `HEAD` does.

- [ ] **Step 5: Run the tests to verify they pass**

Run: `./vendor/bin/phpunit tests/Http/SapiEmitterTest.php`

Expected: `OK (…)` — every test green, nothing skipped.

- [ ] **Step 6: Run the gates**

Run: `composer phpcbf && composer phpcs && composer phpstan && composer test`

Expected: all clean.

- [ ] **Step 7: Commit**

```bash
git add src/Http/SapiEmitter.php tests/Http/SapiEmitterTest.php
git commit -m "feat(http): suppress the body where HTTP forbids one"
```

---

### Task 6: Streaming the body

**Files:**
- Modify: `src/Http/SapiEmitter.php`
- Test: `tests/Http/SapiEmitterTest.php`

**Interfaces:**
- Consumes: `hasBody()` from Task 5.
- Produces: private `emitBody(IResponse $response): void`. Completes the class.

- [ ] **Step 1: Write the failing tests**

`aGetRequestDoesNotSuppressTheBody` is the negative case for Task 5's suppression rule, held back until now because only this task makes it pass.

```php
    #[Test]
    public function aGetRequestDoesNotSuppressTheBody(): void
    {
        $request = new Request('GET', 'https://example.com/');

        ob_start();
        (new SapiEmitter())->emit(new Response(200, body: 'hello'), $request);
        $output = ob_get_clean();

        static::assertSame('hello', $output);
    }

    #[Test]
    public function emitWritesTheWholeBody(): void
    {
        ob_start();
        (new SapiEmitter())->emit(new Response(200, body: 'hello world'));
        $output = ob_get_clean();

        static::assertSame('hello world', $output);
    }

    #[Test]
    public function aBodyLargerThanTheChunkSizeArrivesByteIdentical(): void
    {
        $content = str_repeat('abcdefghij', 1000);

        ob_start();
        (new SapiEmitter(7))->emit(new Response(200, body: $content));
        $output = ob_get_clean();

        static::assertSame($content, $output);
    }

    #[Test]
    public function aBodyLargerThanTheChunkSizeIsReadMoreThanOnce(): void
    {
        $body = $this->createMock(IStream::class);
        $body->method('isReadable')->willReturn(true);
        $body->method('isSeekable')->willReturn(false);
        $body->method('eof')->willReturn(false, false, true);
        $body->expects(static::exactly(2))
            ->method('read')
            ->with(4)
            ->willReturn('abcd', 'efgh');

        ob_start();
        (new SapiEmitter(4))->emit(new Response(200, body: $body));
        $output = ob_get_clean();

        static::assertSame('abcdefgh', $output);
    }

    #[Test]
    public function aSeekableBodyIsRewoundBeforeItIsRead(): void
    {
        $stream = (new StreamFactory())->createStream('hello');
        $stream->read(3);

        ob_start();
        (new SapiEmitter())->emit(new Response(200, body: $stream));
        $output = ob_get_clean();

        static::assertSame('hello', $output);
    }

    #[Test]
    public function aNonSeekableBodyIsNotRewound(): void
    {
        $body = $this->createMock(IStream::class);
        $body->method('isReadable')->willReturn(true);
        $body->method('isSeekable')->willReturn(false);
        $body->method('eof')->willReturn(false, true);
        $body->method('read')->willReturn('tail');
        $body->expects(static::never())->method('rewind');

        ob_start();
        (new SapiEmitter())->emit(new Response(200, body: $body));
        $output = ob_get_clean();

        static::assertSame('tail', $output);
    }

    #[Test]
    public function aNonReadableBodyEmitsNothing(): void
    {
        $body = $this->createMock(IStream::class);
        $body->method('isReadable')->willReturn(false);
        $body->expects(static::never())->method('read');

        ob_start();
        (new SapiEmitter())->emit(new Response(200, body: $body));
        $output = ob_get_clean();

        static::assertSame('', $output);
    }

    #[Test]
    public function anEmptyReadBeforeEofEndsTheLoop(): void
    {
        $body = $this->createMock(IStream::class);
        $body->method('isReadable')->willReturn(true);
        $body->method('isSeekable')->willReturn(false);
        $body->method('eof')->willReturn(false);
        $body->method('read')->willReturn('');

        ob_start();
        (new SapiEmitter())->emit(new Response(200, body: $body));
        $output = ob_get_clean();

        static::assertSame('', $output);
    }
```

`anEmptyReadBeforeEofEndsTheLoop` is the most valuable test in the file. `eof()` never returns true and `read()` never returns content — exactly what a non-blocking stream is permitted to do under PSR-7. Without the break, this test does not fail with an assertion; it hangs until `defaultTimeLimit="3"` in `phpunit.xml` kills it, and the reported error points nowhere near the cause. If you see a timeout here, the `break` is missing.

`StreamFactory` needs importing:

```php
use Manychois\PhpStrong\Http\StreamFactory;
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `./vendor/bin/phpunit tests/Http/SapiEmitterTest.php`

Expected: FAIL — every body test asserts non-empty output and gets `''`, since `emit()` still returns after the suppression check.

- [ ] **Step 3: Call the final phase from `emit()`**

The complete method:

```php
    #[Override]
    public function emit(IResponse $response, ?IRequest $request = null): void
    {
        $this->assertHeadersNotSent();
        $this->emitStatusLine($response);
        $this->emitHeaders($response);
        if (!$this->hasBody($response, $request)) {
            return;
        }

        $this->emitBody($response);
    }
```

- [ ] **Step 4: Add the private method**

Alphabetically first among the `emit*` privates: `assertHeadersNotSent`, `emitBody`, `emitHeaders`, `emitStatusLine`, `hasBody`.

```php
    /**
     * Writes the body in fixed-size chunks, so a body of any size costs a constant amount of memory.
     *
     * A stream which cannot be read emits nothing rather than throwing halfway through a partly written response. A
     * stream which cannot seek is read from wherever it stands, which is the only meaningful behaviour for a pipe or
     * a socket. An empty read before end-of-file ends the loop: PSR-7 permits a non-blocking stream to return an
     * empty string while more data is still coming, and continuing would spin forever.
     *
     * @param IResponse $response The response whose body is written.
     */
    private function emitBody(IResponse $response): void
    {
        $body = $response->getBody();
        if (!$body->isReadable()) {
            return;
        }
        if ($body->isSeekable()) {
            $body->rewind();
        }

        while (!$body->eof()) {
            $chunk = $body->read($this->chunkSize);
            if ($chunk === '') {
                break;
            }

            echo $chunk;
        }
    }
```

- [ ] **Step 5: Run the tests to verify they pass**

Run: `./vendor/bin/phpunit tests/Http/SapiEmitterTest.php`

Expected: `OK (…)`. If the run hangs for three seconds and then reports a time limit, re-read Step 4's `break`.

- [ ] **Step 6: Run the whole suite and the gates**

Run: `composer phpcbf && composer phpcs && composer phpstan && composer test`

Expected: PHPCS clean, PHPStan `[OK]`, and the full suite green.

- [ ] **Step 7: Commit**

```bash
git add src/Http/SapiEmitter.php tests/Http/SapiEmitterTest.php
git commit -m "feat(http): stream the response body in fixed-size chunks"
```

---

### Task 7: Documentation

**Files:**
- Modify: `docs/http.md` (new section between `## Middleware (PSR-15)` and `## Cookies`)
- Modify: `README.md:38`
- Modify: `docs/superpowers/specs/2026-08-25-http-cookies-design.md` (the hand-off note)

**Interfaces:**
- Consumes: the finished `SapiEmitter` and `ResponseEmitterInterface`.
- Produces: nothing further tasks depend on.

- [ ] **Step 1: Add the `## Emitting a response` section to `docs/http.md`**

Insert it immediately **before** the `## Cookies` heading (currently line 144) and after the `## Middleware (PSR-15)` section ends. The emitter is the last step of the server role, so the cookies section that follows can point back to it.

````markdown
## Emitting a response

`SapiEmitter` sends a response to PHP's SAPI: status line, then headers, then body. It implements
`ResponseEmitterInterface`, so an application can substitute a recording emitter in a functional test without
touching PHP's global header state.

```php
use Manychois\PhpStrong\Http\CookieBag;
use Manychois\PhpStrong\Http\MiddlewarePipeline;
use Manychois\PhpStrong\Http\SapiEmitter;
use Manychois\PhpStrong\Http\ServerRequest;

$request = ServerRequest::fromGlobals();
$cookies = CookieBag::fromRequest($request);

$pipeline = new MiddlewarePipeline([new TrimTrailingSlash()], fallback: new NotFoundHandler());
$response = $pipeline->handle($request);

(new SapiEmitter())->emit($cookies->applyTo($response), $request);
```

| Member | Notes |
| ------ | ----- |
| `__construct(int $chunkSize = 8_388_608)` | The number of bytes read from the body stream per write. A value below 1 throws `InvalidArgumentException`. The 8 MiB default sends an ordinary HTML or JSON response in a single read, while a multi-gigabyte download still costs constant memory. |
| `emit(ResponseInterface $response, ?RequestInterface $request = null): void` | Sends the response. `$request` is read for one purpose only — detecting a `HEAD` request, which must receive no body. Pass `null` when the request is known not to be `HEAD`. Throws `RuntimeException` if output has already started. |

- **Headers are merged, not replaced.** Every `Set-Cookie` value is sent with PHP's `replace` flag false, the first
  value included, so a cookie PHP itself has queued survives — most importantly the session cookie
  `session_start()` writes inside `NativeSession`, which never appears in the `Response` object. Other headers
  replace on their first value and append on the rest.
- **No body is sent** on a 1xx, 204 or 304 response, or when answering a `HEAD` request. The body stream is not even
  touched in those cases, so a lazily-populated body is never realised.
- **Output buffers are left alone.** The emitter never opens, flushes, or cleans one: a buffer it did not open may
  hold output the caller intends to keep. If output has already started, `emit()` throws before writing anything,
  naming the file and line PHP blames — so the caller is free to emit a different response instead.
- **`Content-Length` is yours to set.** The emitter never computes or rewrites it. Deriving it from the stream size
  would truncate the response whenever output compression rewrites the body afterwards.
- Header names and values are sent verbatim: PSR-7 preserves the caller's casing deliberately, and nothing here
  re-folds, trims, or normalises them.
- A custom reason phrase set through `withStatus()` reaches the status line. Under HTTP/2 the protocol has no reason
  phrase on the wire, so PHP discards it.
````

- [ ] **Step 2: Update the `## Cookies` intro to point forward**

In the paragraph opening the `## Cookies` section, the `CookieBag` sentence currently ends at queuing cookies on the response. Append one sentence so the two features connect:

```markdown
The headers a `CookieBag` applies reach the browser through [`SapiEmitter`](#emitting-a-response), which merges them
with any cookie PHP has queued itself.
```

- [ ] **Step 3: Update `README.md:38`**

The `Manychois\PhpStrong\Http` row of the `### Utilities` table. Append to its Summary cell, leaving the rest of the row untouched:

```
 Plus `SapiEmitter`, which sends a finished response to the SAPI and is the one place the response's headers and PHP's own queued cookies are merged.
```

Do **not** put it in the PSR-15 row. No PSR governs response emission, and filing it there would assert otherwise — the same ruling the cookie classes got.

- [ ] **Step 4: Close the hand-off note in the cookies spec**

In `docs/superpowers/specs/2026-08-25-http-cookies-design.md`, the "Hand-off to the emitter spec" section ends with a paragraph beginning "Until that spec ships…". Replace that paragraph with:

```markdown
That spec is now written: `docs/superpowers/specs/2026-08-25-http-response-emitter-design.md`, and the rule is
implemented and tested in `SapiEmitter::emitHeaders()`. `CookieBag::applyTo()` is observable end to end.
```

Leave the rest of the section as it stands — the rule's reasoning belongs on both sides of the hand-off.

- [ ] **Step 5: Verify the docs build and the links resolve**

Run: `grep -n 'Emitting a response' docs/http.md README.md`

Expected: the heading in `docs/http.md` and no stale references elsewhere. Confirm the anchor `#emitting-a-response` in Step 2 matches the heading exactly, lowercased with spaces as hyphens.

- [ ] **Step 6: Run the gates**

Run: `composer phpcbf && composer phpcs && composer phpstan && composer test`

Expected: all clean. Documentation changes cannot break them, which is precisely why this runs — a dirty tree from an earlier task would surface here.

- [ ] **Step 7: Commit**

```bash
git add docs/http.md README.md docs/superpowers/specs/2026-08-25-http-cookies-design.md
git commit -m "docs(http): document the response emitter and close the cookies hand-off"
```

---

## Done when

- `composer phpcs` clean, `composer phpstan` reports `[OK]`, `composer test` green.
- `src/Http/SapiEmitter.php` calls `header()` and `headers_sent()` unqualified — grep for `\header(` and `\headers_sent(` and find nothing.
- Every `Set-Cookie` header emits with `replace = false`, asserted against a real `CookieBag::applyTo()` result.
- A body stream returning `''` before `eof()` terminates rather than hanging.
- `docs/http.md` documents a complete server request cycle for the first time.
