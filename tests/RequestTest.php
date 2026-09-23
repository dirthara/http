<?php

declare(strict_types=1);

namespace Dirthara\Http\Tests;

use Dirthara\Http\Uri;
use Dirthara\Http\Request;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Dirthara\Http\Tests\Doubles\ForeignUri;
use Dirthara\Http\Tests\Doubles\MemoryStream;
use Dirthara\Http\Exception\InvalidMessageException;
use Dirthara\Http\Exception\InvalidRequestException;

final class RequestTest extends TestCase
{
    #[Test]
    public function it_holds_what_it_was_given(): void
    {
        $uri = new Uri('https://example.com/path');
        $body = new MemoryStream('body');

        $request = new Request('POST', $uri, $body, ['X-Foo' => 'bar'], '2');

        self::assertSame('POST', $request->getMethod());
        self::assertSame($uri, $request->getUri());
        self::assertSame($body, $request->getBody());
        self::assertSame('2', $request->getProtocolVersion());
        self::assertSame(['Host' => ['example.com'], 'X-Foo' => ['bar']], $request->getHeaders());
    }

    #[Test]
    public function it_speaks_http_1_1_by_default(): void
    {
        self::assertSame('1.1', $this->request()->getProtocolVersion());
    }

    #[Test]
    public function it_keeps_the_case_of_the_method(): void
    {
        self::assertSame('patch', $this->request('patch')->getMethod());
    }

    #[Test]
    public function it_puts_a_host_from_the_uri_first_and_adds_a_non_standard_port(): void
    {
        $request = new Request('GET', new Uri('http://example.com:8080/'), new MemoryStream(), [
            'Accept' => 'text/html',
        ]);

        self::assertSame(['Host' => ['example.com:8080'], 'Accept' => ['text/html']], $request->getHeaders());
    }

    #[Test]
    public function it_keeps_a_host_header_it_was_given(): void
    {
        $request = new Request('GET', new Uri('http://example.com/'), new MemoryStream(), ['host' => 'other.test']);

        self::assertSame(['host' => ['other.test']], $request->getHeaders());
    }

    #[Test]
    public function it_fills_in_an_empty_host_header_from_the_uri_under_its_given_name(): void
    {
        $request = new Request('GET', new Uri('http://example.com/'), new MemoryStream(), [
            'Accept' => 'text/html',
            'host' => '',
        ]);

        self::assertSame(['host' => ['example.com'], 'Accept' => ['text/html']], $request->getHeaders());
    }

    #[Test]
    public function it_adds_no_host_header_for_a_uri_without_a_host(): void
    {
        self::assertFalse($this->request()->hasHeader('Host'));
    }

    #[Test]
    public function it_accepts_a_numeric_header_name(): void
    {
        $request = new Request('GET', new Uri(), new MemoryStream(), ['123' => 'value']);

        self::assertSame('value', $request->getHeaderLine('123'));
        self::assertSame([123 => ['value']], $request->getHeaders());
    }

    #[Test]
    public function it_refuses_an_invalid_method(): void
    {
        try {
            $this->request("GET\r\n");

            self::fail('Expected an InvalidRequestException.');
        } catch (InvalidRequestException $exception) {
            self::assertSame('The HTTP request method "GET\r\n" is invalid.', $exception->getMessage());
            self::assertSame(['method' => "GET\r\n"], $exception->context);
        }
    }

    #[Test]
    public function it_refuses_an_empty_method(): void
    {
        $this->expectException(InvalidRequestException::class);

        $this->request('');
    }

    #[Test]
    public function it_refuses_an_invalid_protocol_version(): void
    {
        try {
            new Request('GET', new Uri(), new MemoryStream(), [], "1.1\n");

            self::fail('Expected an InvalidMessageException.');
        } catch (InvalidMessageException $exception) {
            self::assertSame('The HTTP protocol version "1.1\n" is invalid.', $exception->getMessage());
            self::assertSame(['version' => "1.1\n"], $exception->context);
        }
    }

    #[Test]
    public function it_changes_the_protocol_version_on_a_copy(): void
    {
        $request = $this->request();
        $changed = $request->withProtocolVersion('1.0');

        self::assertSame('1.0', $changed->getProtocolVersion());
        self::assertSame('1.1', $request->getProtocolVersion());
        self::assertSame($request, $request->withProtocolVersion('1.1'));
    }

    #[Test]
    public function it_refuses_an_invalid_protocol_version_on_change(): void
    {
        $this->expectException(InvalidMessageException::class);

        $this->request()->withProtocolVersion('HTTP/1.1');
    }

    #[Test]
    public function it_looks_up_headers_without_regard_to_case(): void
    {
        $request = $this->request()->withHeader('X-Foo', ['a', 'b']);

        self::assertTrue($request->hasHeader('x-foo'));
        self::assertSame(['a', 'b'], $request->getHeader('X-FOO'));
        self::assertSame('a, b', $request->getHeaderLine('x-Foo'));
    }

    #[Test]
    public function it_reports_a_missing_header_as_empty(): void
    {
        $request = $this->request();

        self::assertFalse($request->hasHeader('X-Foo'));
        self::assertSame([], $request->getHeader('X-Foo'));
        self::assertSame('', $request->getHeaderLine('X-Foo'));
    }

    #[Test]
    public function it_replaces_a_header_under_its_new_name_on_a_copy(): void
    {
        $request = $this->request()->withHeader('X-Foo', 'a');
        $changed = $request->withHeader('x-foo', 'b');

        self::assertSame(['x-foo' => ['b']], $changed->getHeaders());
        self::assertSame(['X-Foo' => ['a']], $request->getHeaders());
    }

    #[Test]
    public function it_keeps_the_keys_out_of_a_header_value_array(): void
    {
        $request = $this->request()->withHeader('X-Foo', ['first' => 'a', 'second' => 'b']);

        self::assertSame(['a', 'b'], $request->getHeader('X-Foo'));
    }

    #[Test]
    public function it_trims_surrounding_whitespace_from_header_values_but_keeps_inner_tabs(): void
    {
        $request = $this->request()->withHeader('X-Foo', [" \ta\tb \t", ' c ']);

        self::assertSame(["a\tb", 'c'], $request->getHeader('X-Foo'));
    }

    #[Test]
    public function it_appends_to_a_header_under_its_existing_name_on_a_copy(): void
    {
        $request = $this->request()->withHeader('X-Foo', 'a');
        $changed = $request->withAddedHeader('x-foo', ['b', 'c']);

        self::assertSame(['X-Foo' => ['a', 'b', 'c']], $changed->getHeaders());
        self::assertSame(['X-Foo' => ['a']], $request->getHeaders());
    }

    #[Test]
    public function it_adds_a_header_that_is_not_there_yet(): void
    {
        self::assertSame(['X-Foo' => ['a']], $this->request()->withAddedHeader('X-Foo', 'a')->getHeaders());
    }

    #[Test]
    public function it_removes_a_header_without_regard_to_case_on_a_copy(): void
    {
        $request = $this->request()->withHeader('X-Foo', 'a')->withHeader('X-Bar', 'b');
        $changed = $request->withoutHeader('x-foo');

        self::assertSame(['X-Bar' => ['b']], $changed->getHeaders());
        self::assertFalse($changed->hasHeader('X-Foo'));
        self::assertTrue($request->hasHeader('X-Foo'));
    }

    #[Test]
    public function it_returns_itself_when_removing_a_header_that_is_not_there(): void
    {
        $request = $this->request();

        self::assertSame($request, $request->withoutHeader('X-Foo'));
    }

    #[Test]
    public function it_refuses_an_invalid_header_name(): void
    {
        try {
            $this->request()->withHeader('X Foo', 'a');

            self::fail('Expected an InvalidMessageException.');
        } catch (InvalidMessageException $exception) {
            self::assertSame('The HTTP header name "X Foo" is invalid.', $exception->getMessage());
            self::assertSame(['name' => 'X Foo'], $exception->context);
        }
    }

    #[Test]
    public function it_refuses_an_empty_header_name(): void
    {
        $this->expectException(InvalidMessageException::class);

        $this->request()->withAddedHeader('', 'a');
    }

    #[Test]
    public function it_refuses_a_header_value_that_is_neither_a_string_nor_an_array(): void
    {
        try {
            // @mago-expect analysis:invalid-argument
            $this->request()->withHeader('X-Foo', 1);

            self::fail('Expected an InvalidMessageException.');
        } catch (InvalidMessageException $exception) {
            self::assertSame(
                'The HTTP header "X-Foo" must contain only string values, "int" given.',
                $exception->getMessage(),
            );
            self::assertSame(['name' => 'X-Foo', 'type' => 'int'], $exception->context);
        }
    }

    #[Test]
    public function it_refuses_a_header_value_array_holding_something_other_than_strings(): void
    {
        $this->expectException(InvalidMessageException::class);
        $this->expectExceptionMessage('The HTTP header "X-Foo" must contain only string values, "null" given.');

        // @mago-expect analysis:possibly-invalid-argument
        $this->request()->withHeader('X-Foo', ['a', null]);
    }

    #[Test]
    public function it_refuses_an_empty_header_value_array(): void
    {
        try {
            $this->request()->withAddedHeader('X-Foo', []);

            self::fail('Expected an InvalidMessageException.');
        } catch (InvalidMessageException $exception) {
            self::assertSame('The HTTP header "X-Foo" must have at least one value.', $exception->getMessage());
            self::assertSame(['name' => 'X-Foo'], $exception->context);
        }
    }

    #[Test]
    public function it_refuses_a_header_value_with_a_line_break(): void
    {
        try {
            $this->request()->withHeader('X-Foo', "a\r\nX-Injected: b");

            self::fail('Expected an InvalidMessageException.');
        } catch (InvalidMessageException $exception) {
            self::assertSame('The HTTP header "X-Foo" contains an invalid value.', $exception->getMessage());
            self::assertSame(['name' => 'X-Foo'], $exception->context);
        }
    }

    #[Test]
    public function it_changes_the_body_on_a_copy(): void
    {
        $request = $this->request();
        $body = new MemoryStream('other');
        $changed = $request->withBody($body);

        self::assertSame($body, $changed->getBody());
        self::assertNotSame($body, $request->getBody());
        self::assertSame($changed, $changed->withBody($body));
    }

    #[Test]
    public function it_derives_the_request_target_from_the_path_and_query(): void
    {
        $request = new Request('GET', new Uri('http://example.com/path?a=1#top'), new MemoryStream());

        self::assertSame('/path?a=1', $request->getRequestTarget());
    }

    #[Test]
    public function it_derives_a_slash_as_the_request_target_for_an_empty_path(): void
    {
        self::assertSame('/', $this->request()->getRequestTarget());
    }

    #[Test]
    public function it_changes_the_request_target_on_a_copy(): void
    {
        $request = $this->request();
        $changed = $request->withRequestTarget('*');

        self::assertSame('*', $changed->getRequestTarget());
        self::assertSame('/', $request->getRequestTarget());
        self::assertSame($changed, $changed->withRequestTarget('*'));
    }

    #[Test]
    public function it_keeps_an_explicit_request_target_when_the_uri_changes(): void
    {
        $request = $this->request()->withRequestTarget('example.com:443')->withUri(new Uri('https://other.test/path'));

        self::assertSame('example.com:443', $request->getRequestTarget());
    }

    #[Test]
    public function it_refuses_a_request_target_with_whitespace(): void
    {
        try {
            $this->request()->withRequestTarget('/a b');

            self::fail('Expected an InvalidRequestException.');
        } catch (InvalidRequestException $exception) {
            self::assertSame(
                'The HTTP request target cannot be empty or contain whitespace or control characters.',
                $exception->getMessage(),
            );
            self::assertSame([], $exception->context);
        }
    }

    #[Test]
    public function it_refuses_an_empty_request_target(): void
    {
        $this->expectException(InvalidRequestException::class);

        $this->request()->withRequestTarget('');
    }

    #[Test]
    public function it_changes_the_method_on_a_copy(): void
    {
        $request = $this->request();
        $changed = $request->withMethod('DELETE');

        self::assertSame('DELETE', $changed->getMethod());
        self::assertSame('GET', $request->getMethod());
        self::assertSame($changed, $changed->withMethod('DELETE'));
    }

    #[Test]
    public function it_refuses_an_invalid_method_on_change(): void
    {
        $this->expectException(InvalidRequestException::class);

        $this->request()->withMethod('GET POST');
    }

    #[Test]
    public function it_changes_the_uri_and_moves_the_new_host_first_on_a_copy(): void
    {
        $request = new Request('GET', new Uri('http://example.com/'), new MemoryStream(), ['Accept' => 'text/html']);
        $uri = new Uri('http://other.test:8080/');
        $changed = $request->withHeader('host', 'stale.test')->withUri($uri);

        self::assertSame($uri, $changed->getUri());
        self::assertSame(['host' => ['other.test:8080'], 'Accept' => ['text/html']], $changed->getHeaders());
        self::assertSame('example.com', $request->getHeaderLine('Host'));
    }

    #[Test]
    public function it_keeps_the_host_header_for_a_uri_without_a_host(): void
    {
        $request = new Request('GET', new Uri('http://example.com/'), new MemoryStream());

        self::assertSame('example.com', $request->withUri(new Uri('/path'))->getHeaderLine('Host'));
    }

    #[Test]
    public function it_preserves_a_host_header_when_asked(): void
    {
        $request = new Request('GET', new Uri('http://example.com/'), new MemoryStream());

        self::assertSame(
            'example.com',
            $request->withUri(new Uri('http://other.test/'), preserveHost: true)->getHeaderLine('Host'),
        );
    }

    #[Test]
    public function it_fills_in_a_missing_host_header_even_when_asked_to_preserve_it(): void
    {
        self::assertSame(
            'other.test',
            $this->request()->withUri(new Uri('http://other.test/'), preserveHost: true)->getHeaderLine('Host'),
        );
    }

    #[Test]
    public function it_gives_a_rootless_path_a_leading_slash_in_the_request_target(): void
    {
        $request = new Request('GET', new Uri('http://example.com')->withPath('users'), new MemoryStream());

        self::assertSame('/users', $request->getRequestTarget());
    }

    #[Test]
    public function it_collapses_leading_slashes_in_the_request_target_so_they_cannot_read_as_an_authority(): void
    {
        $request = new Request('GET', new Uri('http://example.com//evil.example///path'), new MemoryStream());

        self::assertSame('/evil.example///path', $request->getRequestTarget());
    }

    #[Test]
    public function it_refuses_a_foreign_uri_whose_request_target_would_split_the_request_line(): void
    {
        try {
            new Request('GET', new ForeignUri("/a\r\nX-Injected: yes"), new MemoryStream());

            self::fail('Expected an InvalidRequestException.');
        } catch (InvalidRequestException $exception) {
            self::assertSame(
                'The HTTP request target cannot be empty or contain whitespace or control characters.',
                $exception->getMessage(),
            );
        }
    }

    #[Test]
    public function it_refuses_to_change_to_a_foreign_uri_with_an_unsafe_query(): void
    {
        $this->expectException(InvalidRequestException::class);

        $this->request()->withUri(new ForeignUri('/search', 'q=a b'));
    }

    #[Test]
    public function it_accepts_a_foreign_uri_with_a_safe_request_target(): void
    {
        $request = new Request('GET', new ForeignUri('/search', 'q=a', 'example.com'), new MemoryStream());

        self::assertSame('/search?q=a', $request->getRequestTarget());
        self::assertSame('example.com', $request->getHeaderLine('Host'));
    }

    #[Test]
    public function it_refuses_headers_given_as_a_list_of_lines(): void
    {
        try {
            new Request('GET', new Uri(), new MemoryStream(), ['Accept: text/html']);

            self::fail('Expected an InvalidMessageException.');
        } catch (InvalidMessageException $exception) {
            self::assertSame('HTTP headers must be keyed by name, a list was given.', $exception->getMessage());
            self::assertSame([], $exception->context);
        }
    }

    private function request(string $method = 'GET'): Request
    {
        return new Request($method, new Uri(), new MemoryStream());
    }
}
