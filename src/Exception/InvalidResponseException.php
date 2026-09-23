<?php

declare(strict_types=1);

namespace Dirthara\Http\Exception;

use Throwable;
use InvalidArgumentException;

use function sprintf;

final class InvalidResponseException extends InvalidArgumentException implements HttpException
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

    public static function invalidStatusCode(int $statusCode): self
    {
        return new self(
            message: sprintf('The HTTP status code "%d" is invalid, it must be between 100 and 599.', $statusCode),
            context: [
                'statusCode' => $statusCode,
            ],
        );
    }

    public static function invalidReasonPhrase(string $reasonPhrase): self
    {
        return new self(message: 'The HTTP reason phrase cannot contain line breaks or control characters.', context: [
            'reasonPhrase' => $reasonPhrase,
        ]);
    }
}
