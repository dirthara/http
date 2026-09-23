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

    public static function invalidUploadedFile(string $path, mixed $file): self
    {
        return new self(
            message: sprintf(
                'The uploaded file "%s" must be an UploadedFileInterface or an array of them, "%s" given.',
                $path,
                get_debug_type($file),
            ),
            context: [
                'path' => $path,
                'type' => get_debug_type($file),
            ],
        );
    }

    public static function invalidParsedBody(mixed $data): self
    {
        // Only the type is kept: a parsed body can carry passwords and other form input.
        return new self(
            message: sprintf(
                'The parsed body must be null, an array, or an object, "%s" given.',
                get_debug_type($data),
            ),
            context: [
                'type' => get_debug_type($data),
            ],
        );
    }
}
