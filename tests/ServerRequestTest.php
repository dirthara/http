<?php

declare(strict_types=1);

namespace Dirthara\Http\Tests;

use stdClass;
use Dirthara\Http\Uri;
use Dirthara\Http\UploadedFile;
use PHPUnit\Framework\TestCase;
use Dirthara\Http\ServerRequest;
use PHPUnit\Framework\Attributes\Test;
use Dirthara\Http\Tests\Doubles\MemoryStream;
use Dirthara\Http\Exception\InvalidRequestException;

final class ServerRequestTest extends TestCase
{
    #[Test]
    public function it_is_a_request_with_server_params(): void
    {
        $uri = new Uri('https://example.com/path?a=1');
        $body = new MemoryStream('body');

        $request = new ServerRequest('POST', $uri, $body, ['Accept' => 'text/html'], '2', [
            'REMOTE_ADDR' => '127.0.0.1',
        ]);

        self::assertSame('POST', $request->getMethod());
        self::assertSame($uri, $request->getUri());
        self::assertSame($body, $request->getBody());
        self::assertSame('2', $request->getProtocolVersion());
        self::assertSame(['Host' => ['example.com'], 'Accept' => ['text/html']], $request->getHeaders());
        self::assertSame('/path?a=1', $request->getRequestTarget());
        self::assertSame(['REMOTE_ADDR' => '127.0.0.1'], $request->getServerParams());
    }

    #[Test]
    public function it_starts_without_cookies_query_files_body_or_attributes(): void
    {
        $request = $this->request();

        self::assertSame([], $request->getServerParams());
        self::assertSame([], $request->getCookieParams());
        self::assertSame([], $request->getQueryParams());
        self::assertSame([], $request->getUploadedFiles());
        self::assertNull($request->getParsedBody());
        self::assertSame([], $request->getAttributes());
    }

    #[Test]
    public function it_keeps_its_type_through_inherited_changes(): void
    {
        $request = $this
            ->request()
            ->withHeader('X-Foo', 'a')
            ->withMethod('PUT')
            ->withUri(new Uri('http://other.test/'))
            ->withAttribute('id', 1);

        self::assertInstanceOf(ServerRequest::class, $request);
        self::assertSame('other.test', $request->getHeaderLine('Host'));
        self::assertSame(1, $request->getAttribute('id'));
    }

    #[Test]
    public function it_changes_the_cookie_params_on_a_copy(): void
    {
        $request = $this->request();
        $changed = $request->withCookieParams(['session' => 'abc']);

        self::assertSame(['session' => 'abc'], $changed->getCookieParams());
        self::assertSame([], $request->getCookieParams());
    }

    #[Test]
    public function it_changes_the_query_params_on_a_copy_without_touching_the_uri(): void
    {
        $request = new ServerRequest('GET', new Uri('http://example.com/?a=1'), new MemoryStream());
        $changed = $request->withQueryParams(['b' => '2']);

        self::assertSame(['b' => '2'], $changed->getQueryParams());
        self::assertSame('a=1', $changed->getUri()->getQuery());
        self::assertSame([], $request->getQueryParams());
    }

    #[Test]
    public function it_changes_the_uploaded_files_on_a_copy(): void
    {
        $avatar = new UploadedFile(new MemoryStream('avatar'));
        $photo = new UploadedFile(new MemoryStream('photo'));
        $files = ['avatar' => $avatar, 'photos' => [[$photo]]];

        $request = $this->request();
        $changed = $request->withUploadedFiles($files);

        self::assertSame($files, $changed->getUploadedFiles());
        self::assertSame([], $request->getUploadedFiles());
    }

    #[Test]
    public function it_refuses_an_uploaded_file_tree_holding_something_else(): void
    {
        try {
            $this->request()->withUploadedFiles(['photos' => [new UploadedFile(new MemoryStream()), 'photo.jpg']]);

            self::fail('Expected an InvalidRequestException.');
        } catch (InvalidRequestException $exception) {
            self::assertSame(
                'The uploaded file "photos[1]" must be an UploadedFileInterface or an array of them, "string" given.',
                $exception->getMessage(),
            );
            self::assertSame(['path' => 'photos[1]', 'type' => 'string'], $exception->context);
        }
    }

    #[Test]
    public function it_names_a_top_level_uploaded_file_by_its_key(): void
    {
        $this->expectException(InvalidRequestException::class);
        $this->expectExceptionMessage('The uploaded file "0" must be');

        $this->request()->withUploadedFiles([null]);
    }

    #[Test]
    public function it_changes_the_parsed_body_on_a_copy(): void
    {
        $object = new stdClass();
        $request = $this->request();

        self::assertSame(['name' => 'value'], $request->withParsedBody(['name' => 'value'])->getParsedBody());
        self::assertSame($object, $request->withParsedBody($object)->getParsedBody());
        self::assertNull($request->withParsedBody(['a' => 'b'])->withParsedBody(null)->getParsedBody());
        self::assertNull($request->getParsedBody());
    }

    #[Test]
    public function it_refuses_a_parsed_body_that_is_not_null_an_array_or_an_object(): void
    {
        try {
            // @mago-expect analysis:invalid-argument
            $this->request()->withParsedBody('password=secret');

            self::fail('Expected an InvalidRequestException.');
        } catch (InvalidRequestException $exception) {
            self::assertSame(
                'The parsed body must be null, an array, or an object, "string" given.',
                $exception->getMessage(),
            );
            self::assertSame(['type' => 'string'], $exception->context);
        }
    }

    #[Test]
    public function it_adds_and_replaces_attributes_on_a_copy(): void
    {
        $request = $this->request();
        $changed = $request->withAttribute('id', 1)->withAttribute('role', 'admin')->withAttribute('id', 2);

        self::assertSame(['id' => 2, 'role' => 'admin'], $changed->getAttributes());
        self::assertSame([], $request->getAttributes());
    }

    #[Test]
    public function it_returns_the_default_only_for_a_missing_attribute(): void
    {
        $request = $this->request()->withAttribute('nothing', null);

        self::assertNull($request->getAttribute('nothing', 'default'));
        self::assertSame('default', $request->getAttribute('missing', 'default'));
        self::assertNull($request->getAttribute('missing'));
    }

    #[Test]
    public function it_removes_an_attribute_on_a_copy(): void
    {
        $request = $this->request()->withAttribute('id', 1)->withAttribute('role', 'admin');
        $changed = $request->withoutAttribute('id');

        self::assertSame(['role' => 'admin'], $changed->getAttributes());
        self::assertSame(1, $request->getAttribute('id'));
    }

    #[Test]
    public function it_returns_itself_when_removing_an_attribute_that_is_not_there(): void
    {
        $request = $this->request();

        self::assertSame($request, $request->withoutAttribute('id'));
    }

    private function request(): ServerRequest
    {
        return new ServerRequest('GET', new Uri(), new MemoryStream());
    }
}
