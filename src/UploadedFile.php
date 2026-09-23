<?php

declare(strict_types=1);

namespace Dirthara\Http;

use RuntimeException;
use Psr\Http\Message\StreamInterface;
use Dirthara\Http\Exception\HttpException;
use Psr\Http\Message\UploadedFileInterface;
use Dirthara\Http\Exception\UploadedFileException;
use Dirthara\Http\Exception\InvalidUploadedFileException;

use function fopen;
use function fclose;
use function fwrite;
use function rename;
use function strlen;
use function substr;
use function unlink;
use function is_file;
use function str_contains;
use function is_uploaded_file;
use function move_uploaded_file;

final class UploadedFile implements UploadedFileInterface
{
    private readonly UploadError $error;

    private bool $moved = false;

    /**
     * @throws InvalidUploadedFileException
     */
    public function __construct(
        private ?StreamInterface $stream,
        private readonly ?int $size = null,
        int|UploadError $error = UploadError::Ok,
        private readonly ?string $clientFilename = null,
        private readonly ?string $clientMediaType = null,
        private readonly ?string $sourcePath = null,
    ) {
        $this->error = $error instanceof UploadError
            ? $error
            : UploadError::tryFrom($error) ?? throw InvalidUploadedFileException::invalidError($error);

        if ($size !== null && $size < 0) {
            throw InvalidUploadedFileException::invalidSize($size);
        }

        if ($sourcePath === '') {
            throw InvalidUploadedFileException::emptySourcePath();
        }

        if ($this->error->isOk() && $stream === null) {
            throw InvalidUploadedFileException::missingStream();
        }
    }

    /**
     * @throws UploadedFileException
     */
    public function getStream(): StreamInterface
    {
        $this->assertAvailable();

        return $this->stream ?? throw UploadedFileException::streamUnavailable();
    }

    /**
     * @throws InvalidUploadedFileException
     * @throws UploadedFileException
     */
    public function moveTo(string $targetPath): void
    {
        $this->validateTargetPath($targetPath);
        $this->assertAvailable();

        // is_uploaded_file() only returns true for a file the SAPI registered during a rfc1867 POST, which no test
        // process can arrange, so this branch and moveUploadedFile() stay outside the coverage report.
        // @codeCoverageIgnoreStart
        if ($this->sourcePath !== null && is_uploaded_file($this->sourcePath)) {
            $this->moveUploadedFile($this->sourcePath, $targetPath);

            return;
        }
        // @codeCoverageIgnoreEnd

        if (!$this->renameSourceTo($targetPath)) {
            $this->copyStreamTo($targetPath);
            $this->removeSource($targetPath);
        }

        $this->finishMove();
    }

    public function getSize(): ?int
    {
        return $this->size;
    }

    public function getError(): int
    {
        return $this->error->value;
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
     * @throws UploadedFileException
     */
    private function assertAvailable(): void
    {
        if ($this->moved) {
            throw UploadedFileException::alreadyMoved();
        }

        if (!$this->error->isOk()) {
            throw UploadedFileException::uploadFailed($this->error);
        }
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
     *
     * @codeCoverageIgnore Only reachable for a file the SAPI registered as an upload.
     */
    private function moveUploadedFile(string $sourcePath, string $targetPath): void
    {
        if (!move_uploaded_file($sourcePath, $targetPath)) {
            throw UploadedFileException::unableToMove($targetPath);
        }

        $this->finishMove();
    }

    private function renameSourceTo(string $targetPath): bool
    {
        // @mago-expect lint:no-error-control-operator -- a rename that fails falls through to the stream copy
        return $this->sourcePath !== null && is_file($this->sourcePath) && @rename($this->sourcePath, $targetPath);
    }

    /**
     * @throws UploadedFileException
     */
    private function removeSource(string $targetPath): void
    {
        // @mago-expect lint:no-error-control-operator -- the failure is reported as unableToRemoveSource
        if ($this->sourcePath !== null && is_file($this->sourcePath) && !@unlink($this->sourcePath)) {
            // @mago-expect lint:no-error-control-operator -- best-effort cleanup of an already failed move
            @unlink($targetPath);

            throw UploadedFileException::unableToRemoveSource($this->sourcePath);
        }
    }

    /**
     * @throws UploadedFileException
     */
    private function copyStreamTo(string $targetPath): void
    {
        $stream = $this->getStream();

        if (!$stream->isReadable()) {
            throw UploadedFileException::unableToReadStream($targetPath);
        }

        if ($stream->isSeekable()) {
            $stream->rewind();
        }

        // @mago-expect lint:no-error-control-operator -- the false return is reported as unableToOpenTarget
        $target = @fopen($targetPath, mode: 'wb');

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

                    throw UploadedFileException::unableToReadStream($targetPath);
                }

                $this->write($target, $chunk);
            }
        } catch (HttpException $exception) {
            $this->discardTarget($target, $targetPath);

            throw $exception->addContext(['targetPath' => $targetPath]);
        } catch (RuntimeException $exception) {
            $this->discardTarget($target, $targetPath);

            throw UploadedFileException::unableToReadStream($targetPath, $exception);
        }

        if (!fclose($target)) {
            // Closing a plain file the process just wrote does not fail: PHP writes through rather than buffering, so
            // a full device already failed the write above. Kept for the report it would give.
            // @codeCoverageIgnoreStart
            // @mago-expect lint:no-error-control-operator -- best-effort cleanup of a target that will not close
            @unlink($targetPath);

            throw UploadedFileException::unableToCloseTarget($targetPath);

            // @codeCoverageIgnoreEnd
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
            // @mago-expect lint:no-error-control-operator -- the false return is reported as unableToWrite
            $written = @fwrite($target, substr($contents, $offset));

            if ($written === false || $written === 0) {
                throw UploadedFileException::unableToWrite();
            }

            $offset += $written;
        }
    }

    /**
     * @param resource $target
     */
    private function discardTarget($target, string $targetPath): void
    {
        fclose($target);

        // @mago-expect lint:no-error-control-operator -- best-effort cleanup while unwinding
        @unlink($targetPath);
    }

    private function finishMove(): void
    {
        $this->stream?->close();

        $this->stream = null;
        $this->moved = true;
    }
}
