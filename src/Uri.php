<?php

declare(strict_types=1);

namespace Dirthara\Http;

use Stringable;
use SensitiveParameter;
use Psr\Http\Message\UriInterface;
use Dirthara\Http\Exception\InvalidUriException;

use function ltrim;
use function substr;
use function preg_match;
use function strtolower;
use function str_starts_with;

final readonly class Uri implements UriInterface, Stringable
{
    /**
     * @var array<string, int>
     */
    private const array STANDARD_PORTS = [
        'http' => 80,
        'https' => 443,
    ];

    /**
     * RFC 3986, appendix B: scheme, authority, path, query, and fragment. The authority, query, and fragment groups keep
     * their delimiters, so an empty part is told apart from a missing one.
     */
    private const string URI_PATTERN = '~^(?:([^:/?#]+):)?(//[^/?#]*)?([^?#]*)(\?[^#]*)?(#.*)?$~sD';

    private const string SCHEME_PATTERN = '/^[a-z][a-z0-9+.-]*$/iD';

    private string $scheme;

    private Authority $authority;

    private string $path;

    private string $query;

    private string $fragment;

    /**
     * @throws InvalidUriException
     */
    public function __construct(string $uri = '')
    {
        // Every part is optional, so the pattern matches any string. PHP leaves out unmatched groups at the end.
        $parts = [];
        preg_match(self::URI_PATTERN, $uri, $parts);

        [, $scheme, $authority, $path, $query, $fragment] = $parts + ['', '', '', '', '', ''];
        $this->scheme = $this->normalizeScheme($scheme);
        $this->authority = $authority === ''
            ? Authority::empty()
            : Authority::fromString(substr($authority, offset: 2), $uri);
        $this->path = $this->encodePath($path);
        $this->query = $this->encodeQueryOrFragment(substr($query, offset: 1));
        $this->fragment = $this->encodeQueryOrFragment(substr($fragment, offset: 1));
    }

    public function __toString(): string
    {
        $uri = '';

        if ($this->scheme !== '') {
            $uri .= $this->scheme . ':';
        }

        $authority = $this->getAuthority();

        // A file URI keeps its empty authority, so file:///etc/hosts does not become file:/etc/hosts.
        $hasAuthority = $authority !== '' || $this->scheme === 'file';

        if ($hasAuthority) {
            $uri .= '//' . $authority;
        }

        $path = $this->path;

        if ($hasAuthority && $path !== '' && $path[0] !== '/') {
            $path = '/' . $path;
        }

        if (!$hasAuthority && str_starts_with($path, '//')) {
            $path = '/' . ltrim($path, characters: '/');
        }

        $uri .= $path;

        if ($this->query !== '') {
            $uri .= '?' . $this->query;
        }

        if ($this->fragment !== '') {
            $uri .= '#' . $this->fragment;
        }

        return $uri;
    }

    public function getScheme(): string
    {
        return $this->scheme;
    }

    public function getAuthority(): string
    {
        if ($this->authority->host === '') {
            return '';
        }

        $authority = '';

        if ($this->authority->userInfo !== '') {
            $authority .= $this->authority->userInfo . '@';
        }

        $authority .= $this->authority->host;

        $port = $this->getPort();

        if ($port !== null) {
            $authority .= ':' . $port;
        }

        return $authority;
    }

    public function getUserInfo(): string
    {
        return $this->authority->userInfo;
    }

    public function getHost(): string
    {
        return $this->authority->host;
    }

    public function getPort(): ?int
    {
        $port = $this->authority->port;

        if ($port === null || (self::STANDARD_PORTS[$this->scheme] ?? null) === $port) {
            return null;
        }

        return $port;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getQuery(): string
    {
        return $this->query;
    }

    public function getFragment(): string
    {
        return $this->fragment;
    }

    /**
     * @throws InvalidUriException
     */
    public function withScheme(string $scheme): UriInterface
    {
        $scheme = $this->normalizeScheme($scheme);

        if ($scheme === $this->scheme) {
            return $this;
        }

        return clone($this, [
            'scheme' => $scheme,
        ]);
    }

    public function withUserInfo(string $user, #[SensitiveParameter] ?string $password = null): UriInterface
    {
        $authority = $this->authority->withUserInfo($user, $password);

        if ($authority->userInfo === $this->authority->userInfo) {
            return $this;
        }

        return clone($this, [
            'authority' => $authority,
        ]);
    }

    /**
     * @throws InvalidUriException
     */
    public function withHost(string $host): UriInterface
    {
        $authority = $this->authority->withHost($host);

        if ($authority->host === $this->authority->host) {
            return $this;
        }

        return clone($this, [
            'authority' => $authority,
        ]);
    }

    /**
     * @throws InvalidUriException
     */
    public function withPort(?int $port): UriInterface
    {
        $authority = $this->authority->withPort($port);

        if ($authority->port === $this->authority->port) {
            return $this;
        }

        return clone($this, [
            'authority' => $authority,
        ]);
    }

    public function withPath(string $path): UriInterface
    {
        $path = $this->encodePath($path);

        if ($path === $this->path) {
            return $this;
        }

        return clone($this, [
            'path' => $path,
        ]);
    }

    public function withQuery(string $query): UriInterface
    {
        $query = $this->encodeQueryOrFragment($query);

        if ($query === $this->query) {
            return $this;
        }

        return clone($this, [
            'query' => $query,
        ]);
    }

    public function withFragment(string $fragment): UriInterface
    {
        $fragment = $this->encodeQueryOrFragment($fragment);

        if ($fragment === $this->fragment) {
            return $this;
        }

        return clone($this, [
            'fragment' => $fragment,
        ]);
    }

    /**
     * @throws InvalidUriException
     */
    private function normalizeScheme(string $scheme): string
    {
        if ($scheme === '') {
            return '';
        }

        if (!preg_match(self::SCHEME_PATTERN, $scheme)) {
            throw InvalidUriException::invalidScheme($scheme);
        }

        return strtolower($scheme);
    }

    private function encodePath(string $path): string
    {
        return PercentEncoding::encode($path, "!$&'()*+,;=:@/");
    }

    private function encodeQueryOrFragment(string $value): string
    {
        return PercentEncoding::encode($value, "!$&'()*+,;=:@/?");
    }
}
