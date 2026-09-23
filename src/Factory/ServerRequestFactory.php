<?php

declare(strict_types=1);

namespace Dirthara\Http\Factory;

use Dirthara\Http\ServerRequest;
use Psr\Http\Message\UriInterface;
use Psr\Http\Message\UriFactoryInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Dirthara\Http\Exception\InvalidUriException;
use Psr\Http\Message\ServerRequestFactoryInterface;
use Dirthara\Http\Exception\InvalidMessageException;
use Dirthara\Http\Exception\InvalidRequestException;

final readonly class ServerRequestFactory implements ServerRequestFactoryInterface
{
    public function __construct(
        private UriFactoryInterface $uriFactory = new UriFactory(),
        private StreamFactoryInterface $streamFactory = new StreamFactory(),
    ) {}

    /**
     * @param UriInterface|string $uri
     * @param array<array-key, mixed> $serverParams
     *
     * @throws InvalidMessageException
     * @throws InvalidRequestException
     * @throws InvalidUriException
     */
    public function createServerRequest(string $method, $uri, array $serverParams = []): ServerRequestInterface
    {
        return new ServerRequest(
            $method,
            $uri instanceof UriInterface ? $uri : $this->uriFactory->createUri($uri),
            $this->streamFactory->createStream(),
            serverParams: $serverParams,
        );
    }
}
