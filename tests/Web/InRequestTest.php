<?php

declare(strict_types=1);

namespace Manychois\PhpStrongTests\Web;

use InvalidArgumentException;
use Manychois\PhpStrong\Web\InRequest;
use Manychois\PhpStrong\Web\StreamFactory;
use Manychois\PhpStrong\Web\UploadedFile;
use Manychois\PhpStrong\Web\Uri;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for {@see InRequest}.
 */
final class InRequestTest extends TestCase
{
    #[Test]
    public function constructor_exposes_server_cookie_query_uploaded_and_attributes(): void
    {
        $streams = new StreamFactory();
        $file = new UploadedFile(
            $streams->createStream('x'),
            size: 1,
            error: \UPLOAD_ERR_OK,
            clientFilename: 'f.txt',
            clientMediaType: 'text/plain',
        );

        $request = new InRequest(
            method: 'PATCH',
            uri: 'https://app.test/update',
            headers: ['X-Trace' => '1'],
            body: '',
            protocolVersion: '1.1',
            requestTarget: null,
            serverParams: ['REMOTE_ADDR' => '127.0.0.1'],
            cookieParams: ['c' => 'v'],
            queryParams: ['q' => 'search'],
            uploadedFiles: ['doc' => $file],
            parsedBody: ['json' => true],
            attributes: ['route' => 'api.update'],
        );

        self::assertSame(['REMOTE_ADDR' => '127.0.0.1'], $request->getServerParams());
        self::assertSame(['c' => 'v'], $request->getCookieParams());
        self::assertSame(['q' => 'search'], $request->getQueryParams());
        self::assertSame(['json' => true], $request->getParsedBody());
        self::assertSame(['route' => 'api.update'], $request->getAttributes());
        $uploaded = $request->getUploadedFiles();
        self::assertArrayHasKey('doc', $uploaded);
        self::assertSame($file, $uploaded['doc']);
    }

    #[Test]
    public function constructor_normalizes_nested_uploaded_files(): void
    {
        $streams = new StreamFactory();
        $inner = new UploadedFile(
            $streams->createStream(''),
            size: 0,
            error: \UPLOAD_ERR_OK,
            clientFilename: null,
            clientMediaType: null,
        );

        $request = new InRequest(uploadedFiles: ['outer' => ['inner' => $inner]]);
        $files = $request->getUploadedFiles();

        self::assertSame($inner, $files['outer']['inner']);
    }

    #[Test]
    public function constructor_rejects_invalid_uploaded_file_entry(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Uploaded files must contain');

        new InRequest(uploadedFiles: ['bad' => 'not-a-file']);
    }

    #[Test]
    public function getAttribute_returns_default_when_missing(): void
    {
        $request = new InRequest();

        self::assertNull($request->getAttribute('missing'));
        self::assertSame('fallback', $request->getAttribute('missing', 'fallback'));
    }

    #[Test]
    public function withAttribute_is_immutable(): void
    {
        $original = new InRequest(attributes: ['a' => 1]);
        $next = $original->withAttribute('b', 2);

        self::assertSame(['a' => 1], $original->getAttributes());
        self::assertSame(1, $original->getAttribute('a'));
        self::assertNull($original->getAttribute('b'));

        self::assertSame(['a' => 1, 'b' => 2], $next->getAttributes());
        self::assertSame(2, $next->getAttribute('b'));
    }

    #[Test]
    public function withoutAttribute_returns_same_instance_when_missing(): void
    {
        $request = new InRequest();
        $same = $request->withoutAttribute('nope');

        self::assertSame($request, $same);
    }

    #[Test]
    public function withoutAttribute_removes_attribute_on_clone(): void
    {
        $original = new InRequest(attributes: ['x' => 'y']);
        $next = $original->withoutAttribute('x');

        self::assertArrayHasKey('x', $original->getAttributes());
        self::assertArrayNotHasKey('x', $next->getAttributes());
    }

    #[Test]
    public function withCookieParams_replaces_cookies(): void
    {
        $original = new InRequest(cookieParams: ['a' => '1']);
        $next = $original->withCookieParams(['b' => '2']);

        self::assertSame(['a' => '1'], $original->getCookieParams());
        self::assertSame(['b' => '2'], $next->getCookieParams());
    }

    #[Test]
    public function withQueryParams_replaces_query(): void
    {
        $original = new InRequest(queryParams: ['old' => '']);
        $next = $original->withQueryParams(['new' => 'x']);

        self::assertSame(['old' => ''], $original->getQueryParams());
        self::assertSame(['new' => 'x'], $next->getQueryParams());
    }

    #[Test]
    public function withParsedBody_accepts_null_array_and_object(): void
    {
        $base = new InRequest(parsedBody: ['a' => 1]);
        $asNull = $base->withParsedBody(null);
        $asArray = $base->withParsedBody(['z' => 9]);
        $obj = new \stdClass();
        $obj->k = 'v';
        $asObject = $base->withParsedBody($obj);

        self::assertNull($asNull->getParsedBody());
        self::assertSame(['z' => 9], $asArray->getParsedBody());
        self::assertSame($obj, $asObject->getParsedBody());
    }

    #[Test]
    public function withParsedBody_rejects_scalar(): void
    {
        $request = new InRequest();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Parsed body must be null, array, or object');

        $request->withParsedBody(3);
    }

    #[Test]
    public function withUploadedFiles_normalizes_tree(): void
    {
        $streams = new StreamFactory();
        $file = new UploadedFile(
            $streams->createStream(''),
            size: 0,
            error: \UPLOAD_ERR_OK,
            clientFilename: null,
            clientMediaType: null,
        );
        $original = new InRequest();
        $next = $original->withUploadedFiles(['k' => ['nested' => $file]]);

        self::assertSame([], $original->getUploadedFiles());
        self::assertSame($file, $next->getUploadedFiles()['k']['nested']);
    }

    #[Test]
    public function inherits_out_request_with_uri_behavior(): void
    {
        $original = new InRequest(
            uri: 'https://one.example/',
            headers: ['Host' => 'one.example'],
        );
        $two = Uri::fromString('https://two.example/path');
        $next = $original->withUri($two);

        self::assertSame('https://two.example/path', (string) $next->getUri());
        self::assertSame(['two.example'], $next->getHeader('Host'));
    }
}
