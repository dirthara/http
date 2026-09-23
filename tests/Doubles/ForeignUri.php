<?php

declare(strict_types=1);

namespace Dirthara\Http\Tests\Doubles;

use Stringable;
use LogicException;
use SensitiveParameter;
use Psr\Http\Message\UriInterface;

/**
 * A URI from another implementation, which hands back its path and query without validating or encoding them.
 */
final readonly class ForeignUri implements UriInterface, Stringable
{
    public function __construct(
        private string $path,
        private string $query = '',
        private string $host = '',
    ) {}

    public function __toString(): string
    {
        return $this->path;
    }

    public function getScheme(): string
    {
        return '';
    }

    public function getAuthority(): string
    {
        return $this->host;
    }

    public function getUserInfo(): string
    {
        return '';
    }

    public function getHost(): string
    {
        return $this->host;
    }

    public function getPort(): ?int
    {
        return null;
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
        return '';
    }

    public function withScheme(string $scheme): UriInterface
    {
        throw new LogicException('Not needed by the tests.');
    }

    public function withUserInfo(string $user, #[SensitiveParameter] ?string $password = null): UriInterface
    {
        throw new LogicException('Not needed by the tests.');
    }

    public function withHost(string $host): UriInterface
    {
        throw new LogicException('Not needed by the tests.');
    }

    public function withPort(?int $port): UriInterface
    {
        throw new LogicException('Not needed by the tests.');
    }

    public function withPath(string $path): UriInterface
    {
        throw new LogicException('Not needed by the tests.');
    }

    public function withQuery(string $query): UriInterface
    {
        throw new LogicException('Not needed by the tests.');
    }

    public function withFragment(string $fragment): UriInterface
    {
        throw new LogicException('Not needed by the tests.');
    }
}
