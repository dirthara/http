<?php

declare(strict_types=1);

namespace Dirthara\Http\Exception;

use Throwable;
use RuntimeException;

use function sprintf;

use const SEEK_SET;

final class StreamException extends RuntimeException implements HttpException
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

    public static function unableToOpen(string $filename, string $mode): self
    {
        return new self(
            message: sprintf('Unable to open "%s" with mode "%s".', self::printable($filename), self::printable($mode)),
            context: [
                'filename' => $filename,
                'mode' => $mode,
            ],
        );
    }

    public static function unableToTell(): self
    {
        return new self(message: 'Unable to tell stream position.');
    }

    public static function notSeekable(): self
    {
        return new self(message: 'Stream is not seekable.');
    }

    public static function unableToSeek(int $offset, int $whence = SEEK_SET): self
    {
        return new self(
            message: sprintf('Unable to seek to stream position %d with whence %d.', $offset, $whence),
            context: [
                'offset' => $offset,
                'whence' => $whence,
            ],
        );
    }

    public static function notWritable(): self
    {
        return new self(message: 'Unable to write to stream.');
    }

    public static function notReadable(?int $length = null): self
    {
        return new self(message: sprintf(
            'Unable to read from stream%s.',
            $length !== null ? sprintf(' with length %d', $length) : '',
        ));
    }

    public static function detached(): self
    {
        return new self(message: 'Stream has been detached.');
    }
}
