<?php

declare(strict_types=1);

namespace Dirthara\Http\Tests;

use Dirthara\Http\Response;
use Dirthara\Http\StatusCode;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Dirthara\Http\Tests\Doubles\MemoryStream;
use Dirthara\Http\Exception\InvalidMessageException;
use Dirthara\Http\Exception\InvalidResponseException;

final class ResponseTest extends TestCase
{
    #[Test]
    public function it_holds_what_it_was_given(): void
    {
        $body = new MemoryStream('body');

        $response = new Response(201, $body, ['Location' => '/items/1', '123' => 'x'], '2', 'Made It');

        self::assertSame(201, $response->getStatusCode());
        self::assertSame('Made It', $response->getReasonPhrase());
        self::assertSame($body, $response->getBody());
        self::assertSame('2', $response->getProtocolVersion());
        self::assertSame(['Location' => ['/items/1'], 123 => ['x']], $response->getHeaders());
    }

    #[Test]
    public function it_speaks_http_1_1_by_default_and_adds_no_headers(): void
    {
        $response = $this->response();

        self::assertSame('1.1', $response->getProtocolVersion());
        self::assertSame([], $response->getHeaders());
    }

    #[Test]
    public function it_defaults_to_the_registered_reason_phrase(): void
    {
        self::assertSame('OK', $this->response()->getReasonPhrase());
        self::assertSame('Not Found', new Response(404, new MemoryStream())->getReasonPhrase());
    }

    #[Test]
    public function it_has_an_empty_reason_phrase_for_an_unregistered_status_code(): void
    {
        self::assertSame('', new Response(299, new MemoryStream())->getReasonPhrase());
    }

    #[Test]
    public function it_accepts_the_edges_of_the_status_code_range(): void
    {
        self::assertSame(100, new Response(100, new MemoryStream())->getStatusCode());
        self::assertSame(599, new Response(599, new MemoryStream())->getStatusCode());
    }

    #[Test]
    public function it_refuses_a_status_code_below_the_range(): void
    {
        try {
            new Response(99, new MemoryStream());

            self::fail('Expected an InvalidResponseException.');
        } catch (InvalidResponseException $exception) {
            self::assertSame(
                'The HTTP status code "99" is invalid, it must be between 100 and 599.',
                $exception->getMessage(),
            );
            self::assertSame(['statusCode' => 99], $exception->context);
        }
    }

    #[Test]
    public function it_refuses_a_status_code_above_the_range(): void
    {
        $this->expectException(InvalidResponseException::class);

        $this->response()->withStatus(600);
    }

    #[Test]
    public function it_refuses_a_reason_phrase_with_a_line_break(): void
    {
        try {
            new Response(200, new MemoryStream(), reasonPhrase: "OK\r\nX-Injected: yes");

            self::fail('Expected an InvalidResponseException.');
        } catch (InvalidResponseException $exception) {
            self::assertSame(
                'The HTTP reason phrase cannot contain line breaks or control characters.',
                $exception->getMessage(),
            );
            self::assertSame(['reasonPhrase' => "OK\r\nX-Injected: yes"], $exception->context);
        }
    }

    #[Test]
    public function it_accepts_a_reason_phrase_with_spaces_and_tabs(): void
    {
        self::assertSame("All\tGood Here", $this->response()->withStatus(200, "All\tGood Here")->getReasonPhrase());
    }

    #[Test]
    public function it_refuses_an_invalid_protocol_version(): void
    {
        $this->expectException(InvalidMessageException::class);

        new Response(200, new MemoryStream(), protocolVersion: 'HTTP/1.1');
    }

    #[Test]
    public function it_changes_the_status_on_a_copy(): void
    {
        $response = $this->response();
        $changed = $response->withStatus(418, 'I Am A Teapot');

        self::assertSame(418, $changed->getStatusCode());
        self::assertSame('I Am A Teapot', $changed->getReasonPhrase());
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('OK', $response->getReasonPhrase());
    }

    #[Test]
    public function it_resets_the_reason_phrase_to_the_registered_one_when_none_is_given(): void
    {
        $response = $this->response()->withStatus(200, 'Fine')->withStatus(404);

        self::assertSame('Not Found', $response->getReasonPhrase());
    }

    #[Test]
    public function it_returns_itself_when_the_status_does_not_change(): void
    {
        $response = $this->response();

        self::assertSame($response, $response->withStatus(200));
        self::assertSame($response, $response->withStatus(200, 'OK'));
    }

    #[Test]
    public function it_keeps_its_type_through_message_changes(): void
    {
        $response = $this
            ->response()
            ->withHeader('Content-Type', 'text/plain')
            ->withProtocolVersion('1.0')
            ->withStatus(204);

        self::assertInstanceOf(Response::class, $response);
        self::assertSame('text/plain', $response->getHeaderLine('content-type'));
        self::assertSame('1.0', $response->getProtocolVersion());
    }

    #[Test]
    public function it_takes_a_status_code_case(): void
    {
        $response = new Response(StatusCode::Created, new MemoryStream());

        self::assertSame(201, $response->getStatusCode());
        self::assertSame('Created', $response->getReasonPhrase());
    }

    #[Test]
    public function it_changes_to_a_status_code_case_on_a_copy(): void
    {
        $response = $this->response()->withStatus(StatusCode::NotFound);

        self::assertSame(404, $response->getStatusCode());
        self::assertSame('Not Found', $response->getReasonPhrase());
        self::assertSame('Gone Fishing', $response->withStatus(StatusCode::Gone, 'Gone Fishing')->getReasonPhrase());
    }

    #[Test]
    public function it_returns_itself_when_a_status_code_case_does_not_change_the_status(): void
    {
        $response = $this->response();

        self::assertSame($response, $response->withStatus(StatusCode::Ok));
    }

    private function response(): Response
    {
        return new Response(200, new MemoryStream());
    }
}
