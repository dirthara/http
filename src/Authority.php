<?php

declare(strict_types=1);

namespace Dirthara\Http;

use SensitiveParameter;
use Dirthara\Http\Exception\InvalidUriException;

use function strlen;
use function strpos;
use function substr;
use function strrpos;
use function filter_var;
use function preg_match;
use function strtolower;
use function str_ends_with;
use function str_starts_with;

use const FILTER_FLAG_IPV6;
use const FILTER_VALIDATE_IP;

/**
 * The authority of a URI: its user info, host, and port, each validated and normalized.
 *
 * @internal
 */
final readonly class Authority
{
    private const string HOST_PORT_PATTERN = '/^(\[[^\]]*\]|[^:]*)(:\d*)?$/D';

    private const string HOST_PATTERN = '/^(?:[a-z0-9._~!$&\'()*+,;=-]|%[a-f0-9]{2})+$/iD';

    private const string IP_FUTURE_PATTERN = '/^v[a-f0-9]+\.[a-z0-9._~!$&\'()*+,;=:-]+$/iD';

    private function __construct(
        public string $userInfo,
        public string $host,
        public ?int $port,
    ) {}

    public static function empty(): self
    {
        return new self('', '', null);
    }

    /**
     * @param string $uri The whole URI, which a malformed authority is reported with.
     *
     * @throws InvalidUriException
     */
    public static function fromString(string $authority, string $uri): self
    {
        $userInfo = '';
        $at = strrpos($authority, needle: '@');

        if ($at !== false) {
            $userInfo = self::parseUserInfo(substr($authority, offset: 0, length: $at));
            $authority = substr($authority, $at + 1);
        }

        $hostAndPort = [];

        if (!preg_match(self::HOST_PORT_PATTERN, $authority, $hostAndPort)) {
            throw InvalidUriException::forInvalidUri($uri);
        }

        [, $host, $port] = $hostAndPort + ['', '', ''];

        return new self($userInfo, self::normalizeHost($host), self::parsePort($port, $uri));
    }

    public function withUserInfo(string $user, #[SensitiveParameter] ?string $password): self
    {
        return new self(self::buildUserInfo($user, $password), $this->host, $this->port);
    }

    /**
     * @throws InvalidUriException
     */
    public function withHost(string $host): self
    {
        return new self($this->userInfo, self::normalizeHost($host), $this->port);
    }

    /**
     * @throws InvalidUriException
     */
    public function withPort(?int $port): self
    {
        return new self($this->userInfo, $this->host, $port === null ? null : self::validatePort($port));
    }

    private static function parseUserInfo(#[SensitiveParameter] string $userInfo): string
    {
        $colon = strpos($userInfo, needle: ':');

        if ($colon === false) {
            return self::buildUserInfo($userInfo, null);
        }

        return self::buildUserInfo(substr($userInfo, offset: 0, length: $colon), substr($userInfo, $colon + 1));
    }

    private static function buildUserInfo(string $user, #[SensitiveParameter] ?string $password): string
    {
        if ($user === '') {
            return '';
        }

        // The first colon separates the user from the password, so only the password may contain one unencoded.
        $userInfo = PercentEncoding::encode($user, "!$&'()*+,;=");

        if ($password !== null) {
            $userInfo .= ':' . PercentEncoding::encode($password, "!$&'()*+,;=:");
        }

        return $userInfo;
    }

    /**
     * The port group keeps its colon: ':' alone is an empty port, which RFC 3986 allows and means none.
     *
     * @throws InvalidUriException
     */
    private static function parsePort(string $port, string $uri): ?int
    {
        if (strlen($port) <= 1) {
            return null;
        }

        if (strlen($port) > 6) {
            throw InvalidUriException::forInvalidUri($uri);
        }

        return self::validatePort((int) substr($port, offset: 1));
    }

    /**
     * @throws InvalidUriException
     */
    private static function normalizeHost(string $host): string
    {
        if ($host === '') {
            return '';
        }

        if (str_starts_with($host, '[')) {
            return self::normalizeIpLiteral($host);
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
    private static function validatePort(int $port): int
    {
        if ($port < 1 || $port > 65_535) {
            throw InvalidUriException::invalidPort($port);
        }

        return $port;
    }
}
