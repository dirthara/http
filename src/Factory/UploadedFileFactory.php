<?php

declare(strict_types=1);

namespace Dirthara\Http\Factory;

use Dirthara\Http\UploadedFile;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UploadedFileInterface;
use Psr\Http\Message\UploadedFileFactoryInterface;
use Dirthara\Http\Exception\InvalidUploadedFileException;

final class UploadedFileFactory implements UploadedFileFactoryInterface
{
    /**
     * @throws InvalidUploadedFileException
     */
    public function createUploadedFile(
        StreamInterface $stream,
        ?int $size = null,
        int $error = UPLOAD_ERR_OK,
        ?string $clientFilename = null,
        ?string $clientMediaType = null,
    ): UploadedFileInterface {
        if (!$stream->isReadable()) {
            throw InvalidUploadedFileException::streamNotReadable();
        }

        return new UploadedFile($stream, $size ?? $stream->getSize(), $error, $clientFilename, $clientMediaType);
    }
}
