<?php

declare(strict_types=1);

namespace Dirthara\Http\Exception;

use Throwable;
use InvalidArgumentException;

final class InvalidStreamException extends InvalidArgumentException implements HttpException
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

    public static function invalidResource(mixed $resource): self
    {
        return new self(sprintf('Expected a PHP stream resource, "%s" given.', get_debug_type($resource)), context: [
            'type' => get_debug_type($resource),
        ]);
    }

    public static function invalidFilename(string $filename): self
    {
        return new self(message: 'A stream filename cannot be empty or contain null bytes.', context: [
            'filename' => $filename,
        ]);
    }

    public static function invalidMode(string $mode): self
    {
        return new self(message: sprintf('The stream mode "%s" is invalid.', $mode), context: ['mode' => $mode]);
    }

    public static function invalidReadLength(int $length): self
    {
        return new self(sprintf('Stream read length cannot be negative, "%d" given.', $length), context: [
            'length' => $length,
        ]);
    }

    public static function unableToTell(): self
    {
        return new self('Unable to tell stream position.');
    }

    public static function notSeekable(): self
    {
        return new self('Stream is not seekable.');
    }

    public static function unableToSeek(int $offset, int $whence = SEEK_SET): self
    {
        return new self(sprintf('Unable to seek to stream position %d with whence %d.', $offset, $whence), context: [
            'offset' => $offset,
            'whence' => $whence,
        ]);
    }

    public static function notWritable(): self
    {
        return new self('Unable to write to stream.');
    }

    public static function notReadable(?int $length = null): self
    {
        return new self(sprintf(
            'Unable to read from stream%s.',
            $length !== null ? sprintf(' with length %d', $length) : '',
        ));
    }

    public static function detached(): self
    {
        return new self('Stream has been detached.');
    }
}
