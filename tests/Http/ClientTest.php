<?php

declare(strict_types=1);

namespace Manychois\PhpStrongTests\Http;

use Manychois\PhpStrong\Http\Client;
use Manychois\PhpStrong\Http\Internal\RawResponse;
use Manychois\PhpStrong\Http\Internal\TransportInterface as ITransport;
use Manychois\PhpStrong\Http\Request;
use Manychois\PhpStrong\Http\RequestException;
use Manychois\PhpStrong\Http\RequestOptions;
use Manychois\PhpStrong\Http\Uri;
use Override;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface as IRequest;

/**
 * Unit tests for {@see Client}.
 */
final class ClientTest extends TestCase
{
    #[Test]
    public function sendRequest_builds_a_response_from_the_transport_result(): void
    {
        $raw = new RawResponse('1.1', 201, 'Created', ['X-Trace' => ['9']], 'stored');
        $transport = $this->fakeTransport($raw);
        $requestOptions = new RequestOptions(timeout: 5.0);
        $client = new Client($requestOptions, $transport);
        $request = new Request('POST', 'http://example.com/things');

        $response = $client->sendRequest($request);

        self::assertSame(201, $response->getStatusCode());
        self::assertSame('Created', $response->getReasonPhrase());
        self::assertSame(['9'], $response->getHeader('X-Trace'));
        self::assertSame('stored', (string) $response->getBody());
        self::assertSame('1.1', $response->getProtocolVersion());
        self::assertSame($request, $transport->lastRequest);
        self::assertSame($requestOptions, $transport->lastOptions);
    }

    #[Test]
    public function constructor_defaults_to_default_request_options(): void
    {
        $transport = $this->fakeTransport();
        $client = new Client(transport: $transport);

        $client->sendRequest(new Request('GET', 'http://example.com/'));

        self::assertNotNull($transport->lastOptions);
        self::assertSame(30.0, $transport->lastOptions->timeout);
    }

    #[Test]
    public function sendRequest_throws_RequestException_for_unsupported_scheme(): void
    {
        $client = new Client(transport: $this->fakeTransport());
        $request = new Request('GET', 'ftp://example.com/file');

        try {
            $client->sendRequest($request);
            self::fail('Expected RequestException.');
        } catch (RequestException $ex) {
            self::assertStringContainsString('ftp', $ex->getMessage());
            self::assertSame($request, $ex->getRequest());
        }
    }

    #[Test]
    public function sendRequest_throws_RequestException_when_method_is_empty(): void
    {
        $client = new Client(transport: $this->fakeTransport());
        $request = new Request('', 'http://example.com/');

        try {
            $client->sendRequest($request);
            self::fail('Expected RequestException.');
        } catch (RequestException $ex) {
            self::assertStringContainsString('method', $ex->getMessage());
            self::assertSame($request, $ex->getRequest());
        }
    }

    #[Test]
    public function sendRequest_throws_RequestException_when_uri_has_no_host(): void
    {
        $client = new Client(transport: $this->fakeTransport());
        $uri = Uri::fromString('/relative/path')->withScheme('http');
        $request = new Request('GET', $uri);

        try {
            $client->sendRequest($request);
            self::fail('Expected RequestException.');
        } catch (RequestException $ex) {
            self::assertStringContainsString('host', $ex->getMessage());
            self::assertSame($request, $ex->getRequest());
        }
    }

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

    /**
     * Creates a transport stub that records its arguments.
     *
     * @return ITransport&object{lastRequest: ?IRequest, lastOptions: ?RequestOptions}
     */
    private function fakeTransport(?RawResponse $raw = null): ITransport
    {
        $raw ??= new RawResponse('1.1', 200, 'OK', [], '');

        return new class ($raw) implements ITransport {
            public ?IRequest $lastRequest = null;
            public ?RequestOptions $lastOptions = null;

            public function __construct(private readonly RawResponse $raw)
            {
            }

            #[Override]
            public function send(IRequest $request, RequestOptions $options): RawResponse
            {
                $this->lastRequest = $request;
                $this->lastOptions = $options;

                return $this->raw;
            }
        };
    }
}
