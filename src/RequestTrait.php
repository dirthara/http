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
        if ($this->requestTarget !== null) {
            return $this->requestTarget;
        }

        $target = $this->uri->getPath();

        if ($target === '') {
            $target = '/';
        }

        $query = $this->uri->getQuery();

        if ($query !== '') {
            $target .= '?' . $query;
        }

        return $target;
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
     */
    // @mago-expect lint:no-boolean-flag-parameter -- PSR-7 defines this signature
    public function withUri(UriInterface $uri, bool $preserveHost = false): RequestInterface
    {
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
        $this->uri = $uri;
        $this->body = $body;
        $this->protocolVersion = $this->validateProtocolVersion($protocolVersion);

        foreach ($headers as $name => $value) {
            $this->setHeader((string) $name, $value);
        }

        if ($this->getHeaderLine('Host') === '') {
            $this->setHostFromUri($uri);
        }
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
