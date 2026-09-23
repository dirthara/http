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

        $parts = parse_url($uri);

        if ($parts === false) {
            throw InvalidUriException::forInvalidUri($uri);
        }

        if (array_key_exists('scheme', $parts)) {
            $this->scheme = $this->normalizeScheme($parts['scheme']);
        }

        if (array_key_exists('user', $parts)) {
            $this->userInfo = $this->encodeUserInfo($parts['user']);

            if (array_key_exists('pass', $parts)) {
                $this->userInfo .= ':' . $this->encodeUserInfo($parts['pass']);
            }
        }

        if (array_key_exists('host', $parts)) {
            $this->host = $this->normalizeHost($parts['host']);
        }

        if (array_key_exists('port', $parts)) {
            $this->port = $this->validatePort($parts['port']);
        }

        if (array_key_exists('path', $parts)) {
            $this->path = $this->encodePath($parts['path']);
        }

        if (array_key_exists('query', $parts)) {
            $this->query = $this->encodeQueryOrFragment($parts['query']);
        }

        if (array_key_exists('fragment', $parts)) {
            $this->fragment = $this->encodeQueryOrFragment($parts['fragment']);
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
    public function withScheme(string $scheme): self
    {
        $scheme = $this->normalizeScheme($scheme);

        if ($scheme === $this->scheme) {
            return $this;
        }

        return clone($this, [
            'scheme' => $scheme,
        ]);
    }

    public function withUserInfo(string $user, #[SensitiveParameter] ?string $password = null): self
    {
        $userInfo = '';

        if ($user !== '') {
            $userInfo = self::encodeUserInfo($user);

            if ($password !== null) {
                $userInfo .= ':' . self::encodeUserInfo($password);
            }
        }

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
    public function withHost(string $host): self
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
    public function withPort(?int $port): self
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

    public function withPath(string $path): self
    {
        $path = $this->encodePath($path);

        if ($path === $this->path) {
            return $this;
        }

        return clone($this, [
            'path' => $path,
        ]);
    }

    public function withQuery(string $query): self
    {
        $query = $this->encodeQueryOrFragment($query);

        if ($query === $this->query) {
            return $this;
        }

        return clone($this, [
            'query' => $query,
        ]);
    }

    public function withFragment(string $fragment): self
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

        if ($authority !== '') {
            $uri .= '//' . $authority;
        }

        $path = $this->path;

        if ($authority !== '' && $path !== '' && $path[0] !== '/') {
            $path = '/' . $path;
        }

        if ($authority === '' && str_starts_with($path, '//')) {
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

    private function encodeUserInfo(string $value): string
    {
        return $this->percentEncode($value, "!$&'()*+,;=:");
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
