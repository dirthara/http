<?php

declare(strict_types=1);

namespace Dirthara\Http;

use RuntimeException;
use Psr\Http\Message\StreamInterface;
use Dirthara\Http\Exception\StreamException;
use Dirthara\Http\Exception\InvalidStreamException;

final class Stream implements StreamInterface
{
    private const int MAX_READ_LENGTH = 1024 * 1024;

    /**
     * @var resource|null
     */
    private $resource;

    private bool $readable;

    private bool $writable;

    private bool $seekable;

    /**
     * @param resource $resource
     *
     * @throws InvalidStreamException
     */
    public function __construct(mixed $resource)
    {
        if (!is_resource($resource) || get_resource_type($resource) !== 'stream') {
            throw InvalidStreamException::invalidResource($resource);
        }

        $this->resource = $resource;

        $metadata = stream_get_meta_data($resource);
        $mode = $metadata['mode'];

        $this->readable = $this->determineReadable($mode);
        $this->writable = $this->determineWritable($mode);
        $this->seekable = $metadata['seekable'];
    }

    public function __toString(): string
    {
        if ($this->resource === null) {
            return '';
        }

        try {
            if ($this->isSeekable()) {
                $this->rewind();
            }

            return $this->getContents();
        } catch (RuntimeException) {
            return '';
        }
    }

    public function close(): void
    {
        $resource = $this->detach();

        if (is_resource($resource)) {
            fclose($resource);
        }
    }

    /**
     * @return resource|null
     */
    public function detach()
    {
        $resource = $this->resource;

        $this->resource = null;
        $this->readable = false;
        $this->writable = false;
        $this->seekable = false;

        return $resource;
    }

    public function getSize(): ?int
    {
        if ($this->resource === null) {
            return null;
        }

        $statistics = fstat($this->resource);

        if ($statistics === false) {
            return null;
        }

        return $statistics['size'];
    }

    /**
     * @throws StreamException
     */
    public function tell(): int
    {
        $resource = $this->getResource();

        $position = ftell($resource);

        if ($position === false) {
            throw StreamException::unableToTell();
        }

        return $position;
    }

    public function eof(): bool
    {
        if ($this->resource === null) {
            return true;
        }

        return feof($this->resource);
    }

    public function isSeekable(): bool
    {
        return $this->resource !== null && $this->seekable;
    }

    /**
     * @throws StreamException
     */
    public function seek(int $offset, int $whence = SEEK_SET): void
    {
        $resource = $this->getResource();

        if (!$this->seekable) {
            throw StreamException::notSeekable();
        }

        if (fseek($resource, $offset, $whence) !== 0) {
            throw StreamException::unableToSeek($offset, $whence);
        }
    }

    /**
     * @throws StreamException
     */
    public function rewind(): void
    {
        $this->seek(0);
    }

    public function isWritable(): bool
    {
        return $this->resource !== null && $this->writable;
    }

    /**
     * @throws StreamException
     */
    public function write(string $string): int
    {
        $resource = $this->getResource();

        if (!$this->writable) {
            throw StreamException::notWritable();
        }

        if ($string === '') {
            return 0;
        }

        $written = fwrite($resource, $string);

        if ($written === false) {
            throw StreamException::notWritable();
        }

        return $written;
    }

    public function isReadable(): bool
    {
        return $this->resource !== null && $this->readable;
    }

    /**
     * @throws InvalidStreamException
     * @throws StreamException
     */
    public function read(int $length): string
    {
        if ($length < 0) {
            throw InvalidStreamException::invalidReadLength($length);
        }

        $resource = $this->getResource();

        if (!$this->readable) {
            throw StreamException::notReadable();
        }

        if ($length === 0) {
            return '';
        }

        // fread() allocates the whole length up front, so an untrusted length would exhaust memory.
        $contents = fread($resource, min($length, self::MAX_READ_LENGTH));

        if ($contents === false) {
            throw StreamException::notReadable($length);
        }

        return $contents;
    }

    /**
     * @throws StreamException
     */
    public function getContents(): string
    {
        $resource = $this->getResource();

        if (!$this->readable) {
            throw StreamException::notReadable();
        }

        $contents = stream_get_contents($resource);

        return $contents === false ? throw StreamException::notReadable() : $contents;
    }

    /**
     * @return array<string, mixed>|mixed|null
     */
    public function getMetadata(?string $key = null): mixed
    {
        if ($this->resource === null) {
            return $key === null ? [] : null;
        }

        $metadata = stream_get_meta_data($this->resource);

        if ($key === null) {
            return $metadata;
        }

        // @mago-expect analysis:mixed-return-statement -- a wrapper's metadata has no single type
        return $metadata[$key] ?? null;
    }

    /**
     * @return resource
     *
     * @throws StreamException
     */
    private function getResource()
    {
        if ($this->resource === null) {
            throw StreamException::detached();
        }

        return $this->resource;
    }

    private function determineReadable(string $mode): bool
    {
        if ($mode === '') {
            return false;
        }

        return $mode[0] === 'r' || str_contains($mode, '+');
    }

    private function determineWritable(string $mode): bool
    {
        if ($mode === '') {
            return false;
        }

        return in_array($mode[0], ['w', 'a', 'x', 'c'], strict: true) || str_contains($mode, '+');
    }
}
