<?php

declare(strict_types=1);

namespace Dirthara\Http;

use Psr\Http\Message\UriInterface;
use Psr\Http\Message\RequestInterface;
use Dirthara\Http\Exception\InvalidMessageException;
use Dirthara\Http\Exception\InvalidRequestException;

use function ltrim;
use function preg_match;

/**
 * @internal
 *
 * @require-implements RequestInterface
 */
trait ImplementsRequest
{
    use ImplementsMessage;

    private const string INVALID_REQUEST_TARGET_PATTERN = '/[\x00-\x20\x7F]/';

    private readonly string $method;

    private readonly UriInterface $uri;

    private readonly ?string $requestTarget;

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
        $uri = $this->validateUri($uri);
        $headers = $this->headers;

        if (!$preserveHost || $headers->line('Host') === '') {
            $headers = $this->withHostFrom($headers, $uri);
        }

        return clone($this, [
            'uri' => $uri,
            'headers' => $headers,
        ]);
    }

    /**
     * @param array<array-key, string|array<array-key, string>> $headers
     *
     * @throws InvalidMessageException
     */
    private function requestHeaders(array $headers, UriInterface $uri): Headers
    {
        $given = Headers::fromArray($headers);

        return $given->line('Host') === '' ? $this->withHostFrom($given, $uri) : $given;
    }

    /**
     * @throws InvalidRequestException
     */
    private function validateUri(UriInterface $uri): UriInterface
    {
        $this->validateRequestTarget($this->deriveRequestTarget($uri));

        return $uri;
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
     * Sets Host from a URI that has a host, first among the headers as RFC 9112 asks.
     *
     * @throws InvalidMessageException
     */
    private function withHostFrom(Headers $headers, UriInterface $uri): Headers
    {
        $host = $uri->getHost();

        if ($host === '') {
            return $headers;
        }

        $port = $uri->getPort();

        return $headers->withFirst('Host', $port === null ? $host : $host . ':' . $port);
    }

    /**
     * @throws InvalidRequestException
     */
    private function validateMethod(string $method): string
    {
        if (!preg_match(Headers::TOKEN_PATTERN, $method)) {
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
