<?php

declare(strict_types=1);

namespace Dirthara\Http\Tests;

use Dirthara\Http\Uri;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Dirthara\Http\Exception\InvalidUriException;

final class UriTest extends TestCase
{
    #[Test]
    public function it_is_empty_when_nothing_is_given(): void
    {
        $uri = new Uri();

        self::assertSame('', $uri->getScheme());
        self::assertSame('', $uri->getAuthority());
        self::assertSame('', $uri->getUserInfo());
        self::assertSame('', $uri->getHost());
        self::assertNull($uri->getPort());
        self::assertSame('', $uri->getPath());
        self::assertSame('', $uri->getQuery());
        self::assertSame('', $uri->getFragment());
        self::assertSame('', (string) $uri);
    }

    #[Test]
    public function it_parses_every_component(): void
    {
        $uri = new Uri('https://user:pass@example.com:8080/path/to?page=2#top');

        self::assertSame('https', $uri->getScheme());
        self::assertSame('user:pass', $uri->getUserInfo());
        self::assertSame('example.com', $uri->getHost());
        self::assertSame(8080, $uri->getPort());
        self::assertSame('user:pass@example.com:8080', $uri->getAuthority());
        self::assertSame('/path/to', $uri->getPath());
        self::assertSame('page=2', $uri->getQuery());
        self::assertSame('top', $uri->getFragment());
        self::assertSame('https://user:pass@example.com:8080/path/to?page=2#top', (string) $uri);
    }

    #[Test]
    public function it_parses_a_user_without_a_password(): void
    {
        self::assertSame('user', new Uri('http://user@example.com/')->getUserInfo());
    }

    #[Test]
    public function it_lowercases_the_scheme_and_the_host_but_not_the_path(): void
    {
        $uri = new Uri('HTTP://EXAMPLE.COM/Path');

        self::assertSame('http', $uri->getScheme());
        self::assertSame('example.com', $uri->getHost());
        self::assertSame('/Path', $uri->getPath());
    }

    #[Test]
    public function it_refuses_a_uri_it_cannot_parse(): void
    {
        try {
            new Uri('http://host:port');

            self::fail('Expected an InvalidUriException.');
        } catch (InvalidUriException $exception) {
            self::assertSame('The given URI "http://host:port" is invalid.', $exception->getMessage());
            self::assertSame(['uri' => 'http://host:port'], $exception->context);
            self::assertNull($exception->getPrevious());
        }
    }

    #[Test]
    public function it_hides_the_standard_port_of_the_scheme(): void
    {
        self::assertNull(new Uri('http://example.com:80/')->getPort());
        self::assertNull(new Uri('https://example.com:443/')->getPort());
        self::assertSame('example.com', new Uri('http://example.com:80/')->getAuthority());
        self::assertSame(443, new Uri('http://example.com:443/')->getPort());
        self::assertSame(8080, new Uri('https://example.com:8080/')->getPort());
    }

    #[Test]
    public function it_has_no_authority_without_a_host(): void
    {
        self::assertSame(
            '',
            new Uri('/just/a/path')
                ->withUserInfo('user')
                ->getAuthority(),
        );
    }

    #[Test]
    public function it_refuses_a_scheme_that_is_not_a_scheme(): void
    {
        try {
            new Uri()->withScheme('1http');

            self::fail('Expected an InvalidUriException.');
        } catch (InvalidUriException $exception) {
            self::assertSame('The given scheme "1http" is invalid.', $exception->getMessage());
            self::assertSame(['scheme' => '1http'], $exception->context);
        }
    }

    #[Test]
    public function it_refuses_a_host_that_is_not_a_host(): void
    {
        try {
            new Uri()->withHost('exa mple.com');

            self::fail('Expected an InvalidUriException.');
        } catch (InvalidUriException $exception) {
            self::assertSame('The given host "exa mple.com" is invalid.', $exception->getMessage());
            self::assertSame(['host' => 'exa mple.com'], $exception->context);
        }
    }

    #[Test]
    public function it_accepts_an_ip_literal_host(): void
    {
        self::assertSame('[::1]', new Uri('http://[::1]:8080/')->getHost());
        self::assertSame('[::1]:8080', new Uri('http://[::1]:8080/')->getAuthority());
        self::assertSame(
            '[2001:db8::1]',
            new Uri()
                ->withHost('[2001:DB8::1]')
                ->getHost(),
        );
        self::assertSame(
            '[v1.fe80::a+en1]',
            new Uri()
                ->withHost('[v1.fe80::a+en1]')
                ->getHost(),
        );
    }

    #[Test]
    public function it_refuses_an_ip_literal_that_is_never_closed(): void
    {
        try {
            new Uri()->withHost('[::1');

            self::fail('Expected an InvalidUriException.');
        } catch (InvalidUriException $exception) {
            self::assertSame('The given IP literal host "[::1" is invalid.', $exception->getMessage());
            self::assertSame(['literal' => '[::1'], $exception->context);
        }
    }

    #[Test]
    public function it_refuses_an_ip_literal_that_is_neither_ipv6_nor_ipvfuture(): void
    {
        $this->expectException(InvalidUriException::class);
        $this->expectExceptionMessage('The given IP literal host "[example.com]" is invalid.');

        new Uri()->withHost('[example.com]');
    }

    #[Test]
    public function it_refuses_a_scheme_that_ends_in_a_newline(): void
    {
        $this->expectException(InvalidUriException::class);

        new Uri()->withScheme("http\n");
    }

    #[Test]
    public function it_refuses_a_host_that_ends_in_a_newline(): void
    {
        $this->expectException(InvalidUriException::class);

        new Uri()->withHost("example.com\n");
    }

    #[Test]
    public function it_refuses_an_ipvfuture_literal_that_ends_in_a_newline(): void
    {
        $this->expectException(InvalidUriException::class);

        new Uri()->withHost("[v1.fe\n]");
    }

    #[Test]
    public function it_refuses_a_port_outside_the_valid_range(): void
    {
        try {
            new Uri()->withPort(0);

            self::fail('Expected an InvalidUriException.');
        } catch (InvalidUriException $exception) {
            self::assertSame('The given port "0" is invalid.', $exception->getMessage());
            self::assertSame(['port' => 0], $exception->context);
        }

        $this->expectException(InvalidUriException::class);

        new Uri()->withPort(65_536);
    }

    #[Test]
    public function it_accepts_the_edges_of_the_port_range(): void
    {
        self::assertSame(
            1,
            new Uri()
                ->withPort(1)
                ->getPort(),
        );
        self::assertSame(
            65_535,
            new Uri()
                ->withPort(65_535)
                ->getPort(),
        );
    }

    #[Test]
    public function it_returns_a_new_instance_for_every_change(): void
    {
        $uri = new Uri('http://example.com/path?page=2#top');

        self::assertNotSame($uri, $uri->withScheme('https'));
        self::assertNotSame($uri, $uri->withUserInfo('user'));
        self::assertNotSame($uri, $uri->withHost('example.org'));
        self::assertNotSame($uri, $uri->withPort(8080));
        self::assertNotSame($uri, $uri->withPath('/other'));
        self::assertNotSame($uri, $uri->withQuery('page=3'));
        self::assertNotSame($uri, $uri->withFragment('bottom'));

        self::assertSame('http://example.com/path?page=2#top', (string) $uri);
    }

    #[Test]
    public function it_keeps_the_same_instance_when_nothing_changes(): void
    {
        $uri = new Uri('http://user@example.com:8080/path?page=2#top');

        self::assertSame($uri, $uri->withScheme('HTTP'));
        self::assertSame($uri, $uri->withUserInfo('user'));
        self::assertSame($uri, $uri->withHost('EXAMPLE.com'));
        self::assertSame($uri, $uri->withPort(8080));
        self::assertSame($uri, $uri->withPath('/path'));
        self::assertSame($uri, $uri->withQuery('page=2'));
        self::assertSame($uri, $uri->withFragment('top'));
    }

    #[Test]
    public function it_drops_the_parts_that_are_given_as_empty(): void
    {
        $uri = new Uri('http://user:pass@example.com:8080/path');

        self::assertSame('', $uri->withScheme('')->getScheme());
        self::assertSame('', $uri->withHost('')->getHost());
        self::assertSame('', $uri->withUserInfo('')->getUserInfo());
        self::assertNull($uri->withPort(null)->getPort());
    }

    #[Test]
    public function it_composes_user_info_from_a_user_and_a_password(): void
    {
        self::assertSame(
            'user:pass',
            new Uri()
                ->withUserInfo('user', 'pass')
                ->getUserInfo(),
        );
        self::assertSame(
            'user',
            new Uri()
                ->withUserInfo('user')
                ->getUserInfo(),
        );
        self::assertSame(
            'u%40s:p%3Fss',
            new Uri()
                ->withUserInfo('u@s', 'p?ss')
                ->getUserInfo(),
        );
    }

    #[Test]
    public function it_writes_a_scheme_without_an_authority(): void
    {
        $uri = new Uri()
            ->withScheme('mailto')
            ->withPath('someone@example.com');

        self::assertSame('mailto:someone@example.com', (string) $uri);
    }

    #[Test]
    public function it_gives_a_rootless_path_a_slash_when_there_is_an_authority(): void
    {
        $uri = new Uri('http://example.com')->withPath('path');

        self::assertSame('http://example.com/path', (string) $uri);
    }

    #[Test]
    public function it_collapses_leading_slashes_on_a_path_without_an_authority(): void
    {
        self::assertSame('/path', (string) new Uri()->withPath('//path'));
    }

    #[Test]
    public function it_percent_encodes_what_a_component_may_not_contain(): void
    {
        self::assertSame(
            '/a%20b',
            new Uri()
                ->withPath('/a b')
                ->getPath(),
        );
        self::assertSame(
            'a=1%20or%202',
            new Uri()
                ->withQuery('a=1 or 2')
                ->getQuery(),
        );
        self::assertSame(
            'a%20b',
            new Uri()
                ->withFragment('a b')
                ->getFragment(),
        );
    }

    #[Test]
    public function it_leaves_what_a_component_may_contain_alone(): void
    {
        self::assertSame(
            "/a-b._~!$&'()*+,;=:@/c",
            new Uri()
                ->withPath("/a-b._~!$&'()*+,;=:@/c")
                ->getPath(),
        );
        self::assertSame(
            'a=1&b=/c?d',
            new Uri()
                ->withQuery('a=1&b=/c?d')
                ->getQuery(),
        );
        self::assertSame(
            'A1z9',
            new Uri()
                ->withFragment('A1z9')
                ->getFragment(),
        );
    }

    #[Test]
    public function it_keeps_an_existing_percent_encoded_triplet_as_it_is(): void
    {
        self::assertSame(
            '/a%2Fb',
            new Uri()
                ->withPath('/a%2Fb')
                ->getPath(),
        );
        self::assertSame(
            '/a%2fb%20c',
            new Uri()
                ->withPath('/a%2fb c')
                ->getPath(),
        );
    }

    #[Test]
    public function it_encodes_a_percent_that_starts_nothing(): void
    {
        self::assertSame(
            '/100%25',
            new Uri()
                ->withPath('/100%')
                ->getPath(),
        );
        self::assertSame(
            '/a%25zz',
            new Uri()
                ->withPath('/a%zz')
                ->getPath(),
        );
        self::assertSame(
            '/a%25b',
            new Uri()
                ->withPath('/a%b')
                ->getPath(),
        );
    }

    #[Test]
    public function it_parses_a_relative_path_whose_last_segment_looks_like_a_port(): void
    {
        self::assertSame('/time/12:30', new Uri('/time/12:30')->getPath());
    }

    #[Test]
    public function it_parses_an_empty_authority(): void
    {
        $uri = new Uri('///path');

        self::assertSame('', $uri->getHost());
        self::assertSame('/path', $uri->getPath());
    }

    #[Test]
    public function it_parses_a_scheme_followed_by_a_path_rather_than_a_host_and_port(): void
    {
        $uri = new Uri('urn:isbn');

        self::assertSame('urn', $uri->getScheme());
        self::assertSame('', $uri->getHost());
        self::assertSame('isbn', $uri->getPath());
        self::assertSame('urn:isbn', (string) $uri);
    }

    #[Test]
    public function it_parses_an_ipv6_host_with_a_port(): void
    {
        $uri = new Uri('http://[::1]:8080/');

        self::assertSame('[::1]', $uri->getHost());
        self::assertSame(8080, $uri->getPort());
    }

    #[Test]
    public function it_treats_an_empty_port_as_no_port(): void
    {
        self::assertNull(new Uri('http://example.com:/')->getPort());
    }

    #[Test]
    public function it_refuses_a_port_that_is_not_only_digits(): void
    {
        $this->expectException(InvalidUriException::class);
        $this->expectExceptionMessage('The given URI "http://example.com:+80/" is invalid.');

        new Uri('http://example.com:+80/');
    }

    #[Test]
    public function it_refuses_a_port_with_more_than_five_digits(): void
    {
        $this->expectException(InvalidUriException::class);
        $this->expectExceptionMessage('The given URI "http://example.com:123456/" is invalid.');

        new Uri('http://example.com:123456/');
    }

    #[Test]
    public function it_percent_encodes_control_characters_in_a_parsed_uri(): void
    {
        $uri = new Uri("http://us\ter:pa\nss@example.com/a\r\nb?q=\x01#f\x7f");

        self::assertSame('us%09er:pa%0Ass', $uri->getUserInfo());
        self::assertSame('http://us%09er:pa%0Ass@example.com/a%0D%0Ab?q=%01#f%7F', (string) $uri);
    }

    #[Test]
    public function it_refuses_a_parsed_host_with_a_control_character_and_escapes_it_in_the_message(): void
    {
        try {
            new Uri("http://exa\rmple.com/");

            self::fail('Expected an InvalidUriException.');
        } catch (InvalidUriException $exception) {
            self::assertSame('The given host "exa\rmple.com" is invalid.', $exception->getMessage());
            self::assertSame(['host' => "exa\rmple.com"], $exception->context);
        }
    }

    #[Test]
    public function it_splits_parsed_user_info_at_the_first_colon_and_the_host_at_the_last_at_sign(): void
    {
        self::assertSame('user:pa:ss%40word', new Uri('http://user:pa:ss@word@example.com/')->getUserInfo());
    }

    #[Test]
    public function it_encodes_a_colon_in_the_user_but_not_in_the_password(): void
    {
        self::assertSame(
            'us%3Aer:pa:ss',
            new Uri('http://example.com')
                ->withUserInfo('us:er', 'pa:ss')
                ->getUserInfo(),
        );
    }

    #[Test]
    public function it_keeps_the_empty_authority_of_a_file_uri(): void
    {
        self::assertSame('file:///etc/hosts', (string) new Uri('file:///etc/hosts'));
        self::assertSame('file:///etc/hosts', (string) new Uri('file:')->withPath('etc/hosts'));
    }
}
