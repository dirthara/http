<?php

declare(strict_types=1);

namespace Dirthara\Http;

use Stringable;
use RuntimeException;
use Psr\Http\Message\StreamInterface;
use Dirthara\Http\Exception\StreamException;
use Dirthara\Http\Exception\InvalidStreamException;

use function min;
use function feof;
use function fread;
use function fseek;
use function fstat;
use function ftell;
use function fclose;
use function fwrite;
use function is_resource;
use function get_resource_type;
use function stream_get_contents;

final class Stream implements StreamInterface, Stringable
{
    private const int MAX_READ_LENGTH = 1024 * 1024;

    /**
     * @var resource|null
     */
    private $resource;

    private readonly StreamMode $mode;

    private readonly bool $seekable;

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

        $metadata = StreamMetadata::fromResource($resource);

        $this->mode = $metadata->mode;
        $this->seekable = $metadata->seekable;
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
        return $this->resource !== null && $this->mode->isWritable();
    }

    /**
     * @throws StreamException
     */
    public function write(string $string): int
    {
        $resource = $this->getResource();

        if (!$this->mode->isWritable()) {
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
        return $this->resource !== null && $this->mode->isReadable();
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

        if (!$this->mode->isReadable()) {
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

        if (!$this->mode->isReadable()) {
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

        $metadata = StreamMetadata::fromResource($this->resource);

        // @mago-expect analysis:mixed-return-statement -- a wrapper's metadata has no single type
        return $key === null ? $metadata->all() : $metadata->get($key);
    }

    /**
     * @throws StreamException
     *
     * @return resource
     */
    private function getResource()
    {
        if ($this->resource === null) {
            throw StreamException::detached();
        }

        return $this->resource;
    }
}
