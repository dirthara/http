<?php

declare(strict_types=1);

namespace Dirthara\Http\Factory;

use Dirthara\Http\Response;
use Dirthara\Http\StatusCode;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Dirthara\Http\Exception\InvalidResponseException;

final readonly class ResponseFactory implements ResponseFactoryInterface
{
    public function __construct(
        private StreamFactoryInterface $streamFactory = new StreamFactory(),
    ) {}

    /**
     * @throws InvalidResponseException
     */
    public function createResponse(int|StatusCode $code = 200, string $reasonPhrase = ''): ResponseInterface
    {
        return new Response($code, $this->streamFactory->createStream(), reasonPhrase: $reasonPhrase);
    }
}
