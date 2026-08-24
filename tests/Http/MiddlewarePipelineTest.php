<?php

declare(strict_types=1);

namespace Manychois\PhpStrongTests\Http;

use Closure;
use Manychois\PhpStrong\Http\MiddlewarePipeline;
use Manychois\PhpStrong\Http\Response;
use Manychois\PhpStrong\Http\ServerRequest;
use Override;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface as IResponse;
use Psr\Http\Message\ServerRequestInterface as IServerRequest;
use Psr\Http\Server\MiddlewareInterface as IMiddleware;
use Psr\Http\Server\RequestHandlerInterface as IRequestHandler;
use RuntimeException;

final class MiddlewarePipelineTest extends TestCase
{
    #[Test]
    public function handle_runsMiddlewaresInOrderThenTheFallback(): void
    {
        $log = [];
        $pipeline = new MiddlewarePipeline(
            [
                self::logging('a', $log),
                self::logging('b', $log),
            ],
            new FixedResponseHandler(new Response(204), $log),
        );

        $response = $pipeline->handle(new ServerRequest());

        self::assertSame(['a', 'b', 'fallback'], $log);
        self::assertSame(204, $response->getStatusCode());
    }

    #[Test]
    public function handle_middlewareReturningWithoutCallingItsHandlerShortCircuits(): void
    {
        $log = [];
        $short = new CallbackMiddleware(
            static fn (IServerRequest $request, IRequestHandler $handler): IResponse => new Response(403)
        );
        $fallback = new FixedResponseHandler(new Response(200), $log);
        $pipeline = new MiddlewarePipeline([$short, self::logging('after', $log)], $fallback);

        $response = $pipeline->handle(new ServerRequest());

        self::assertSame(403, $response->getStatusCode());
        self::assertSame([], $log);
    }

    #[Test]
    public function handle_emptyMiddlewareListDelegatesToTheFallback(): void
    {
        $log = [];
        $pipeline = new MiddlewarePipeline([], new FixedResponseHandler(new Response(200), $log));

        self::assertSame(200, $pipeline->handle(new ServerRequest())->getStatusCode());
        self::assertSame(['fallback'], $log);
    }

    #[Test]
    public function handle_repeatedCallsStartFromTheFirstMiddleware(): void
    {
        $log = [];
        $pipeline = new MiddlewarePipeline(
            [self::logging('a', $log)],
            new FixedResponseHandler(new Response(200), $log),
        );

        $pipeline->handle(new ServerRequest());
        $pipeline->handle(new ServerRequest());

        self::assertSame(['a', 'fallback', 'a', 'fallback'], $log);
    }

    #[Test]
    public function handle_aMiddlewareMayDispatchASubRequestThroughItsOwnPipeline(): void
    {
        $log = [];
        $pipeline = null;
        $recursing = new CallbackMiddleware(
            static function (IServerRequest $request, IRequestHandler $handler) use (&$log, &$pipeline): IResponse {
                $depth = $request->getAttribute('depth', 0);
                $log[] = 'a' . $depth;
                if ($depth === 0) {
                    \assert($pipeline instanceof MiddlewarePipeline);
                    $pipeline->handle($request->withAttribute('depth', 1));
                }

                return $handler->handle($request);
            }
        );
        $pipeline = new MiddlewarePipeline(
            [$recursing, self::logging('b', $log)],
            new FixedResponseHandler(new Response(200), $log),
        );

        $pipeline->handle(new ServerRequest());

        self::assertSame(['a0', 'a1', 'b', 'fallback', 'b', 'fallback'], $log);
    }

    #[Test]
    public function handle_worksWithForeignResponseAndHandlerDoubles(): void
    {
        $foreignResponse = $this->createStub(IResponse::class);
        $foreignHandler = new class ($foreignResponse) implements IRequestHandler {
            public function __construct(private readonly IResponse $response)
            {
            }

            #[Override]
            public function handle(IServerRequest $request): IResponse
            {
                return $this->response;
            }
        };

        $pipeline = new MiddlewarePipeline([], $foreignHandler);

        self::assertSame($foreignResponse, $pipeline->handle(new ServerRequest()));
    }

    #[Test]
    public function handle_anExceptionFromAMiddlewarePropagatesUnchanged(): void
    {
        $boom = new RuntimeException('boom');
        $throwing = new CallbackMiddleware(
            static fn (IServerRequest $request, IRequestHandler $handler): IResponse => throw $boom
        );
        $pipeline = new MiddlewarePipeline([$throwing], new FixedResponseHandler(new Response(200)));

        $this->expectExceptionObject($boom);
        $pipeline->handle(new ServerRequest());
    }

    #[Test]
    public function handle_anExceptionFromTheFallbackPropagatesUnchanged(): void
    {
        $boom = new RuntimeException('fallback boom');
        $throwingFallback = new class ($boom) implements IRequestHandler {
            public function __construct(private readonly RuntimeException $ex)
            {
            }

            #[Override]
            public function handle(IServerRequest $request): IResponse
            {
                throw $this->ex;
            }
        };
        $pipeline = new MiddlewarePipeline([], $throwingFallback);

        $this->expectExceptionObject($boom);
        $pipeline->handle(new ServerRequest());
    }

    /**
     * Creates a middleware that appends `$name` to `$log` and delegates to its handler.
     *
     * @param string $name The log entry.
     * @param array<string> $log The shared call log, taken by reference.
     *
     * @return IMiddleware The middleware.
     */
    private static function logging(string $name, array &$log): IMiddleware
    {
        return new CallbackMiddleware(
            static function (IServerRequest $request, IRequestHandler $handler) use ($name, &$log): IResponse {
                $log[] = $name;

                return $handler->handle($request);
            }
        );
    }
}

final class CallbackMiddleware implements IMiddleware
{
    /**
     * @param Closure(IServerRequest, IRequestHandler): IResponse $fn The processing logic.
     */
    public function __construct(private readonly Closure $fn)
    {
    }

    #[Override]
    public function process(IServerRequest $request, IRequestHandler $handler): IResponse
    {
        return ($this->fn)($request, $handler);
    }
}

final class FixedResponseHandler implements IRequestHandler
{
    /**
     * @var array<string>
     */
    private array $log;

    /**
     * @param IResponse $response The response returned by every call.
     * @param array<string> $log A shared call log appended with `fallback`, taken by reference.
     */
    public function __construct(private readonly IResponse $response, array &$log = [])
    {
        $this->log = &$log;
    }

    #[Override]
    public function handle(IServerRequest $request): IResponse
    {
        $this->log[] = 'fallback';

        return $this->response;
    }
}
