<?php

declare(strict_types=1);

namespace Dirthara\Http\Tests;

use Dirthara\Http\Authority;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Dirthara\Http\Exception\InvalidUriException;

final class AuthorityTest extends TestCase
{
    #[Test]
    public function it_is_empty_to_begin_with(): void
    {
        $authority = Authority::empty();

        self::assertSame('', $authority->userInfo);
        self::assertSame('', $authority->host);
        self::assertNull($authority->port);
    }

    #[Test]
    public function it_parses_user_info_host_and_port(): void
    {
        $authority = Authority::fromString('User:Pa ss@Example.COM:8080', 'http://User:Pa ss@Example.COM:8080/');

        self::assertSame('User:Pa%20ss', $authority->userInfo);
        self::assertSame('example.com', $authority->host);
        self::assertSame(8080, $authority->port);
    }

    #[Test]
    public function it_reports_a_malformed_authority_with_the_whole_uri(): void
    {
        $this->expectException(InvalidUriException::class);
        $this->expectExceptionMessage('The given URI "http://host:port/path" is invalid.');

        Authority::fromString('host:port', 'http://host:port/path');
    }

    #[Test]
    public function it_changes_each_part_on_a_copy(): void
    {
        $authority = Authority::fromString('example.com', 'http://example.com');

        self::assertSame('user:secret', $authority->withUserInfo('user', 'secret')->userInfo);
        self::assertSame('', $authority->withUserInfo('', 'secret')->userInfo);
        self::assertSame('example.org', $authority->withHost('EXAMPLE.ORG')->host);
        self::assertSame(8080, $authority->withPort(8080)->port);
        self::assertNull($authority->withPort(8080)->withPort(null)->port);
        self::assertSame('example.com', $authority->host);
        self::assertNull($authority->port);
    }
}
