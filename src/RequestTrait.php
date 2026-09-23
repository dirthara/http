<?php

declare(strict_types=1);

namespace Dirthara\Http;

use Psr\Http\Message\UriInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\RequestInterface;
use Dirthara\Http\Exception\InvalidMessageException;
use Dirthara\Http\Exception\InvalidRequestException;

/**
 * @internal
 *
 * @require-implements RequestInterface
 */
trait RequestTrait
{
    use MessageTrait;

    private const string INVALID_REQUEST_TARGET_PATTERN = '/[\x00-\x20\x7F]/';

    private string $method;

    private UriInterface $uri;

    private ?string $requestTarget = null;

    public function getRequestTarget(): string
    {
        return $this->requestTarget ?? $this->deriveRequestTarget($this->uri);
    }

    /**
     * @throws InvalidRequestException
     */
    public function withRequestTarget(string $requestTarget): RequestInterface
    {
        $requestTarget = $this->validateRequestTarget($requestTarget);

        if ($requestTarget === $this->requestTarget) {
            return $this;
        }

        return clone($this, [
            'requestTarget' => $requestTarget,
        ]);
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    /**
     * @throws InvalidRequestException
     */
    public function withMethod(string $method): RequestInterface
    {
        $method = $this->validateMethod($method);

        if ($method === $this->method) {
            return $this;
        }

        return clone($this, [
            'method' => $method,
        ]);
    }

    public function getUri(): UriInterface
    {
        return $this->uri;
    }

    /**
     * @throws InvalidMessageException
     * @throws InvalidRequestException
     */
    // @mago-expect lint:no-boolean-flag-parameter -- PSR-7 defines this signature
    public function withUri(UriInterface $uri, bool $preserveHost = false): RequestInterface
    {
        $this->validateRequestTarget($this->deriveRequestTarget($uri));

        $clone = clone($this, [
            'uri' => $uri,
        ]);

        if (!$preserveHost || $clone->getHeaderLine('Host') === '') {
            $clone->setHostFromUri($uri);
        }

        return $clone;
    }

    /**
     * @param array<array-key, string|array<array-key, string>> $headers
     *
     * @throws InvalidMessageException
     * @throws InvalidRequestException
     */
    private function initializeRequest(
        string $method,
        UriInterface $uri,
        StreamInterface $body,
        array $headers,
        string $protocolVersion,
    ): void {
        $this->method = $this->validateMethod($method);
        $this->validateRequestTarget($this->deriveRequestTarget($uri));
        $this->uri = $uri;
        $this->body = $body;
        $this->protocolVersion = $this->validateProtocolVersion($protocolVersion);

        $this->setHeaders($headers);

        if ($this->getHeaderLine('Host') === '') {
            $this->setHostFromUri($uri);
        }
    }

    /**
     * The origin form of the URI: its path with exactly one leading slash, and its query. Collapsing leading slashes
     * keeps a path such as //evil.example from reading as an authority.
     */
    private function deriveRequestTarget(UriInterface $uri): string
    {
        $target = '/' . ltrim($uri->getPath(), characters: '/');
        $query = $uri->getQuery();

        return $query === '' ? $target : $target . '?' . $query;
    }

    /**
     * @throws InvalidMessageException
     */
    private function setHostFromUri(UriInterface $uri): void
    {
        $host = $uri->getHost();

        if ($host === '') {
            return;
        }

        $port = $uri->getPort();

        if ($port !== null) {
            $host .= ':' . $port;
        }

        $name = $this->headerNames['host'] ?? 'Host';

        $this->setHeader($name, $host);

        $this->headers = [$name => $this->headers[$name]] + $this->headers;
    }

    /**
     * @throws InvalidRequestException
     */
    private function validateMethod(string $method): string
    {
        if (!preg_match(self::TOKEN_PATTERN, $method)) {
            throw InvalidRequestException::invalidMethod($method);
        }

        return $method;
    }

    /**
     * @throws InvalidRequestException
     */
    private function validateRequestTarget(string $requestTarget): string
    {
        if ($requestTarget === '' || preg_match(self::INVALID_REQUEST_TARGET_PATTERN, $requestTarget)) {
            throw InvalidRequestException::invalidRequestTarget();
        }

        return $requestTarget;
    }
}
