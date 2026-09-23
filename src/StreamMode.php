<?php

declare(strict_types=1);

namespace Dirthara\Http;

use Dirthara\Http\Exception\InvalidStreamException;

use function substr;
use function in_array;
use function preg_match;
use function str_contains;
use function str_starts_with;

/**
 * @internal
 */
final readonly class StreamMode
{
    private const string PATTERN = '/^[rwaxc][bte]*\+?[bte]*$/D';

    private function __construct(
        public string $mode,
    ) {}

    /**
     * @throws InvalidStreamException
     */
    public static function fromString(string $mode): self
    {
        if (!preg_match(self::PATTERN, $mode)) {
            throw InvalidStreamException::invalidMode($mode);
        }

        return new self($mode);
    }

    public static function fromMetadata(string $mode): self
    {
        return new self($mode);
    }

    public function isReadable(): bool
    {
        return str_starts_with($this->mode, 'r') || str_contains($this->mode, '+');
    }

    public function isWritable(): bool
    {
        return (
            in_array(substr($this->mode, offset: 0, length: 1), ['w', 'a', 'x', 'c'], strict: true)
            || str_contains($this->mode, '+')
        );
    }
}
