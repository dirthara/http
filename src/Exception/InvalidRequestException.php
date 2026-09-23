<?php

declare(strict_types=1);

namespace Dirthara\Http\Exception;

use Throwable;
use InvalidArgumentException;

final class InvalidRequestException extends InvalidArgumentException implements HttpException
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

    public static function invalidMethod(string $method): self
    {
        return new self(message: sprintf('The HTTP request method "%s" is invalid.', $method), context: [
            'method' => $method,
        ]);
    }

    public static function invalidRequestTarget(): self
    {
        // The target stays out of the message and the context: its query string can carry tokens.
        return new self(
            message: 'The HTTP request target cannot be empty or contain whitespace or control characters.',
        );
    }
}
