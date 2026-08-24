<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\Http;

use Manychois\PhpStrong\Http\Internal\PipelineStep;
use Override;
use Psr\Http\Message\ResponseInterface as IResponse;
use Psr\Http\Message\ServerRequestInterface as IServerRequest;
use Psr\Http\Server\MiddlewareInterface as IMiddleware;
use Psr\Http\Server\RequestHandlerInterface as IRequestHandler;

/**
 * A PSR-15 request handler that dispatches middleware in order, ending at a fallback handler.
 */
final class MiddlewarePipeline implements IRequestHandler
{
    /**
     * @var list<IMiddleware|string> Middleware instances; a string is a container service id resolved (and replaced
     * in place) on first dispatch.
     */
    private array $middlewares;
    private readonly IRequestHandler $fallback;

    /**
     * Creates a pipeline over an ordered list of middleware.
     *
     * @param iterable $middlewares Middleware instances, or container service ids resolved on first dispatch.
     * @param IRequestHandler $fallback The handler producing the response when no middleware short-circuits.
     *
     * @phpstan-param iterable<mixed> $middlewares
     */
    public function __construct(iterable $middlewares, IRequestHandler $fallback)
    {
        $list = [];
        foreach ($middlewares as $middleware) {
            \assert($middleware instanceof IMiddleware);
            $list[] = $middleware;
        }

        $this->middlewares = $list;
        $this->fallback = $fallback;
    }

    /**
     * Returns the handler that produces the response when the middleware list is exhausted.
     *
     * @return IRequestHandler The fallback handler.
     *
     * @internal
     */
    public function fallbackHandler(): IRequestHandler
    {
        return $this->fallback;
    }

    /**
     * Returns the middleware at a position, or `null` past the end of the list.
     *
     * @param int $index The zero-based position.
     *
     * @return ?IMiddleware The middleware, or `null` when `$index` is past the end.
     *
     * @internal
     *
     * @phpstan-param non-negative-int $index
     */
    public function middlewareAt(int $index): ?IMiddleware
    {
        $middleware = $this->middlewares[$index] ?? null;
        \assert(!\is_string($middleware));

        return $middleware;
    }

    #region implements IRequestHandler

    /**
     * @inheritDoc
     */
    #[Override]
    public function handle(IServerRequest $request): IResponse
    {
        return (new PipelineStep($this, 0))->handle($request);
    }

    #endregion implements IRequestHandler
}
