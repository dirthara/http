<?php

declare(strict_types=1);

namespace Dirthara\Http;

use SensitiveParameter;
use Psr\Http\Message\UriInterface;
use Dirthara\Http\Exception\InvalidUriException;

final class Uri implements UriInterface
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

    private const string HOST_PORT_PATTERN = '/^(\[[^\]]*\]|[^:]*)(:\d*)?$/D';

    private const string SCHEME_PATTERN = '/^[a-z][a-z0-9+.-]*$/iD';

    private const string HOST_PATTERN = '/^(?:[a-z0-9._~!$&\'()*+,;=-]|%[a-f0-9]{2})+$/iD';

    private const string IP_FUTURE_PATTERN = '/^v[a-f0-9]+\.[a-z0-9._~!$&\'()*+,;=:-]+$/iD';

    private string $scheme = '';

    private string $userInfo = '';

    private string $host = '';

    private ?int $port = null;

    private string $path = '';

    private string $query = '';

    private string $fragment = '';

    /**
     * @throws InvalidUriException
     */
    public function __construct(string $uri = '')
    {
        if ($uri === '') {
            return;
        }

        // Every part is optional, so the pattern matches any string. PHP leaves out unmatched groups at the end.
        $parts = [];
        preg_match(self::URI_PATTERN, $uri, $parts);

        [, $scheme, $authority, $path, $query, $fragment] = $parts + ['', '', '', '', '', ''];

        if ($scheme !== '') {
            $this->scheme = $this->normalizeScheme($scheme);
        }

        if ($authority !== '') {
            $this->parseAuthority($uri, substr($authority, offset: 2));
        }

        $this->path = $this->encodePath($path);

        if ($query !== '') {
            $this->query = $this->encodeQueryOrFragment(substr($query, offset: 1));
        }

        if ($fragment !== '') {
            $this->fragment = $this->encodeQueryOrFragment(substr($fragment, offset: 1));
        }
    }

    public function getScheme(): string
    {
        return $this->scheme;
    }

    public function getAuthority(): string
    {
        if ($this->host === '') {
            return '';
        }

        $authority = '';

        if ($this->userInfo !== '') {
            $authority .= $this->userInfo . '@';
        }

        $authority .= $this->host;

        $port = $this->getPort();

        if ($port !== null) {
            $authority .= ':' . $port;
        }

        return $authority;
    }

    public function getUserInfo(): string
    {
        return $this->userInfo;
    }

    public function getHost(): string
    {
        return $this->host;
    }

    public function getPort(): ?int
    {
        if ($this->port === null) {
            return null;
        }

        if ((self::STANDARD_PORTS[$this->scheme] ?? null) === $this->port) {
            return null;
        }

        return $this->port;
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
        $userInfo = $this->buildUserInfo($user, $password);

        if ($userInfo === $this->userInfo) {
            return $this;
        }

        return clone($this, [
            'userInfo' => $userInfo,
        ]);
    }

    /**
     * @throws InvalidUriException
     */
    public function withHost(string $host): UriInterface
    {
        $host = $this->normalizeHost($host);

        if ($host === $this->host) {
            return $this;
        }

        return clone($this, [
            'host' => $host,
        ]);
    }

    /**
     * @throws InvalidUriException
     */
    public function withPort(?int $port): UriInterface
    {
        if ($port !== null) {
            $port = $this->validatePort($port);
        }

        if ($port === $this->port) {
            return $this;
        }

        return clone($this, [
            'port' => $port,
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

    /**
     * @throws InvalidUriException
     */
    private function parseAuthority(string $uri, string $authority): void
    {
        $at = strrpos($authority, needle: '@');

        if ($at !== false) {
            $this->userInfo = $this->parseUserInfo(substr($authority, offset: 0, length: $at));
            $authority = substr($authority, $at + 1);
        }

        $hostAndPort = [];

        if (!preg_match(self::HOST_PORT_PATTERN, $authority, $hostAndPort)) {
            throw InvalidUriException::forInvalidUri($uri);
        }

        [, $host, $port] = $hostAndPort + ['', '', ''];

        $this->host = $this->normalizeHost($host);

        // The group keeps its colon: ':' alone is an empty port, which RFC 3986 allows and means none.
        if (strlen($port) <= 1) {
            return;
        }

        if (strlen($port) > 6) {
            throw InvalidUriException::forInvalidUri($uri);
        }

        $this->port = $this->validatePort((int) substr($port, offset: 1));
    }

    private function parseUserInfo(#[SensitiveParameter] string $userInfo): string
    {
        $colon = strpos($userInfo, needle: ':');

        if ($colon === false) {
            return $this->buildUserInfo($userInfo, null);
        }

        return $this->buildUserInfo(substr($userInfo, offset: 0, length: $colon), substr($userInfo, $colon + 1));
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

    /**
     * @throws InvalidUriException
     */
    private function normalizeHost(string $host): string
    {
        if ($host === '') {
            return '';
        }

        if (str_starts_with($host, '[')) {
            return $this->normalizeIpLiteral($host);
        }

        if (!preg_match(self::HOST_PATTERN, $host)) {
            throw InvalidUriException::invalidHost($host);
        }

        return strtolower($host);
    }

    /**
     * @throws InvalidUriException
     */
    private static function normalizeIpLiteral(string $host): string
    {
        if (!str_ends_with($host, ']')) {
            throw InvalidUriException::invalidIpLiteralHost($host);
        }

        $literal = substr($host, offset: 1, length: -1);
        $isIpv6 = filter_var($literal, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false;
        $isIpvFuture = preg_match(self::IP_FUTURE_PATTERN, $literal) === 1;

        if (!$isIpv6 && !$isIpvFuture) {
            throw InvalidUriException::invalidIpLiteralHost($host);
        }

        return strtolower($host);
    }

    /**
     * @throws InvalidUriException
     */
    private function validatePort(int $port): int
    {
        if ($port < 1 || $port > 65_535) {
            throw InvalidUriException::invalidPort($port);
        }

        return $port;
    }

    private function encodePath(string $path): string
    {
        return $this->percentEncode($path, "!$&'()*+,;=:@/");
    }

    private function encodeQueryOrFragment(string $value): string
    {
        return $this->percentEncode($value, "!$&'()*+,;=:@/?");
    }

    private function buildUserInfo(string $user, #[SensitiveParameter] ?string $password): string
    {
        if ($user === '') {
            return '';
        }

        // The first colon separates the user from the password, so only the password may contain one unencoded.
        $userInfo = $this->percentEncode($user, "!$&'()*+,;=");

        if ($password !== null) {
            $userInfo .= ':' . $this->percentEncode($password, "!$&'()*+,;=:");
        }

        return $userInfo;
    }

    private function percentEncode(string $value, string $extraAllowed): string
    {
        $encoded = '';
        $length = strlen($value);

        for ($index = 0; $index < $length; $index++) {
            $character = $value[$index];

            if (
                $character === '%'
                && ($index + 2) < $length
                && ctype_xdigit($value[$index + 1])
                && ctype_xdigit($value[$index + 2])
            ) {
                $encoded .= substr($value, $index, length: 3);
                $index += 2;

                continue;
            }

            $ordinal = ord($character);

            $isUnreserved =
                $ordinal >= 65 && $ordinal <= 90
                || $ordinal >= 97 && $ordinal <= 122
                || $ordinal >= 48 && $ordinal <= 57
                || str_contains('-._~', $character);

            if ($isUnreserved || str_contains($extraAllowed, $character)) {
                $encoded .= $character;

                continue;
            }

            $encoded .= sprintf('%%%02X', $ordinal);
        }

        return $encoded;
    }
}
