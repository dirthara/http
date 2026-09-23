<?php

declare(strict_types=1);

namespace Dirthara\Http\Tests\Factory;

use Dirthara\Http\Stream;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Dirthara\Http\Factory\StreamFactory;
use Dirthara\Http\Factory\UploadedFileFactory;
use Dirthara\Http\Exception\InvalidUploadedFileException;

use function fopen;
use function fclose;

final class UploadedFileFactoryTest extends TestCase
{
    #[Test]
    public function it_creates_an_uploaded_file_with_what_it_was_given(): void
    {
        $stream = new StreamFactory()->createStream('content');

        $file = new UploadedFileFactory()->createUploadedFile($stream, 3, UPLOAD_ERR_OK, 'photo.jpg', 'image/jpeg');

        self::assertSame($stream, $file->getStream());
        self::assertSame(3, $file->getSize());
        self::assertSame(UPLOAD_ERR_OK, $file->getError());
        self::assertSame('photo.jpg', $file->getClientFilename());
        self::assertSame('image/jpeg', $file->getClientMediaType());
    }

    #[Test]
    public function it_takes_the_size_from_the_stream_when_none_is_given(): void
    {
        $file = new UploadedFileFactory()->createUploadedFile(new StreamFactory()->createStream('content'));

        self::assertSame(7, $file->getSize());
        self::assertSame(UPLOAD_ERR_OK, $file->getError());
        self::assertNull($file->getClientFilename());
        self::assertNull($file->getClientMediaType());
    }

    #[Test]
    public function it_creates_an_uploaded_file_for_a_failed_upload(): void
    {
        $file = new UploadedFileFactory()->createUploadedFile(
            new StreamFactory()->createStream(),
            error: UPLOAD_ERR_NO_FILE,
        );

        self::assertSame(UPLOAD_ERR_NO_FILE, $file->getError());
    }

    #[Test]
    public function it_refuses_a_stream_it_cannot_read(): void
    {
        $resource = fopen('php://output', mode: 'wb');
        self::assertIsResource($resource);

        try {
            new UploadedFileFactory()->createUploadedFile(new Stream($resource));

            self::fail('Expected an InvalidUploadedFileException.');
        } catch (InvalidUploadedFileException $exception) {
            self::assertSame('The stream of an uploaded file must be readable.', $exception->getMessage());
            self::assertSame([], $exception->context);
        } finally {
            fclose($resource);
        }
    }
}
