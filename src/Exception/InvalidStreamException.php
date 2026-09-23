<?php

declare(strict_types=1);

namespace Dirthara\Http\Exception;

use Throwable;
use InvalidArgumentException;

use function sprintf;
use function get_debug_type;

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
        return new self(
            message: sprintf('Expected a PHP stream resource, "%s" given.', get_debug_type($resource)),
            context: [
                'type' => get_debug_type($resource),
            ],
        );
    }

    public static function invalidFilename(string $filename): self
    {
        return new self(message: 'A stream filename cannot be empty or contain null bytes.', context: [
            'filename' => $filename,
        ]);
    }

    public static function invalidMode(string $mode): self
    {
        return new self(message: sprintf('The stream mode "%s" is invalid.', self::printable($mode)), context: [
            'mode' => $mode,
        ]);
    }

    public static function invalidReadLength(int $length): self
    {
        return new self(message: sprintf('Stream read length cannot be negative, "%d" given.', $length), context: [
            'length' => $length,
        ]);
    }
}
