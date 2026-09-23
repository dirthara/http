<?php

declare(strict_types=1);

namespace Dirthara\Http\Exception;

use Throwable;
use InvalidArgumentException;

use function sprintf;
use function get_debug_type;

final class InvalidMessageException extends InvalidArgumentException implements HttpException
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

    public static function invalidProtocolVersion(string $version): self
    {
        return new self(
            message: sprintf('The HTTP protocol version "%s" is invalid.', self::printable($version)),
            context: [
                'version' => $version,
            ],
        );
    }

    public static function headersNotKeyedByName(): self
    {
        return new self(message: 'HTTP headers must be keyed by name, a list was given.');
    }

    public static function invalidHeaderName(string $name): self
    {
        return new self(message: sprintf('The HTTP header name "%s" is invalid.', self::printable($name)), context: [
            'name' => $name,
        ]);
    }

    public static function invalidHeaderValue(string $name): self
    {
        return new self(message: sprintf('The HTTP header "%s" contains an invalid value.', $name), context: [
            'name' => $name,
        ]);
    }

    public static function emptyHeaderValue(string $name): self
    {
        return new self(message: sprintf('The HTTP header "%s" must have at least one value.', $name), context: [
            'name' => $name,
        ]);
    }

    public static function invalidHeaderValueType(string $name, mixed $value): self
    {
        return new self(
            message: sprintf(
                'The HTTP header "%s" must contain only string values, "%s" given.',
                $name,
                get_debug_type($value),
            ),
            context: [
                'name' => $name,
                'type' => get_debug_type($value),
            ],
        );
    }
}
