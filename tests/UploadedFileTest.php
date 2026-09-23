<?php

declare(strict_types=1);

namespace Dirthara\Http\Tests;

use RuntimeException;
use Dirthara\Http\UploadedFile;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Dirthara\Http\Tests\Doubles\MemoryStream;
use Dirthara\Http\Exception\UploadedFileException;
use Dirthara\Http\Exception\InvalidUploadedFileException;

final class UploadedFileTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/dirthara-http-' . bin2hex(random_bytes(8));

        mkdir($this->directory);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->directory);
    }

    #[Test]
    public function it_reports_what_the_upload_said_about_the_file(): void
    {
        $stream = new MemoryStream('contents');
        $file = new UploadedFile($stream, 8, UPLOAD_ERR_OK, 'avatar.png', 'image/png');

        self::assertSame($stream, $file->getStream());
        self::assertSame(8, $file->getSize());
        self::assertSame(UPLOAD_ERR_OK, $file->getError());
        self::assertSame('avatar.png', $file->getClientFilename());
        self::assertSame('image/png', $file->getClientMediaType());
    }

    #[Test]
    public function it_knows_nothing_the_upload_did_not_say(): void
    {
        $file = new UploadedFile(new MemoryStream());

        self::assertNull($file->getSize());
        self::assertNull($file->getClientFilename());
        self::assertNull($file->getClientMediaType());
    }

    #[Test]
    public function it_refuses_an_error_code_php_never_reports(): void
    {
        try {
            new UploadedFile(new MemoryStream(), error: 99);

            self::fail('Expected an InvalidUploadedFileException.');
        } catch (InvalidUploadedFileException $exception) {
            self::assertSame('Invalid upload error code "99".', $exception->getMessage());
            self::assertSame(['error' => 99], $exception->context);
        }
    }

    #[Test]
    public function it_refuses_a_negative_size(): void
    {
        try {
            new UploadedFile(new MemoryStream(), -1);

            self::fail('Expected an InvalidUploadedFileException.');
        } catch (InvalidUploadedFileException $exception) {
            self::assertSame('Uploaded file size cannot be negative, "-1" given.', $exception->getMessage());
            self::assertSame(['size' => -1], $exception->context);
        }
    }

    #[Test]
    public function it_refuses_an_empty_source_path(): void
    {
        $this->expectException(InvalidUploadedFileException::class);
        $this->expectExceptionMessage('Uploaded file source path cannot be empty.');

        new UploadedFile(new MemoryStream(), sourcePath: '');
    }

    #[Test]
    public function it_insists_on_a_stream_for_an_upload_that_succeeded(): void
    {
        $this->expectException(InvalidUploadedFileException::class);
        $this->expectExceptionMessage('A successfully uploaded file must have a stream.');

        new UploadedFile(null);
    }

    #[Test]
    public function it_accepts_a_failed_upload_without_a_stream(): void
    {
        $file = new UploadedFile(null, error: UPLOAD_ERR_NO_FILE);

        self::assertSame(UPLOAD_ERR_NO_FILE, $file->getError());

        try {
            $file->getStream();

            self::fail('Expected an UploadedFileException.');
        } catch (UploadedFileException $exception) {
            self::assertSame('The upload failed with error code "4".', $exception->getMessage());
            self::assertSame(['error' => UPLOAD_ERR_NO_FILE], $exception->context);
        }
    }

    #[Test]
    public function it_refuses_an_empty_target_path(): void
    {
        $this->expectException(InvalidUploadedFileException::class);
        $this->expectExceptionMessage('Target path cannot be empty.');

        new UploadedFile(new MemoryStream())->moveTo('');
    }

    #[Test]
    public function it_refuses_a_target_path_with_a_null_byte(): void
    {
        $this->expectException(InvalidUploadedFileException::class);
        $this->expectExceptionMessage('Target path cannot contain null bytes.');

        new UploadedFile(new MemoryStream())->moveTo($this->path('tar') . "\0get");
    }

    #[Test]
    public function it_refuses_to_move_an_upload_that_failed(): void
    {
        $this->expectException(UploadedFileException::class);
        $this->expectExceptionMessage('The upload failed with error code "1".');

        new UploadedFile(null, error: UPLOAD_ERR_INI_SIZE)->moveTo($this->path('target'));
    }

    #[Test]
    public function it_renames_the_source_file_to_the_target(): void
    {
        $source = $this->write('source', 'contents');
        $target = $this->path('target');
        $stream = new MemoryStream('contents');

        new UploadedFile($stream, sourcePath: $source)->moveTo($target);

        self::assertFileDoesNotExist($source);
        self::assertStringEqualsFile($target, 'contents');
        self::assertTrue($stream->closed);
    }

    #[Test]
    public function it_copies_the_stream_when_there_is_no_source_file(): void
    {
        $target = $this->path('target');

        new UploadedFile(new MemoryStream('contents'))->moveTo($target);

        self::assertStringEqualsFile($target, 'contents');
    }

    #[Test]
    public function it_copies_the_stream_when_the_source_file_is_gone(): void
    {
        $target = $this->path('target');

        new UploadedFile(new MemoryStream('contents'), sourcePath: $this->path('missing'))->moveTo($target);

        self::assertStringEqualsFile($target, 'contents');
    }

    #[Test]
    public function it_copies_a_stream_that_is_larger_than_one_chunk(): void
    {
        $target = $this->path('target');
        $contents = str_repeat('a', (1024 * 1024) + 1);

        new UploadedFile(new MemoryStream($contents))->moveTo($target);

        self::assertStringEqualsFile($target, $contents);
    }

    #[Test]
    public function it_rewinds_a_seekable_stream_before_copying_it(): void
    {
        $stream = new MemoryStream('contents');
        $stream->read(4);

        new UploadedFile($stream)->moveTo($this->path('target'));

        self::assertTrue($stream->rewound);
        self::assertStringEqualsFile($this->path('target'), 'contents');
    }

    #[Test]
    public function it_copies_a_stream_that_cannot_rewind_from_where_it_stands(): void
    {
        $stream = new MemoryStream('contents', seekable: false);

        new UploadedFile($stream)->moveTo($this->path('target'));

        self::assertFalse($stream->rewound);
        self::assertStringEqualsFile($this->path('target'), 'contents');
    }

    #[Test]
    public function it_stops_reading_once_the_stream_reports_the_end(): void
    {
        $stream = new MemoryStream('contents');
        $stream->emptiesAtEof = true;

        new UploadedFile($stream)->moveTo($this->path('target'));

        self::assertStringEqualsFile($this->path('target'), '');
    }

    #[Test]
    public function it_refuses_to_move_a_file_twice(): void
    {
        $file = new UploadedFile(new MemoryStream('contents'));
        $file->moveTo($this->path('target'));

        try {
            $file->moveTo($this->path('other'));

            self::fail('Expected an UploadedFileException.');
        } catch (UploadedFileException $exception) {
            self::assertSame('The uploaded file has already been moved.', $exception->getMessage());
        }

        $this->expectException(UploadedFileException::class);
        $this->expectExceptionMessage('The uploaded file has already been moved.');

        $file->getStream();
    }

    #[Test]
    public function it_reports_a_target_it_cannot_open(): void
    {
        $target = $this->directory . '/missing/target';

        try {
            new UploadedFile(new MemoryStream('contents'))->moveTo($target);

            self::fail('Expected an UploadedFileException.');
        } catch (UploadedFileException $exception) {
            self::assertSame(
                sprintf('Unable to open target path "%s" for writing.', $target),
                $exception->getMessage(),
            );
            self::assertSame(['targetPath' => $target], $exception->context);
        }
    }

    #[Test]
    public function it_reports_a_stream_that_stops_producing_before_the_end(): void
    {
        $stream = new MemoryStream('contents');
        $stream->stalls = true;
        $target = $this->path('target');

        try {
            new UploadedFile($stream)->moveTo($target);

            self::fail('Expected an UploadedFileException.');
        } catch (UploadedFileException $exception) {
            self::assertSame('Unable to read from uploaded file stream.', $exception->getMessage());
            self::assertSame(['targetPath' => $target], $exception->context);
            self::assertNull($exception->getPrevious());
        }

        self::assertFileDoesNotExist($target);
    }

    #[Test]
    public function it_wraps_a_runtime_failure_the_stream_raises_without_its_message(): void
    {
        $failure = new RuntimeException('the stream gave up');
        $stream = new MemoryStream('contents');
        $stream->readFailure = $failure;
        $target = $this->path('target');

        try {
            new UploadedFile($stream)->moveTo($target);

            self::fail('Expected an UploadedFileException.');
        } catch (UploadedFileException $exception) {
            self::assertSame('Unable to read from uploaded file stream.', $exception->getMessage());
            self::assertSame(['targetPath' => $target], $exception->context);
            self::assertSame($failure, $exception->getPrevious());
        }

        self::assertFileDoesNotExist($target);
    }

    #[Test]
    public function it_reports_a_write_that_never_lands(): void
    {
        if (!is_writable('/dev/full')) {
            self::markTestSkipped('/dev/full is not available.');
        }

        try {
            new UploadedFile(new MemoryStream('contents'))->moveTo('/dev/full');

            self::fail('Expected an UploadedFileException.');
        } catch (UploadedFileException $exception) {
            self::assertSame('Unable to write uploaded file.', $exception->getMessage());
            self::assertSame(['targetPath' => '/dev/full'], $exception->context);
            self::assertNull($exception->getPrevious());
        }
    }

    #[Test]
    public function it_reports_a_source_it_cannot_remove(): void
    {
        $locked = $this->directory . '/locked';
        mkdir($locked);

        $source = $locked . '/source';
        file_put_contents($source, data: 'contents');
        chmod($locked, permissions: 0o555);

        $target = $this->path('target');

        try {
            new UploadedFile(new MemoryStream('contents'), sourcePath: $source)->moveTo($target);

            self::fail('Expected an UploadedFileException.');
        } catch (UploadedFileException $exception) {
            self::assertSame(sprintf('Unable to remove uploaded file source "%s".', $source), $exception->getMessage());
            self::assertSame(['sourcePath' => $source], $exception->context);
        }

        self::assertFileDoesNotExist($target);
    }

    private function path(string $name): string
    {
        return $this->directory . '/' . $name;
    }

    private function write(string $name, string $contents): string
    {
        $path = $this->path($name);

        file_put_contents($path, data: $contents);

        return $path;
    }

    private function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        chmod($directory, permissions: 0o755);

        $entries = scandir($directory);

        if ($entries === false) {
            return;
        }

        foreach (array_diff($entries, ['.', '..']) as $entry) {
            $path = $directory . '/' . $entry;

            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }

        rmdir($directory);
    }
}
