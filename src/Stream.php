<?php

declare(strict_types=1);

namespace Dirthara\Http;

use Throwable;
use Psr\Http\Message\StreamInterface;
use Dirthara\Http\Exception\InvalidStreamException;

final class Stream implements StreamInterface
{
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
        $mode = $metadata['mode'] ?? '';

        $this->readable = $this->determineReadable($mode);
        $this->writable = $this->determineWritable($mode);
        $this->seekable = (bool) ($metadata['seekable'] ?? false);
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
        } catch (Throwable) {
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

        return isset($statistics['size']) ? (int) $statistics['size'] : null;
    }

    /**
     * @throws InvalidStreamException
     */
    public function tell(): int
    {
        $resource = $this->getResource();

        $position = ftell($resource);

        if ($position === false) {
            throw InvalidStreamException::unableToTell();
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
     * @throws InvalidStreamException
     */
    public function seek(int $offset, int $whence = SEEK_SET): void
    {
        $resource = $this->getResource();

        if (!$this->seekable) {
            throw InvalidStreamException::notSeekable();
        }

        if (fseek($resource, $offset, $whence) !== 0) {
            throw InvalidStreamException::unableToSeek($offset, $whence);
        }
    }

    /**
     * @throws InvalidStreamException
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
     * @throws InvalidStreamException
     */
    public function write(string $string): int
    {
        $resource = $this->getResource();

        if (!$this->writable) {
            throw InvalidStreamException::notWritable();
        }

        if ($string === '') {
            return 0;
        }

        $written = fwrite($resource, $string);

        if ($written === false) {
            throw InvalidStreamException::notWritable();
        }

        return $written;
    }

    public function isReadable(): bool
    {
        return $this->resource !== null && $this->readable;
    }

    /**
     * @throws InvalidStreamException
     */
    public function read(int $length): string
    {
        if ($length < 0) {
            throw InvalidStreamException::invalidReadLength($length);
        }

        $resource = $this->getResource();

        if (!$this->readable) {
            throw InvalidStreamException::notReadable();
        }

        if ($length === 0) {
            return '';
        }

        $contents = fread($resource, $length);

        if ($contents === false) {
            throw InvalidStreamException::notReadable($length);
        }

        return $contents;
    }

    /**
     * @throws InvalidStreamException
     */
    public function getContents(): string
    {
        $resource = $this->getResource();

        if (!$this->readable) {
            throw InvalidStreamException::notReadable();
        }

        $contents = stream_get_contents($resource);

        if ($contents === false) {
            throw InvalidStreamException::notReadable();
        }

        return $contents;
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

        return $metadata[$key] ?? null;
    }

    /**
     * @return resource
     *
     * @throws InvalidStreamException
     */
    private function getResource()
    {
        if ($this->resource === null) {
            throw InvalidStreamException::detached();
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

        return in_array($mode[0], ['w', 'a', 'x', 'c'], true) || str_contains($mode, '+');
    }
}
