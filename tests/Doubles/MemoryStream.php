<?php

declare(strict_types=1);

namespace Dirthara\Http\Tests\Doubles;

use Throwable;
use Stringable;
use RuntimeException;
use Psr\Http\Message\StreamInterface;

use function strlen;
use function substr;

/**
 * An in-memory stream with knobs for the failures UploadedFile has to survive.
 */
final class MemoryStream implements StreamInterface, Stringable
{
    public bool $closed = false;

    public bool $rewound = false;

    /**
     * Makes read() hand back nothing while eof() still reports more to come.
     */
    public bool $stalls = false;

    /**
     * Makes read() hand back nothing and only then report the end of the stream.
     */
    public bool $emptiesAtEof = false;

    public ?Throwable $readFailure = null;

    private int $position = 0;

    public function __construct(
        private string $contents = '',
        private readonly bool $seekable = true,
    ) {}

    public function __toString(): string
    {
        return $this->contents;
    }

    public function close(): void
    {
        $this->closed = true;
    }

    public function detach()
    {
        $this->closed = true;

        return null;
    }

    public function getSize(): ?int
    {
        return strlen($this->contents);
    }

    public function tell(): int
    {
        return $this->position;
    }

    public function eof(): bool
    {
        return $this->position >= strlen($this->contents);
    }

    public function isSeekable(): bool
    {
        return $this->seekable;
    }

    public function seek(int $offset, int $whence = SEEK_SET): void
    {
        if (!$this->seekable) {
            throw new RuntimeException('The stream is not seekable.');
        }

        $this->position = $offset;
    }

    public function rewind(): void
    {
        $this->rewound = true;

        $this->seek(0);
    }

    public function isWritable(): bool
    {
        return true;
    }

    public function write(string $string): int
    {
        $this->contents .= $string;

        return strlen($string);
    }

    public function isReadable(): bool
    {
        return true;
    }

    public function read(int $length): string
    {
        if ($this->readFailure !== null) {
            throw $this->readFailure;
        }

        if ($this->stalls) {
            return '';
        }

        if ($this->emptiesAtEof) {
            $this->position = strlen($this->contents);

            return '';
        }

        $chunk = substr($this->contents, $this->position, $length);
        $this->position += strlen($chunk);

        return $chunk;
    }

    public function getContents(): string
    {
        $remaining = substr($this->contents, $this->position);
        $this->position = strlen($this->contents);

        return $remaining;
    }

    public function getMetadata(?string $key = null): mixed
    {
        return $key === null ? [] : null;
    }
}
