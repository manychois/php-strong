<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\Http\Internal;

use Manychois\PhpStrong\Http\MiddlewarePipeline;
use Override;
use Psr\Http\Message\ResponseInterface as IResponse;
use Psr\Http\Message\ServerRequestInterface as IServerRequest;
use Psr\Http\Server\RequestHandlerInterface as IRequestHandler;

/**
 * The handler a middleware receives: the rest of its pipeline, starting at a fixed position.
 *
 * @internal
 */
final class PipelineStep implements IRequestHandler
{
    /**
     * @param MiddlewarePipeline $pipeline The pipeline being dispatched.
     * @param int $index The position this step dispatches.
     *
     * @phpstan-param non-negative-int $index
     */
    public function __construct(
        private readonly MiddlewarePipeline $pipeline,
        private readonly int $index,
    ) {
    }

    #region implements IRequestHandler

    /**
     * @inheritDoc
     */
    #[Override]
    public function handle(IServerRequest $request): IResponse
    {
        $middleware = $this->pipeline->middlewareAt($this->index);
        if ($middleware === null) {
            return $this->pipeline->fallback->handle($request);
        }

        return $middleware->process($request, new PipelineStep($this->pipeline, $this->index + 1));
    }

    #endregion implements IRequestHandler
}
