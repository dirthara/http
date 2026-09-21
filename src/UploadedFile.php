<?php

declare(strict_types=1);

namespace Dirthara\Http;

use Throwable;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UploadedFileInterface;
use Dirthara\Http\Exception\UploadedFileException;
use Dirthara\Http\Exception\InvalidUploadedFileException;

final class UploadedFile implements UploadedFileInterface
{
    /**
     * @var list<int>
     */
    private const array VALID_ERRORS = [
        UPLOAD_ERR_OK,
        UPLOAD_ERR_INI_SIZE,
        UPLOAD_ERR_FORM_SIZE,
        UPLOAD_ERR_PARTIAL,
        UPLOAD_ERR_NO_FILE,
        UPLOAD_ERR_NO_TMP_DIR,
        UPLOAD_ERR_CANT_WRITE,
        UPLOAD_ERR_EXTENSION,
    ];

    private bool $moved = false;

    /**
     * @throws InvalidUploadedFileException
     */
    public function __construct(
        private ?StreamInterface $stream,
        private readonly ?int $size = null,
        private readonly int $error = UPLOAD_ERR_OK,
        private readonly ?string $clientFilename = null,
        private readonly ?string $clientMediaType = null,
        private readonly ?string $sourcePath = null,
    ) {
        if (!in_array($error, self::VALID_ERRORS, true)) {
            throw InvalidUploadedFileException::invalidError($error);
        }

        if ($size !== null && $size < 0) {
            throw InvalidUploadedFileException::invalidSize($size);
        }

        if ($sourcePath === '') {
            throw InvalidUploadedFileException::emptySourcePath();
        }

        if ($error === UPLOAD_ERR_OK && $stream === null) {
            throw InvalidUploadedFileException::missingStream();
        }
    }

    /**
     * @throws UploadedFileException
     */
    public function getStream(): StreamInterface
    {
        if ($this->moved) {
            throw UploadedFileException::alreadyMoved();
        }

        if ($this->error !== UPLOAD_ERR_OK) {
            throw UploadedFileException::uploadFailed($this->error);
        }

        if ($this->stream === null) {
            throw UploadedFileException::streamUnavailable();
        }

        return $this->stream;
    }

    /**
     * @throws InvalidUploadedFileException
     * @throws UploadedFileException
     */
    public function moveTo(string $targetPath): void
    {
        $this->validateTargetPath($targetPath);

        if ($this->moved) {
            throw UploadedFileException::alreadyMoved();
        }

        if ($this->error !== UPLOAD_ERR_OK) {
            throw UploadedFileException::uploadFailed($this->error);
        }

        if ($this->sourcePath !== null && is_uploaded_file($this->sourcePath)) {
            $this->moveUploadedFile($targetPath);

            return;
        }

        if ($this->sourcePath !== null && is_file($this->sourcePath) && @rename($this->sourcePath, $targetPath)) {
            $this->finishMove();

            return;
        }

        $this->copyStreamTo($targetPath);

        if ($this->sourcePath !== null && is_file($this->sourcePath) && !@unlink($this->sourcePath)) {
            @unlink($targetPath);

            throw UploadedFileException::unableToRemoveSource($this->sourcePath);
        }

        $this->finishMove();
    }

    public function getSize(): ?int
    {
        return $this->size;
    }

    public function getError(): int
    {
        return $this->error;
    }

    public function getClientFilename(): ?string
    {
        return $this->clientFilename;
    }

    public function getClientMediaType(): ?string
    {
        return $this->clientMediaType;
    }

    /**
     * @throws InvalidUploadedFileException
     */
    private function validateTargetPath(string $targetPath): void
    {
        if ($targetPath === '') {
            throw InvalidUploadedFileException::emptyTargetPath();
        }

        if (str_contains($targetPath, "\0")) {
            throw InvalidUploadedFileException::targetPathContainsNullByte();
        }
    }

    /**
     * @throws UploadedFileException
     */
    private function moveUploadedFile(string $targetPath): void
    {
        if (!move_uploaded_file($this->sourcePath, $targetPath)) {
            throw UploadedFileException::unableToMove($targetPath);
        }

        $this->finishMove();
    }

    /**
     * @throws UploadedFileException
     */
    private function copyStreamTo(string $targetPath): void
    {
        $stream = $this->getStream();

        if ($stream->isSeekable()) {
            $stream->rewind();
        }

        $target = @fopen($targetPath, 'wb');

        if ($target === false) {
            throw UploadedFileException::unableToOpenTarget($targetPath);
        }

        try {
            while (!$stream->eof()) {
                $chunk = $stream->read(1024 * 1024);

                if ($chunk === '') {
                    if ($stream->eof()) {
                        break;
                    }

                    throw UploadedFileException::unableToReadStream();
                }

                $this->write($target, $chunk);
            }
        } catch (Throwable $exception) {
            fclose($target);
            @unlink($targetPath);

            throw UploadedFileException::fromThrowable($exception);
        }

        if (!fclose($target)) {
            @unlink($targetPath);

            throw UploadedFileException::unableToCloseTarget($targetPath);
        }
    }

    /**
     * @param resource $target
     *
     * @throws UploadedFileException
     */
    private function write($target, string $contents): void
    {
        $length = strlen($contents);
        $offset = 0;

        while ($offset < $length) {
            $written = fwrite($target, substr($contents, $offset));

            if ($written === false || $written === 0) {
                throw UploadedFileException::unableToWrite();
            }

            $offset += $written;
        }
    }

    private function finishMove(): void
    {
        $this->stream?->close();

        $this->stream = null;
        $this->moved = true;
    }
}
