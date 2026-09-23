<?php

declare(strict_types=1);

namespace Dirthara\Http\Tests\Factory;

use Dirthara\Http\Uri;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Dirthara\Http\Factory\RequestFactory;
use Dirthara\Http\Factory\ServerRequestFactory;
use Dirthara\Http\Exception\InvalidUriException;

final class RequestFactoryTest extends TestCase
{
    #[Test]
    public function it_creates_a_request_from_a_uri_string_with_an_empty_body(): void
    {
        $request = new RequestFactory()->createRequest('POST', 'https://example.com/path?a=1');

        self::assertSame('POST', $request->getMethod());
        self::assertSame('https://example.com/path?a=1', (string) $request->getUri());
        self::assertSame('example.com', $request->getHeaderLine('Host'));
        self::assertSame('', (string) $request->getBody());
        self::assertTrue($request->getBody()->isWritable());
    }

    #[Test]
    public function it_creates_a_request_with_the_uri_it_was_given(): void
    {
        $uri = new Uri('https://example.com/');

        self::assertSame(
            $uri,
            new RequestFactory()
                ->createRequest('GET', $uri)
                ->getUri(),
        );
    }

    #[Test]
    public function it_refuses_a_uri_string_it_cannot_parse(): void
    {
        $this->expectException(InvalidUriException::class);

        new RequestFactory()->createRequest('GET', 'http://host:port');
    }

    #[Test]
    public function it_creates_a_server_request_with_the_server_params_it_was_given(): void
    {
        $request = new ServerRequestFactory()->createServerRequest('GET', 'http://example.com/?a=1', [
            'REQUEST_METHOD' => 'POST',
            'REMOTE_ADDR' => '127.0.0.1',
        ]);

        self::assertSame('GET', $request->getMethod());
        self::assertSame(['REQUEST_METHOD' => 'POST', 'REMOTE_ADDR' => '127.0.0.1'], $request->getServerParams());
        self::assertSame([], $request->getQueryParams());
        self::assertSame('example.com', $request->getHeaderLine('Host'));
        self::assertSame('', (string) $request->getBody());
    }

    #[Test]
    public function it_creates_a_server_request_with_the_uri_it_was_given(): void
    {
        $uri = new Uri('https://example.com/');

        self::assertSame(
            $uri,
            new ServerRequestFactory()
                ->createServerRequest('GET', $uri)
                ->getUri(),
        );
    }
}
