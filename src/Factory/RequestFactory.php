<?php

declare(strict_types=1);

namespace Dirthara\Http\Factory;

use Dirthara\Http\Request;
use Psr\Http\Message\UriInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\UriFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Dirthara\Http\Exception\InvalidUriException;
use Dirthara\Http\Exception\InvalidMessageException;
use Dirthara\Http\Exception\InvalidRequestException;

final readonly class RequestFactory implements RequestFactoryInterface
{
    public function __construct(
        private UriFactoryInterface $uriFactory = new UriFactory(),
        private StreamFactoryInterface $streamFactory = new StreamFactory(),
    ) {}

    /**
     * @param UriInterface|string $uri
     *
     * @throws InvalidMessageException
     * @throws InvalidRequestException
     * @throws InvalidUriException
     */
    public function createRequest(string $method, $uri): RequestInterface
    {
        return new Request(
            $method,
            $uri instanceof UriInterface ? $uri : $this->uriFactory->createUri($uri),
            $this->streamFactory->createStream(),
        );
    }
}
