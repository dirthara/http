<?php

declare(strict_types=1);

namespace Dirthara\Http\Exception;

use Throwable;
use RuntimeException;

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
        return new self(message: sprintf('Unable to open "%s" with mode "%s".', $filename, $mode), context: [
            'filename' => $filename,
            'mode' => $mode,
        ]);
    }
}
