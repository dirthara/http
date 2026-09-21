<?php

declare(strict_types=1);

namespace Dirthara\Http\Exception;

use Throwable;
use InvalidArgumentException;

final class InvalidUploadedFileException extends InvalidArgumentException implements HttpException
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

    public static function invalidError(int $error): self
    {
        return new self(message: sprintf('Invalid upload error code "%d".', $error), context: ['error' => $error]);
    }

    public static function invalidSize(int $size): self
    {
        return new self(message: sprintf('Uploaded file size cannot be negative, "%d" given.', $size), context: [
            'size' => $size,
        ]);
    }

    public static function emptySourcePath(): self
    {
        return new self(message: 'Uploaded file source path cannot be empty.');
    }

    public static function missingStream(): self
    {
        return new self(message: 'A successfully uploaded file must have a stream.');
    }

    public static function emptyTargetPath(): self
    {
        return new self(message: 'Target path cannot be empty.');
    }

    public static function targetPathContainsNullByte(): self
    {
        return new self(message: 'Target path cannot contain null bytes.');
    }
}
