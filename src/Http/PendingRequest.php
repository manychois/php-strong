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
