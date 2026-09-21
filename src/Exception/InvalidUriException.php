<?php

declare(strict_types=1);

namespace Dirthara\Http\Exception;

use Throwable;
use InvalidArgumentException;

final class InvalidUriException extends InvalidArgumentException implements HttpException
{
    use HasExceptionContext;

    /**
     * @param array<string, mixed> $context
     */
    public function __construct(string $message = '', int $code = 0, ?Throwable $previous = null, array $context = [])
    {
        parent::__construct($message, $code, $previous);

        $this->context = $context;
    }

    public static function forInvalidUri(string $uri, ?Throwable $previous = null): self
    {
        return new self(message: sprintf('The given URI "%s" is invalid.', $uri), previous: $previous, context: [
            'uri' => $uri,
        ]);
    }

    public static function invalidScheme(string $scheme): self
    {
        return new self(message: sprintf('The given scheme "%s" is invalid.', $scheme), context: ['scheme' => $scheme]);
    }

    public static function invalidIpLiteralHost(string $literal): self
    {
        return new self(message: sprintf('The given IP literal host "%s" is invalid.', $literal), context: [
            'literal' => $literal,
        ]);
    }

    public static function invalidHost(string $host): self
    {
        return new self(message: sprintf('The given host "%s" is invalid.', $host), context: ['host' => $host]);
    }

    public static function invalidPort(int $port): self
    {
        return new self(message: sprintf('The given port "%d" is invalid.', $port), context: ['port' => $port]);
    }
}
