<?php

declare(strict_types=1);

namespace Dirthara\Http\Factory;

use Dirthara\Http\Uri;
use Psr\Http\Message\UriInterface;
use Psr\Http\Message\UriFactoryInterface;
use Dirthara\Http\Exception\InvalidUriException;

final class UriFactory implements UriFactoryInterface
{
    /**
     * @throws InvalidUriException
     */
    public function createUri(string $uri = ''): UriInterface
    {
        return new Uri($uri);
    }
}
