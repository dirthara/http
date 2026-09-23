<?php

declare(strict_types=1);

namespace Dirthara\Http\Tests\Factory;

use PHPUnit\Framework\TestCase;
use Dirthara\Http\Factory\UriFactory;
use PHPUnit\Framework\Attributes\Test;
use Dirthara\Http\Exception\InvalidUriException;

final class UriFactoryTest extends TestCase
{
    #[Test]
    public function it_creates_a_uri_from_a_string(): void
    {
        self::assertSame('https://example.com/path', (string) new UriFactory()->createUri('https://example.com/path'));
    }

    #[Test]
    public function it_creates_an_empty_uri_by_default(): void
    {
        self::assertSame('', (string) new UriFactory()->createUri());
    }

    #[Test]
    public function it_refuses_a_uri_it_cannot_parse(): void
    {
        $this->expectException(InvalidUriException::class);

        new UriFactory()->createUri('http://host:port');
    }
}
