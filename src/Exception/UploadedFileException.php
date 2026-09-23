<?php

declare(strict_types=1);

namespace Dirthara\Http\Exception;

use Throwable;
use RuntimeException;

use function sprintf;
use function is_string;

final class UploadedFileException extends RuntimeException implements HttpException
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

    public static function alreadyMoved(): self
    {
        return new self(message: 'The uploaded file has already been moved.');
    }

    public static function uploadFailed(int $error): self
    {
        return new self(message: sprintf('The upload failed with error code "%d".', $error), context: [
            'error' => $error,
        ]);
    }

    public static function streamUnavailable(): self
    {
        return new self(message: 'No stream is available for the uploaded file.');
    }

    public static function unableToMove(string $targetPath): self
    {
        return new self(
            message: sprintf('Unable to move uploaded file to "%s".', self::printable($targetPath)),
            context: [
                'targetPath' => $targetPath,
            ],
        );
    }

    public static function unableToRemoveSource(string $sourcePath): self
    {
        return new self(
            message: sprintf('Unable to remove uploaded file source "%s".', self::printable($sourcePath)),
            context: [
                'sourcePath' => $sourcePath,
            ],
        );
    }

    public static function unableToOpenTarget(string $targetPath): self
    {
        return new self(
            message: sprintf('Unable to open target path "%s" for writing.', self::printable($targetPath)),
            context: [
                'targetPath' => $targetPath,
            ],
        );
    }

    public static function unableToReadStream(string $targetPath, ?Throwable $previous = null): self
    {
        // A PDOException reports a string SQLSTATE as its code, which an int code cannot hold.
        $code = $previous?->getCode();

        return new self(message: 'Unable to read from uploaded file stream.', previous: $previous, context: [
            'targetPath' => $targetPath,
            ...(is_string($code) ? ['sqlState' => $code] : []),
        ]);
    }

    public static function unableToWrite(): self
    {
        return new self(message: 'Unable to write uploaded file.');
    }

    public static function unableToCloseTarget(string $targetPath): self
    {
        return new self(message: sprintf('Unable to close target file "%s".', self::printable($targetPath)), context: [
            'targetPath' => $targetPath,
        ]);
    }
}
