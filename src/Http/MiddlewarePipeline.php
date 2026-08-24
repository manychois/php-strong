<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\Http;

use InvalidArgumentException;
use Manychois\PhpStrong\Http\Internal\PipelineStep;
use Override;
use Psr\Container\ContainerInterface as IContainer;
use Psr\Http\Message\ResponseInterface as IResponse;
use Psr\Http\Message\ServerRequestInterface as IServerRequest;
use Psr\Http\Server\MiddlewareInterface as IMiddleware;
use Psr\Http\Server\RequestHandlerInterface as IRequestHandler;
use RuntimeException;

/**
 * A PSR-15 request handler that dispatches middleware in order, ending at a fallback handler.
 */
final class MiddlewarePipeline implements IRequestHandler
{
    public readonly IRequestHandler $fallback;
    /**
     * @var list<IMiddleware|string> Middleware instances; a string is a container service id resolved (and replaced
     * in place) on first dispatch.
     */
    private array $middlewares;
    private readonly ?IContainer $container;

    /**
     * Creates a pipeline over an ordered list of middleware.
     *
     * @param iterable $middlewares Middleware instances, or container service ids resolved on first dispatch.
     * @param IRequestHandler $fallback The handler producing the response when no middleware short-circuits.
     * @param ?IContainer $container The container resolving service ids; required when any id is given.
     *
     * @throws InvalidArgumentException if an element is neither a middleware nor a string, if a service id is given
     * without a container, or if the container does not have a given id.
     *
     * @phpstan-param iterable<mixed> $middlewares
     */
    public function __construct(iterable $middlewares, IRequestHandler $fallback, ?IContainer $container = null)
    {
        $list = [];
        $i = 0;
        foreach ($middlewares as $middleware) {
            if (\is_string($middleware)) {
                if ($container === null) {
                    throw new InvalidArgumentException(
                        \sprintf('Middleware %d is the service id "%s" but no container is given.', $i, $middleware)
                    );
                }

                if (!$container->has($middleware)) {
                    throw new InvalidArgumentException(
                        \sprintf('Middleware %d refers to the unknown service "%s".', $i, $middleware)
                    );
                }
            } elseif (!$middleware instanceof IMiddleware) {
                throw new InvalidArgumentException(
                    \sprintf(
                        'Middleware %d must be a middleware instance or a service id, %s given.',
                        $i,
                        \get_debug_type($middleware)
                    )
                );
            }

            $list[] = $middleware;
            $i++;
        }

        $this->middlewares = $list;
        $this->fallback = $fallback;
        $this->container = $container;
    }

    /**
     * Returns the middleware at a position, or `null` past the end of the list.
     *
     * A service id at the position is resolved through the container on the first call and kept, so each id is
     * resolved at most once per pipeline.
     *
     * @param int $index The zero-based position.
     *
     * @return ?IMiddleware The middleware, or `null` when `$index` is past the end.
     *
     * @throws RuntimeException if a service id resolves to anything but a middleware instance.
     *
     * @internal
     *
     * @phpstan-param non-negative-int $index
     */
    public function middlewareAt(int $index): ?IMiddleware
    {
        $middleware = $this->middlewares[$index] ?? null;
        if (!\is_string($middleware)) {
            return $middleware;
        }

        // The constructor rejects service ids without a container, so the nullsafe call cannot yield null here.
        $resolved = $this->container?->get($middleware);
        if (!$resolved instanceof IMiddleware) {
            throw new RuntimeException(
                \sprintf('Service "%s" is not a middleware, %s given.', $middleware, \get_debug_type($resolved))
            );
        }

        $this->middlewares[$index] = $resolved;

        return $resolved;
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
