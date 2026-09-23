<?php

declare(strict_types=1);

namespace Dirthara\Http\Tests\Factory;

use Dirthara\Http\StatusCode;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Dirthara\Http\Factory\ResponseFactory;

final class ResponseFactoryTest extends TestCase
{
    #[Test]
    public function it_creates_a_200_ok_response_with_an_empty_body_by_default(): void
    {
        $response = new ResponseFactory()->createResponse();

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('OK', $response->getReasonPhrase());
        self::assertSame('', (string) $response->getBody());
        self::assertTrue($response->getBody()->isWritable());
    }

    #[Test]
    public function it_creates_a_response_with_the_given_status_and_reason_phrase(): void
    {
        $response = new ResponseFactory()->createResponse(404, 'Nothing Here');

        self::assertSame(404, $response->getStatusCode());
        self::assertSame('Nothing Here', $response->getReasonPhrase());
    }

    #[Test]
    public function it_creates_a_response_from_a_status_code_case(): void
    {
        $response = new ResponseFactory()->createResponse(StatusCode::Created);

        self::assertSame(201, $response->getStatusCode());
        self::assertSame('Created', $response->getReasonPhrase());
    }
}
