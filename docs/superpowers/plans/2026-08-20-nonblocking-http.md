# Non-Blocking HTTP Requests Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add `Client::sendAsync(): PendingRequest` so multiple HTTP requests run concurrently over `curl_multi`, with `response()`, `waitAny()`, and destructor-abort semantics.

**Architecture:** A per-client `CurlMultiExecutor` (internal) owns a `curl_multi` handle and reports each finished transfer to a completion callback. `PendingRequest` registers that callback (via `WeakReference`, so discarding the handle still triggers `__destruct` abort), stores the settled `Response` or exception, and pumps the executor inside `response()`/`waitAny()`. The existing synchronous `sendRequest()` path is untouched except for extracting shared validation.

**Tech Stack:** PHP 8.5, ext-curl (`curl_multi_*`), PHPUnit 13, existing `CurlTransport::buildOptions()` / `RawResponse` helpers.

**Spec:** `docs/superpowers/specs/2026-08-20-nonblocking-http-design.md`

## Global Constraints

- PHP >= 8.5; library code lives under `Manychois\PhpStrong\Http` (PSR-4 `src/`).
- Quality gates after every task: `composer phpcbf` → `composer phpcs` → `composer phpstan` → `composer test` — all must pass; 100% statement coverage of `src/` must hold at the end of Task 7 (check `coverage/clover.xml`).
- Coding standard (docs/internal/php-coding-standard.md): `declare(strict_types=1)` with blank lines; interfaces imported with `IXxx` aliases; `#[Override]` on interface implementations; `#region implements IInterface` blocks; methods alphabetical within visibility groups (static before instance, public before private); methods outside regions go above region blocks; PHPDoc on all public/protected members, one blank line between annotation types; `@phpstan-param` for precise types; global PHP constants referenced as `\CURLE_OK` etc. with leading backslash.
- Exception rules (PSR-18): `RequestException` synchronously before sending; `NetworkException` for transfer failures incl. timeouts; `ClientException` for unparseable responses; non-2xx responses returned, never thrown.
- Feature tests live in `feature-tests/` (namespace `Manychois\PhpStrongFeatureTests`), run against a `php -S` fixture server; PHPUnit enforces a 3-second per-test time limit, so keep all delays/timeouts well under that.
- Commit after every task (small commits, message style `feat(http): ...` / `test(http): ...` / `refactor(http): ...` / `docs: ...`, each ending with the Claude co-author trailer used in this repo).

---

### Task 1: Extract shared request validation in Client

**Files:**
- Modify: `src/Http/Client.php`

**Interfaces:**
- Consumes: existing `Client::sendRequest()` (validation currently inline at the top of the method).
- Produces: `private function assertSendable(IRequest $request): void` — throws `RequestException` for empty method, non-http(s) scheme, or missing host. Task 3's `sendAsync()` calls it.

- [ ] **Step 1: Confirm current tests pass (baseline)**

Run: `./vendor/bin/phpunit --no-coverage`
Expected: `OK (304 tests, ...)` (count may have drifted; note it — it must be identical after the refactor).

- [ ] **Step 2: Extract the validation**

In `src/Http/Client.php`, replace the start of `sendRequest()` (the empty-method check, the `$uri`/`$scheme` checks) with a call to a new private method, so `sendRequest()` begins:

```php
    #[Override]
    public function sendRequest(IRequest $request): Response
    {
        $this->assertSendable($request);

        $raw = $this->transport->send($request, $this->options);

        return new Response($raw->statusCode, $raw->reasonPhrase, $raw->headers, $raw->body, $raw->protocolVersion);
    }
```

Add the private method **above** the `#region implements IClient` block (methods outside regions go above region blocks; it is the only such method for now):

```php
    /**
     * Throws when the request lacks what is needed to send it.
     *
     * @param IRequest $request The request to check.
     *
     * @throws RequestException if the request method is empty, or the request URI has an
     * unsupported scheme or no host.
     */
    private function assertSendable(IRequest $request): void
    {
        if ($request->getMethod() === '') {
            throw new RequestException('Request method must not be empty.', $request);
        }

        $uri = $request->getUri();
        $scheme = $uri->getScheme();
        if (!in_array($scheme, ['http', 'https'], true)) {
            throw new RequestException(
                sprintf('Unsupported URI scheme "%s"; expected "http" or "https".', $scheme),
                $request,
            );
        }
        if ($uri->getHost() === '') {
            throw new RequestException('Request URI must include a host.', $request);
        }
    }
```

Keep the existing `@throws` lines on `sendRequest()`'s docblock unchanged (they still describe its behaviour).

- [ ] **Step 3: Run gates**

Run: `composer phpcbf && composer phpcs && composer phpstan && composer test`
Expected: all green, same test count as Step 1.

- [ ] **Step 4: Commit**

```bash
git add src/Http/Client.php
git commit -m "refactor(http): extract request validation into Client::assertSendable"
```

---

### Task 2: Fixture server delay parameter + shared server trait

**Files:**
- Modify: `feature-tests/Http/fixtures/server.php`
- Create: `feature-tests/Http/FixtureServerTrait.php`
- Modify: `feature-tests/Http/ClientFeatureTest.php`

**Interfaces:**
- Produces: `/slow?ms=N` endpoint (defaults to 700 ms so the existing timeout test is unaffected); trait `FixtureServerTrait` with `static startFixtureServer(): void`, `static stopFixtureServer(): void`, `static url(string $pathAndQuery): string`, `static findFreePort(): int`. The server starts with `PHP_CLI_SERVER_WORKERS=4` so concurrent requests genuinely overlap (php -S is single-threaded otherwise). Task 4/5/6 feature tests use this trait.

- [ ] **Step 1: Parameterize `/slow`**

In `feature-tests/Http/fixtures/server.php` replace the `/slow` case with:

```php
    case '/slow':
        usleep((int) ($_GET['ms'] ?? 700) * 1000);
        echo 'slow';
        break;
```

- [ ] **Step 2: Extract the server management into a trait**

Create `feature-tests/Http/FixtureServerTrait.php` with the logic currently in `ClientFeatureTest` (port finding, proc_open, readiness poll, teardown), with two changes: the env passes `PHP_CLI_SERVER_WORKERS=4`, and members are renamed per the trait interface above:

```php
<?php

declare(strict_types=1);

namespace Manychois\PhpStrongFeatureTests\Http;

use RuntimeException;

/**
 * Starts and stops the PHP built-in fixture server for feature tests.
 */
trait FixtureServerTrait
{
    private static int $port;

    /**
     * @var ?resource
     */
    private static $serverProcess = null;

    private static function startFixtureServer(): void
    {
        self::$port = self::findFreePort();
        $command = [
            \PHP_BINARY,
            '-S',
            sprintf('127.0.0.1:%d', self::$port),
            __DIR__ . '/fixtures/server.php',
        ];
        $env = getenv();
        $env['PHP_CLI_SERVER_WORKERS'] = '4';
        $process = proc_open($command, [2 => ['pipe', 'w']], $pipes, null, $env);
        if ($process === false) {
            throw new RuntimeException('Failed to start the PHP built-in server.');
        }

        self::$serverProcess = $process;
        self::waitUntilServerIsReady();
    }

    private static function stopFixtureServer(): void
    {
        if (self::$serverProcess === null) {
            return;
        }

        proc_terminate(self::$serverProcess);
        proc_close(self::$serverProcess);
        self::$serverProcess = null;
    }

    private static function url(string $pathAndQuery): string
    {
        return sprintf('http://127.0.0.1:%d%s', self::$port, $pathAndQuery);
    }

    private static function findFreePort(): int
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
        if ($socket === false) {
            throw new RuntimeException(sprintf('Failed to open a socket: %s', $error));
        }

        $name = stream_socket_get_name($socket, false);
        assert(is_string($name));
        fclose($socket);
        $colonPos = strrpos($name, ':');
        assert($colonPos !== false);

        return (int) substr($name, $colonPos + 1);
    }

    private static function waitUntilServerIsReady(): void
    {
        $deadline = microtime(true) + 5.0;
        while (microtime(true) < $deadline) {
            $socket = @fsockopen('127.0.0.1', self::$port, $errno, $error, 0.1);
            if ($socket !== false) {
                fclose($socket);

                return;
            }

            usleep(50_000);
        }

        throw new RuntimeException('The PHP built-in server did not become ready in time.');
    }
}
```

- [ ] **Step 3: Rewire `ClientFeatureTest` onto the trait**

In `feature-tests/Http/ClientFeatureTest.php`: remove the `$port`/`$serverProcess` properties and the `url()`/`findFreePort()`/`waitUntilServerIsReady()` methods; add `use FixtureServerTrait;` inside the class; replace `setUpBeforeClass()` / `tearDownAfterClass()` bodies with `self::startFixtureServer();` / `self::stopFixtureServer();`. The `connection_to_a_closed_port_throws_NetworkException` test keeps using `self::findFreePort()` (now from the trait). Remove the now-unused `RuntimeException` import if phpcbf doesn't.

- [ ] **Step 4: Run gates**

Run: `composer phpcbf && composer phpcs && composer phpstan && composer test`
Expected: all green, same behaviour as before (existing feature tests unchanged in outcome).

- [ ] **Step 5: Commit**

```bash
git add feature-tests
git commit -m "test(http): parameterize /slow fixture and extract server trait"
```

---

### Task 3: CurlMultiExecutor + PendingRequest + Client::sendAsync (happy path)

**Files:**
- Create: `src/Http/Internal/CurlMultiExecutor.php`
- Create: `src/Http/PendingRequest.php`
- Modify: `src/Http/Client.php`
- Create: `feature-tests/Http/ClientAsyncFeatureTest.php`

**Interfaces:**
- Consumes: `CurlTransport::buildOptions(IRequest, RequestOptions): array<int,mixed>`, `RawResponse::fromHeaderLines(list<string>, string): RawResponse` (throws `ClientException`), `Client::assertSendable()` from Task 1, `FixtureServerTrait` from Task 2.
- Produces:
  - `CurlMultiExecutor::add(CurlHandle $handle, callable $onComplete): void` where `$onComplete` is `callable(int $errno, string $errorMessage, list<string> $headerLines, string $body): void`;
  - `CurlMultiExecutor::remove(CurlHandle $handle): void` (idempotent);
  - `CurlMultiExecutor::pump(float $maxWait = 0.05): void`;
  - `PendingRequest::__construct(CurlMultiExecutor, CurlHandle, IRequest)` (`@internal`), `PendingRequest::settle(int, string, array, string): void` (`@internal`, public so Task 4's unit tests can drive it), `PendingRequest::response(): Response`;
  - `Client::sendAsync(IRequest): PendingRequest`.
  - Tasks 4–6 extend these classes; signatures above must not change.

- [ ] **Step 1: Write the failing feature test**

Create `feature-tests/Http/ClientAsyncFeatureTest.php`:

```php
<?php

declare(strict_types=1);

namespace Manychois\PhpStrongFeatureTests\Http;

use Manychois\PhpStrong\Http\Client;
use Manychois\PhpStrong\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Feature tests for {@see Client::sendAsync()} against a real PHP built-in server.
 */
final class ClientAsyncFeatureTest extends TestCase
{
    use FixtureServerTrait;

    public static function setUpBeforeClass(): void
    {
        self::startFixtureServer();
    }

    public static function tearDownAfterClass(): void
    {
        self::stopFixtureServer();
    }

    #[Test]
    public function sendAsync_returns_a_response_with_status_headers_and_body(): void
    {
        $client = new Client();

        $pending = $client->sendAsync(new Request('GET', self::url('/hello')));
        $response = $pending->response();

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('php-strong', $response->getHeaderLine('X-Server'));
        self::assertSame('Hello, world!', (string) $response->getBody());
    }

    #[Test]
    public function concurrent_requests_overlap_instead_of_running_serially(): void
    {
        $client = new Client();

        $start = microtime(true);
        $p1 = $client->sendAsync(new Request('GET', self::url('/slow?ms=400')));
        $p2 = $client->sendAsync(new Request('GET', self::url('/slow?ms=400')));
        self::assertSame('slow', (string) $p1->response()->getBody());
        self::assertSame('slow', (string) $p2->response()->getBody());
        $elapsed = microtime(true) - $start;

        self::assertLessThan(0.75, $elapsed, 'Two 400ms requests must overlap, not serialize.');
    }

    #[Test]
    public function responses_can_be_collected_in_any_order(): void
    {
        $client = new Client();

        $slow = $client->sendAsync(new Request('GET', self::url('/slow?ms=300')));
        $fast = $client->sendAsync(new Request('GET', self::url('/hello')));

        self::assertSame('slow', (string) $slow->response()->getBody());
        self::assertSame('Hello, world!', (string) $fast->response()->getBody());
    }

    #[Test]
    public function non_2xx_responses_are_returned_not_thrown(): void
    {
        $client = new Client();

        $pending = $client->sendAsync(new Request('GET', self::url('/status?code=503')));

        self::assertSame(503, $pending->response()->getStatusCode());
    }
}
```

- [ ] **Step 2: Run it to verify it fails**

Run: `./vendor/bin/phpunit --no-coverage feature-tests/Http/ClientAsyncFeatureTest.php`
Expected: ERROR — `Call to undefined method ...Client::sendAsync()`.

- [ ] **Step 3: Implement CurlMultiExecutor**

Create `src/Http/Internal/CurlMultiExecutor.php`:

```php
<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\Http\Internal;

use CurlHandle;
use CurlMultiHandle;

/**
 * Runs multiple cURL transfers concurrently and reports each completion to a callback.
 *
 * @internal
 */
final class CurlMultiExecutor
{
    private readonly CurlMultiHandle $multiHandle;

    /**
     * @var array<int,callable(int,string,list<string>,string):void> Completion callbacks
     * keyed by the spl_object_id of the easy handle.
     */
    private array $callbacks = [];

    /**
     * @var array<int,CurlHandle> Active easy handles keyed by their spl_object_id.
     */
    private array $handles = [];

    /**
     * @var array<int,list<string>> Collected response header lines keyed by the
     * spl_object_id of the easy handle.
     */
    private array $headerLines = [];

    public function __construct()
    {
        $this->multiHandle = curl_multi_init();
    }

    /**
     * Attaches a transfer to this executor.
     *
     * @param CurlHandle $handle The fully configured easy handle.
     * @param callable $onComplete Called once when the transfer finishes, with the cURL
     * result code, an error message ('' on success), the collected header lines, and the body.
     *
     * @phpstan-param callable(int,string,list<string>,string):void $onComplete
     */
    public function add(CurlHandle $handle, callable $onComplete): void
    {
        $id = spl_object_id($handle);
        $this->callbacks[$id] = $onComplete;
        $this->handles[$id] = $handle;
        $this->headerLines[$id] = [];
        curl_setopt(
            $handle,
            \CURLOPT_HEADERFUNCTION,
            function (CurlHandle $h, string $line): int {
                $trimmed = trim($line);
                if ($trimmed !== '') {
                    $this->headerLines[spl_object_id($h)][] = $trimmed;
                }

                return strlen($line);
            },
        );
        curl_multi_add_handle($this->multiHandle, $handle);
    }

    /**
     * Performs one iteration of transfer processing: runs ready I/O, optionally waits
     * briefly for socket activity, then delivers every finished transfer to its callback.
     *
     * @param float $maxWait The maximum time to wait for socket activity, in seconds.
     */
    public function pump(float $maxWait = 0.05): void
    {
        $stillRunning = 0;
        curl_multi_exec($this->multiHandle, $stillRunning);
        if ($stillRunning > 0 && $maxWait > 0) {
            curl_multi_select($this->multiHandle, $maxWait);
            curl_multi_exec($this->multiHandle, $stillRunning);
        }

        while (true) {
            $info = curl_multi_info_read($this->multiHandle);
            if ($info === false) {
                break;
            }

            $handle = $info['handle'];
            $id = spl_object_id($handle);
            $callback = $this->callbacks[$id];
            $lines = $this->headerLines[$id];
            $errno = $info['result'];
            $error = $errno === \CURLE_OK
                ? ''
                : sprintf('cURL error %d: %s', $errno, curl_strerror($errno) ?? 'unknown error');
            $body = curl_multi_getcontent($handle) ?? '';
            $this->remove($handle);
            $callback($errno, $error, $lines, $body);
        }
    }

    /**
     * Detaches a transfer, aborting it if it has not finished. Safe to call twice.
     *
     * @param CurlHandle $handle The easy handle to detach.
     */
    public function remove(CurlHandle $handle): void
    {
        $id = spl_object_id($handle);
        if (!array_key_exists($id, $this->handles)) {
            return;
        }

        curl_multi_remove_handle($this->multiHandle, $handle);
        unset($this->callbacks[$id], $this->handles[$id], $this->headerLines[$id]);
    }
}
```

Note: methods are ordered `add`, `pump`, `remove` (alphabetical, all public instance).

- [ ] **Step 4: Implement PendingRequest**

Create `src/Http/PendingRequest.php`:

```php
<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\Http;

use CurlHandle;
use Manychois\PhpStrong\Http\Internal\CurlMultiExecutor;
use Manychois\PhpStrong\Http\Internal\RawResponse;
use Psr\Http\Message\RequestInterface as IRequest;
use WeakReference;

/**
 * A handle to an HTTP request that has been dispatched but whose response has not
 * been collected yet. Created by {@see Client::sendAsync()}.
 */
final class PendingRequest
{
    private bool $settled = false;
    private ?Response $response = null;
    private ?ClientException $error = null;

    /**
     * @param CurlMultiExecutor $executor The executor driving this transfer.
     * @param CurlHandle $handle The configured easy handle for this transfer.
     * @param IRequest $request The request being sent.
     *
     * @internal Use {@see Client::sendAsync()} to create instances.
     */
    public function __construct(
        private readonly CurlMultiExecutor $executor,
        private readonly CurlHandle $handle,
        private readonly IRequest $request,
    ) {
        $weak = WeakReference::create($this);
        $executor->add(
            $handle,
            static function (int $errno, string $error, array $headerLines, string $body) use ($weak): void {
                $weak->get()?->settle($errno, $error, $headerLines, $body);
            },
        );
    }

    /**
     * Waits until this transfer completes and returns its response.
     * While waiting, every transfer on the same executor makes progress.
     * Repeated calls return the same response or rethrow the same exception.
     *
     * @return Response The response received.
     *
     * @throws NetworkException if the transfer failed (e.g. timeout, connection refused).
     * @throws ClientException if the response could not be parsed.
     */
    public function response(): Response
    {
        while (!$this->settled) {
            $this->executor->pump();
        }

        if ($this->error !== null) {
            throw $this->error;
        }

        assert($this->response !== null);

        return $this->response;
    }

    /**
     * Records the transfer outcome. Called by the executor's completion callback.
     *
     * @param int $errno The cURL result code (\CURLE_OK on success).
     * @param string $errorMessage The error message; '' on success.
     * @param list<string> $headerLines The collected response header lines.
     * @param string $body The response body.
     *
     * @internal
     */
    public function settle(int $errno, string $errorMessage, array $headerLines, string $body): void
    {
        $this->settled = true;
        if ($errno !== \CURLE_OK) {
            $this->error = new NetworkException($errorMessage, $this->request);

            return;
        }

        try {
            $raw = RawResponse::fromHeaderLines($headerLines, $body);
            $this->response = new Response(
                $raw->statusCode,
                $raw->reasonPhrase,
                $raw->headers,
                $raw->body,
                $raw->protocolVersion,
            );
        } catch (ClientException $ex) {
            $this->error = $ex;
        }
    }
}
```

Note: public instance methods ordered alphabetically: `response`, `settle`. `$this->handle` is unused until Task 6 (`__destruct`); phpstan may flag it as write-only — if it does, add `__destruct` in this task instead of Task 6 by copying Task 6 Step 2's method verbatim, and skip Task 6 Steps 2–3.

- [ ] **Step 5: Add Client::sendAsync**

In `src/Http/Client.php`:

1. Add import: `use Manychois\PhpStrong\Http\Internal\CurlMultiExecutor;`
2. Add a property below the existing ones: `private ?CurlMultiExecutor $executor = null;`
3. Add this method **above** `assertSendable()` (public before private, both above the region block):

```php
    /**
     * Dispatches the request without waiting for the response and returns a handle to
     * collect it later. Transfers of the same client run concurrently. This method is
     * an extension beyond PSR-18.
     *
     * @param IRequest $request The request to send.
     *
     * @return PendingRequest The handle for collecting the response.
     *
     * @throws RequestException if the request method is empty, the request URI has an
     * unsupported scheme or no host, or the request body cannot be read.
     */
    public function sendAsync(IRequest $request): PendingRequest
    {
        $this->assertSendable($request);

        $this->executor ??= new CurlMultiExecutor();
        $handle = curl_init();
        curl_setopt_array($handle, CurlTransport::buildOptions($request, $this->options));
        $pending = new PendingRequest($this->executor, $handle, $request);
        $this->executor->pump(0.0);

        return $pending;
    }
```

4. Add import `use Manychois\PhpStrong\Http\Internal\CurlTransport;` if not already present (it is — `CurlTransport` is already imported for the default transport).

- [ ] **Step 6: Run the feature test**

Run: `./vendor/bin/phpunit --no-coverage feature-tests/Http/ClientAsyncFeatureTest.php`
Expected: 4 tests PASS. If the overlap test is flaky on elapsed time, the fixture server workers are not active — verify `PHP_CLI_SERVER_WORKERS` is passed in `FixtureServerTrait`.

- [ ] **Step 7: Run gates**

Run: `composer phpcbf && composer phpcs && composer phpstan && composer test`
Expected: all green. (Coverage of the new classes is partial until Tasks 4–6 — that is fine; 100% is asserted at Task 7.)

- [ ] **Step 8: Commit**

```bash
git add src/Http feature-tests/Http/ClientAsyncFeatureTest.php
git commit -m "feat(http): add Client::sendAsync with curl_multi-backed PendingRequest"
```

---

### Task 4: Error paths — network failure, parse failure, idempotency, sync validation

**Files:**
- Modify: `feature-tests/Http/ClientAsyncFeatureTest.php`
- Create: `tests/Http/PendingRequestTest.php`
- Modify: `tests/Http/ClientTest.php`

**Interfaces:**
- Consumes: `PendingRequest::settle()` (public `@internal`, from Task 3) — unit tests drive it directly to reach the parse-failure branch that a well-behaved fixture server cannot produce; `Client::sendAsync()` validation via `assertSendable()`.
- Produces: nothing new — this task only adds tests (all production code exists after Task 3).

- [ ] **Step 1: Add failing/error feature tests**

Append to `ClientAsyncFeatureTest` (imports to add: `Manychois\PhpStrong\Http\NetworkException`, `Manychois\PhpStrong\Http\RequestOptions`):

```php
    #[Test]
    public function transfer_failure_throws_NetworkException_from_response(): void
    {
        $client = new Client();
        $request = new Request('GET', sprintf('http://127.0.0.1:%d/', self::findFreePort()));

        $pending = $client->sendAsync($request);

        try {
            $pending->response();
            self::fail('Expected NetworkException.');
        } catch (NetworkException $ex) {
            self::assertSame($request, $ex->getRequest());
            self::assertStringContainsString('cURL error', $ex->getMessage());
        }
    }

    #[Test]
    public function timeout_throws_NetworkException_from_response(): void
    {
        $client = new Client(new RequestOptions(timeout: 0.2));

        $pending = $client->sendAsync(new Request('GET', self::url('/slow?ms=800')));

        $this->expectException(NetworkException::class);
        $pending->response();
    }

    #[Test]
    public function response_is_idempotent_for_success_and_failure(): void
    {
        $client = new Client();

        $ok = $client->sendAsync(new Request('GET', self::url('/hello')));
        self::assertSame($ok->response(), $ok->response());

        $bad = $client->sendAsync(
            new Request('GET', sprintf('http://127.0.0.1:%d/', self::findFreePort())),
        );
        $first = null;
        try {
            $bad->response();
        } catch (NetworkException $ex) {
            $first = $ex;
        }
        try {
            $bad->response();
            self::fail('Expected NetworkException on second call.');
        } catch (NetworkException $ex) {
            self::assertSame($first, $ex);
        }
    }

    #[Test]
    public function mixed_batch_delivers_each_outcome_to_its_own_handle(): void
    {
        $client = new Client();

        $ok = $client->sendAsync(new Request('GET', self::url('/hello')));
        $serverError = $client->sendAsync(new Request('GET', self::url('/status?code=500')));
        $failed = $client->sendAsync(
            new Request('GET', sprintf('http://127.0.0.1:%d/', self::findFreePort())),
        );

        self::assertSame(200, $ok->response()->getStatusCode());
        self::assertSame(500, $serverError->response()->getStatusCode());
        $this->expectException(NetworkException::class);
        $failed->response();
    }
```

- [ ] **Step 2: Add the PendingRequest unit test (parse-failure branch)**

Create `tests/Http/PendingRequestTest.php`:

```php
<?php

declare(strict_types=1);

namespace Manychois\PhpStrongTests\Http;

use Manychois\PhpStrong\Http\ClientException;
use Manychois\PhpStrong\Http\Internal\CurlMultiExecutor;
use Manychois\PhpStrong\Http\PendingRequest;
use Manychois\PhpStrong\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for {@see PendingRequest}. Transfer-level behaviour is covered by
 * feature tests; here settle() is driven directly for branches a well-behaved
 * HTTP server cannot produce.
 */
final class PendingRequestTest extends TestCase
{
    #[Test]
    public function unparseable_response_throws_ClientException(): void
    {
        $pending = $this->makePending();

        $pending->settle(\CURLE_OK, '', ['not a status line'], 'body');

        $this->expectException(ClientException::class);
        $this->expectExceptionMessage('Malformed HTTP response');
        $pending->response();
    }

    #[Test]
    public function settled_response_is_returned_without_pumping(): void
    {
        $pending = $this->makePending();

        $pending->settle(\CURLE_OK, '', ['HTTP/1.1 204 No Content'], '');

        $response = $pending->response();
        self::assertSame(204, $response->getStatusCode());
        self::assertSame($response, $pending->response());
    }

    private function makePending(): PendingRequest
    {
        $handle = curl_init();
        assert($handle !== false);

        return new PendingRequest(new CurlMultiExecutor(), $handle, new Request('GET', 'http://example.com/'));
    }
}
```

- [ ] **Step 3: Add sendAsync validation unit test**

Append to `tests/Http/ClientTest.php`:

```php
    #[Test]
    public function sendAsync_throws_RequestException_synchronously_for_invalid_requests(): void
    {
        $client = new Client(transport: $this->fakeTransport());

        foreach (
            [
                new Request('', 'http://example.com/'),
                new Request('GET', 'ftp://example.com/file'),
            ] as $request
        ) {
            try {
                $client->sendAsync($request);
                self::fail('Expected RequestException.');
            } catch (RequestException $ex) {
                self::assertSame($request, $ex->getRequest());
            }
        }
    }
```

(No transfer is registered when validation fails, so this touches no network.)

- [ ] **Step 4: Run the new tests**

Run: `./vendor/bin/phpunit --no-coverage tests/Http/PendingRequestTest.php tests/Http/ClientTest.php feature-tests/Http/ClientAsyncFeatureTest.php`
Expected: all PASS.

- [ ] **Step 5: Run gates**

Run: `composer phpcbf && composer phpcs && composer phpstan && composer test`
Expected: all green.

- [ ] **Step 6: Commit**

```bash
git add tests/Http feature-tests/Http/ClientAsyncFeatureTest.php
git commit -m "test(http): cover sendAsync error paths and PendingRequest settlement"
```

---

### Task 5: PendingRequest::waitAny()

**Files:**
- Modify: `src/Http/PendingRequest.php`
- Modify: `tests/Http/PendingRequestTest.php`
- Modify: `feature-tests/Http/ClientAsyncFeatureTest.php`

**Interfaces:**
- Consumes: `CurlMultiExecutor::pump(float)`, `PendingRequest::$settled`/`$executor` (private, same-class access).
- Produces: `public static function waitAny(iterable $requests): PendingRequest`.

- [ ] **Step 1: Write the failing unit test (input validation)**

Append to `tests/Http/PendingRequestTest.php` (add import `InvalidArgumentException`):

```php
    #[Test]
    public function waitAny_rejects_empty_input(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('at least one');

        PendingRequest::waitAny([]);
    }

    #[Test]
    public function waitAny_rejects_non_PendingRequest_items(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('PendingRequest');

        PendingRequest::waitAny(['not a pending request']);
    }

    #[Test]
    public function waitAny_returns_an_already_settled_request_immediately(): void
    {
        $pending = $this->makePending();
        $pending->settle(\CURLE_OK, '', ['HTTP/1.1 200 OK'], 'ok');

        self::assertSame($pending, PendingRequest::waitAny([$pending]));
    }
```

- [ ] **Step 2: Run to verify failure**

Run: `./vendor/bin/phpunit --no-coverage tests/Http/PendingRequestTest.php`
Expected: ERROR — `Call to undefined method ...PendingRequest::waitAny()`.

- [ ] **Step 3: Implement waitAny**

In `src/Http/PendingRequest.php`, add import `InvalidArgumentException` and insert this method **above** `response()` (static before instance):

```php
    /**
     * Waits until at least one of the given pending requests completes and returns it.
     * A transfer that failed counts as completed; read the outcome — response or
     * exception — via the returned handle's {@see response()}. Call again with the
     * remaining handles to process completions in arrival order.
     *
     * @param iterable $requests The pending requests to wait on.
     *
     * @return self The first request to complete.
     *
     * @throws InvalidArgumentException if the input is empty or contains a value that
     * is not a PendingRequest.
     *
     * @phpstan-param iterable<mixed> $requests
     */
    public static function waitAny(iterable $requests): self
    {
        $items = [];
        foreach ($requests as $item) {
            if (!$item instanceof self) {
                throw new InvalidArgumentException('waitAny() accepts PendingRequest instances only.');
            }

            $items[] = $item;
        }
        if ($items === []) {
            throw new InvalidArgumentException('waitAny() requires at least one PendingRequest.');
        }

        while (true) {
            $executors = [];
            foreach ($items as $item) {
                if ($item->settled) {
                    return $item;
                }

                $executors[spl_object_id($item->executor)] = $item->executor;
            }
            foreach ($executors as $executor) {
                $executor->pump(0.01);
            }
        }
    }
```

- [ ] **Step 4: Run unit tests**

Run: `./vendor/bin/phpunit --no-coverage tests/Http/PendingRequestTest.php`
Expected: PASS.

- [ ] **Step 5: Add feature tests (real completion order, multiple clients)**

Append to `ClientAsyncFeatureTest` (add import `Manychois\PhpStrong\Http\PendingRequest`):

```php
    #[Test]
    public function waitAny_returns_the_fastest_of_a_batch(): void
    {
        $client = new Client();

        $slow = $client->sendAsync(new Request('GET', self::url('/slow?ms=500')));
        $fast = $client->sendAsync(new Request('GET', self::url('/hello')));

        $winner = PendingRequest::waitAny([$slow, $fast]);
        self::assertSame($fast, $winner);
        self::assertSame('Hello, world!', (string) $winner->response()->getBody());

        self::assertSame($slow, PendingRequest::waitAny([$slow]));
        self::assertSame('slow', (string) $slow->response()->getBody());
    }

    #[Test]
    public function waitAny_spans_multiple_clients(): void
    {
        $clientA = new Client();
        $clientB = new Client();

        $slow = $clientA->sendAsync(new Request('GET', self::url('/slow?ms=400')));
        $fast = $clientB->sendAsync(new Request('GET', self::url('/hello')));

        self::assertSame($fast, PendingRequest::waitAny([$slow, $fast]));
        self::assertSame('slow', (string) $slow->response()->getBody());
    }

    #[Test]
    public function waitAny_returns_a_failed_transfer_as_completed(): void
    {
        $client = new Client();

        $failing = $client->sendAsync(
            new Request('GET', sprintf('http://127.0.0.1:%d/', self::findFreePort())),
        );
        $slow = $client->sendAsync(new Request('GET', self::url('/slow?ms=600')));

        $winner = PendingRequest::waitAny([$failing, $slow]);
        self::assertSame($failing, $winner);
        $this->expectException(NetworkException::class);
        $winner->response();
    }
```

- [ ] **Step 6: Run gates**

Run: `composer phpcbf && composer phpcs && composer phpstan && composer test`
Expected: all green.

- [ ] **Step 7: Commit**

```bash
git add src/Http/PendingRequest.php tests/Http/PendingRequestTest.php feature-tests/Http/ClientAsyncFeatureTest.php
git commit -m "feat(http): add PendingRequest::waitAny for first-completion waits"
```

---

### Task 6: Destructor abort

**Files:**
- Modify: `src/Http/PendingRequest.php`
- Modify: `feature-tests/Http/ClientAsyncFeatureTest.php`

**Interfaces:**
- Consumes: `CurlMultiExecutor::remove(CurlHandle)` (idempotent — the executor already removed the handle if the transfer finished).
- Produces: `PendingRequest::__destruct()`.

(If Task 3 already added `__destruct` due to a phpstan write-only-property complaint, do Steps 1 and 4–5 only.)

- [ ] **Step 1: Add the failing feature test**

Append to `ClientAsyncFeatureTest`:

```php
    #[Test]
    public function discarding_a_pending_request_aborts_it_without_disturbing_others(): void
    {
        $client = new Client();

        $discarded = $client->sendAsync(new Request('GET', self::url('/slow?ms=600')));
        $kept = $client->sendAsync(new Request('GET', self::url('/hello')));

        unset($discarded);

        $start = microtime(true);
        self::assertSame('Hello, world!', (string) $kept->response()->getBody());
        self::assertLessThan(0.5, microtime(true) - $start, 'Aborted transfer must not delay the kept one.');
    }
```

(The elapsed assertion is what proves the abort: with the 600 ms transfer still attached, `response()` would keep pumping it; aborted, the fast response returns quickly and nothing waits on the discarded transfer.)

- [ ] **Step 2: Implement the destructor**

In `src/Http/PendingRequest.php`, add **above** `response()` (magic method sorts before regular methods; keep `waitAny` above it as static comes first):

```php
    public function __destruct()
    {
        if (!$this->settled) {
            $this->executor->remove($this->handle);
        }
    }
```

(No docblock required by the standard for `__destruct`; add one line `/** Aborts the transfer if it has not completed. */` to keep phpcs happy if it complains.)

- [ ] **Step 3: Run the feature test**

Run: `./vendor/bin/phpunit --no-coverage feature-tests/Http/ClientAsyncFeatureTest.php`
Expected: PASS. The `WeakReference` in the constructor is what makes this work — verify by temporarily replacing `$weak->get()?->settle(...)` with a strong `$this` capture and watching this test fail (destructor never fires); revert.

- [ ] **Step 4: Run gates**

Run: `composer phpcbf && composer phpcs && composer phpstan && composer test`
Expected: all green.

- [ ] **Step 5: Commit**

```bash
git add src/Http/PendingRequest.php feature-tests/Http/ClientAsyncFeatureTest.php
git commit -m "feat(http): abort discarded PendingRequest transfers via destructor"
```

---

### Task 7: Coverage sweep + documentation

**Files:**
- Modify: `docs/http.md`
- Modify: `README.md`
- Possibly modify: `tests/Http/PendingRequestTest.php` (only if the coverage check exposes gaps)

**Interfaces:**
- Consumes: everything shipped in Tasks 1–6.
- Produces: user-facing reference documentation; 100% statement coverage confirmed.

- [ ] **Step 1: Verify 100% coverage**

Run: `composer test`, then:

```bash
php -r '
$xml = simplexml_load_file("coverage/clover.xml");
foreach ($xml->xpath("//file") as $f) {
    $m = $f->metrics;
    $st = (int)$m["statements"]; $cs = (int)$m["coveredstatements"];
    if ($st !== $cs) echo (string)$f["name"], ": $cs/$st\n";
}
echo "done\n";'
```

Expected: only `done`. If `CurlMultiExecutor` or `PendingRequest` lines are uncovered, add targeted unit tests to `tests/Http/PendingRequestTest.php` driving `settle()`/`pump()`/`remove()` directly (both are constructible without network: `new CurlMultiExecutor()`, `curl_init()`), then re-run. Likely candidates: the `remove()` early-return (call `remove()` twice on the same handle) and `pump()` with no transfers (call `pump(0.0)` on a fresh executor).

- [ ] **Step 2: Document in docs/http.md**

In the `## HTTP Client (PSR-18)` section of `docs/http.md`, extend the class table with two rows and add the throttling paragraph after the table:

```markdown
| `PendingRequest` | — | Handle returned by `sendAsync()`. `response(): Response` waits for and returns this transfer's response (all transfers of the same client progress while waiting; repeated calls return the same result or rethrow the same exception). `static waitAny(iterable $requests): PendingRequest` returns the first handle to complete — failed transfers count as completed and throw from the winner's `response()`. Discarding a handle (`unset`) aborts its transfer. |
| `Client::sendAsync()` | — | `sendAsync(RequestInterface $request): PendingRequest` — dispatches immediately and returns a handle; transfers of one client run concurrently over `curl_multi`. Same validation and exception rules as `sendRequest()`. Not part of PSR-18. |
```

```markdown
`sendAsync()` places no cap on concurrency. To throttle, keep a sliding window:
start N transfers, then each time `PendingRequest::waitAny($window)` yields a
completed handle, remove it from the window, process it, and start the next
request.
```

- [ ] **Step 3: Update the README PSR-18 row**

In `README.md`, extend the PSR-18 row's summary sentence: after "…per the PSR-18 contract", append ", plus a non-PSR `sendAsync()` returning `PendingRequest` handles for concurrent requests over `curl_multi`".

- [ ] **Step 4: Run gates one final time**

Run: `composer phpcbf && composer phpcs && composer phpstan && composer test`
Expected: all green; coverage check from Step 1 prints only `done`.

- [ ] **Step 5: Commit**

```bash
git add docs/http.md README.md tests/Http/PendingRequestTest.php
git commit -m "docs: document sendAsync, PendingRequest and waitAny"
```
