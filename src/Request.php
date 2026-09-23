<?php

declare(strict_types=1);

namespace Dirthara\Http;

use Psr\Http\Message\UriInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\RequestInterface;
use Dirthara\Http\Exception\InvalidMessageException;
use Dirthara\Http\Exception\InvalidRequestException;

final readonly class Request implements RequestInterface
{
    use ImplementsRequest;

    /**
     * @param array<array-key, string|array<array-key, string>> $headers
     *
     * @throws InvalidMessageException
     * @throws InvalidRequestException
     */
    public function __construct(
        string $method,
        UriInterface $uri,
        StreamInterface $body,
        array $headers = [],
        string $protocolVersion = '1.1',
    ) {
        $this->method = $this->validateMethod($method);
        $this->uri = $this->validateUri($uri);
        $this->requestTarget = null;
        $this->body = $body;
        $this->protocolVersion = $this->validateProtocolVersion($protocolVersion);
        $this->headers = $this->requestHeaders($headers, $uri);
    }
}
