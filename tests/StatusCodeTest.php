<?php

declare(strict_types=1);

namespace Dirthara\Http\Tests;

use Dirthara\Http\StatusCode;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class StatusCodeTest extends TestCase
{
    #[Test]
    public function it_is_backed_by_the_numeric_code(): void
    {
        self::assertSame(200, StatusCode::Ok->value);
        self::assertSame(404, StatusCode::NotFound->value);
        self::assertSame(StatusCode::UnprocessableContent, StatusCode::from(422));
        self::assertNull(StatusCode::tryFrom(299));
    }

    #[Test]
    public function it_gives_the_registered_reason_phrase(): void
    {
        self::assertSame('OK', StatusCode::Ok->reasonPhrase());
        self::assertSame('Non-Authoritative Information', StatusCode::NonAuthoritativeInformation->reasonPhrase());
        self::assertSame('IM Used', StatusCode::ImUsed->reasonPhrase());
        self::assertSame('URI Too Long', StatusCode::UriTooLong->reasonPhrase());
        self::assertSame('HTTP Version Not Supported', StatusCode::HttpVersionNotSupported->reasonPhrase());
    }

    #[Test]
    public function it_has_a_reason_phrase_matching_every_case_name(): void
    {
        foreach (StatusCode::cases() as $statusCode) {
            self::assertSame(
                strtolower($statusCode->name),
                strtolower(str_replace([' ', '-'], replace: '', subject: $statusCode->reasonPhrase())),
            );
        }
    }

    #[Test]
    public function it_covers_only_three_digit_codes_from_100_to_599(): void
    {
        foreach (StatusCode::cases() as $statusCode) {
            self::assertGreaterThanOrEqual(100, $statusCode->value);
            self::assertLessThanOrEqual(599, $statusCode->value);
        }
    }

    #[Test]
    public function it_sorts_the_edges_of_each_range_into_their_class(): void
    {
        self::assertTrue(StatusCode::Continue->isInformational());
        self::assertTrue(StatusCode::EarlyHints->isInformational());
        self::assertTrue(StatusCode::Ok->isSuccessful());
        self::assertTrue(StatusCode::ImUsed->isSuccessful());
        self::assertTrue(StatusCode::MultipleChoices->isRedirection());
        self::assertTrue(StatusCode::PermanentRedirect->isRedirection());
        self::assertTrue(StatusCode::BadRequest->isClientError());
        self::assertTrue(StatusCode::UnavailableForLegalReasons->isClientError());
        self::assertTrue(StatusCode::InternalServerError->isServerError());
        self::assertTrue(StatusCode::NetworkAuthenticationRequired->isServerError());
    }

    #[Test]
    public function it_puts_every_case_in_exactly_one_class(): void
    {
        foreach (StatusCode::cases() as $statusCode) {
            $classes = array_filter([
                $statusCode->isInformational(),
                $statusCode->isSuccessful(),
                $statusCode->isRedirection(),
                $statusCode->isClientError(),
                $statusCode->isServerError(),
            ]);

            self::assertCount(1, $classes, $statusCode->name);
        }
    }

    #[Test]
    public function it_counts_client_and_server_errors_as_errors(): void
    {
        foreach (StatusCode::cases() as $statusCode) {
            self::assertSame(
                $statusCode->isClientError() || $statusCode->isServerError(),
                $statusCode->isError(),
                $statusCode->name,
            );
        }
    }
}
