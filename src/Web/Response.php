<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\Web;

use InvalidArgumentException;
use Manychois\PhpStrong\Web\Internal\AbstractMessage;
use Override;
use Psr\Http\Message\ResponseInterface as IResponse;
use Psr\Http\Message\StreamInterface as IStream;

/**
 * A lightweight immutable PSR-7 response implementation.
 */
class Response extends AbstractMessage implements IResponse
{
    /**
     * @param array<string,string|string[]> $headers
     */
    public function __construct(
        private int $statusCode = 200,
        string $reasonPhrase = '',
        array $headers = [],
        IStream|string|null $body = null,
        string $protocolVersion = '1.1',
    ) {
        self::assertStatusCode($statusCode);

        parent::__construct(
            headers: $headers,
            body: $body instanceof IStream ? $body : (new StreamFactory())->createStream($body ?? ''),
            protocolVersion: $protocolVersion,
        );

        $this->reasonPhrase = $reasonPhrase !== '' ? $reasonPhrase : self::defaultReasonPhrase($statusCode);
    }

    private string $reasonPhrase;

    #region implements IResponse

    /**
     * @inheritDoc
     */
    #[Override]
    public function getReasonPhrase(): string
    {
        return $this->reasonPhrase;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function withStatus(int $code, string $reasonPhrase = ''): IResponse
    {
        self::assertStatusCode($code);

        $clone = clone $this;
        $clone->statusCode = $code;
        $clone->reasonPhrase = $reasonPhrase !== '' ? $reasonPhrase : self::defaultReasonPhrase($code);
        return $clone;
    }

    #endregion implements IResponse

    private static function assertStatusCode(int $code): void
    {
        if ($code < 100 || $code > 599) {
            throw new InvalidArgumentException(sprintf(
                'Invalid HTTP status code %d; expected a value between 100 and 599.',
                $code,
            ));
        }
    }

    private static function defaultReasonPhrase(int $code): string
    {
        $registered = StatusCode::tryFrom($code);

        return $registered?->reasonPhrase() ?? '';
    }
}
