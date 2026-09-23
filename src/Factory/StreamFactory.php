<?php

declare(strict_types=1);

namespace Dirthara\Http\Factory;

use Dirthara\Http\Stream;
use Psr\Http\Message\StreamInterface;
use Dirthara\Http\Exception\StreamException;
use Psr\Http\Message\StreamFactoryInterface;
use Dirthara\Http\Exception\InvalidStreamException;

use function fopen;
use function is_dir;
use function preg_match;
use function str_contains;

final readonly class StreamFactory implements StreamFactoryInterface
{
    private const string MODE_PATTERN = '/^[rwaxc][bte]*\+?[bte]*$/D';

    /**
     * @throws InvalidStreamException
     * @throws StreamException
     */
    public function createStream(string $content = ''): StreamInterface
    {
        $stream = $this->createStreamFromFile('php://temp', 'r+b');
        $stream->write($content);
        $stream->rewind();

        return $stream;
    }

    /**
     * @throws InvalidStreamException
     * @throws StreamException
     */
    public function createStreamFromFile(string $filename, string $mode = 'r'): StreamInterface
    {
        if (!preg_match(self::MODE_PATTERN, $mode)) {
            throw InvalidStreamException::invalidMode($mode);
        }

        if ($filename === '' || str_contains($filename, "\0")) {
            throw InvalidStreamException::invalidFilename($filename);
        }

        // fopen() opens a directory for reading on some platforms, and reading it then fails with a notice.
        if (is_dir($filename)) {
            throw StreamException::unableToOpen($filename, $mode);
        }

        // @mago-expect lint:no-error-control-operator -- the false return is reported as unableToOpen
        $resource = @fopen($filename, $mode);

        if ($resource === false) {
            throw StreamException::unableToOpen($filename, $mode);
        }

        return new Stream($resource);
    }

    /**
     * @param resource $resource
     *
     * @throws InvalidStreamException
     */
    public function createStreamFromResource($resource): StreamInterface
    {
        return new Stream($resource);
    }
}
