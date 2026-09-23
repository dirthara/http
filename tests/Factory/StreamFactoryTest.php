<?php

declare(strict_types=1);

namespace Dirthara\Http\Tests\Factory;

use Dirthara\Http\Stream;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Dirthara\Http\Factory\StreamFactory;
use Dirthara\Http\Exception\StreamException;
use Dirthara\Http\Exception\InvalidStreamException;

use function fopen;
use function mkdir;
use function rmdir;
use function unlink;
use function bin2hex;
use function scandir;
use function sprintf;
use function array_diff;
use function random_bytes;
use function sys_get_temp_dir;
use function file_put_contents;

final class StreamFactoryTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/dirthara-http-' . bin2hex(random_bytes(8));

        mkdir($this->directory);
    }

    protected function tearDown(): void
    {
        $entries = scandir($this->directory);

        foreach (array_diff($entries === false ? [] : $entries, ['.', '..']) as $entry) {
            unlink($this->directory . '/' . $entry);
        }

        rmdir($this->directory);
    }

    #[Test]
    public function it_creates_a_readable_writable_seekable_stream_at_the_start_of_its_content(): void
    {
        $stream = new StreamFactory()->createStream('content');

        self::assertTrue($stream->isReadable());
        self::assertTrue($stream->isWritable());
        self::assertTrue($stream->isSeekable());
        self::assertSame(0, $stream->tell());
        self::assertSame('content', $stream->getContents());
    }

    #[Test]
    public function it_creates_an_empty_stream_by_default(): void
    {
        $stream = new StreamFactory()->createStream();

        self::assertSame(0, $stream->getSize());
        self::assertSame('', (string) $stream);
    }

    #[Test]
    public function it_creates_a_stream_from_a_file_with_the_given_mode(): void
    {
        $path = $this->directory . '/file.txt';
        file_put_contents($path, data: 'content');

        $readOnly = new StreamFactory()->createStreamFromFile($path);
        $appending = new StreamFactory()->createStreamFromFile($path, 'ab');

        self::assertSame('content', (string) $readOnly);
        self::assertFalse($readOnly->isWritable());
        self::assertTrue($appending->isWritable());
        self::assertFalse($appending->isReadable());
    }

    #[Test]
    public function it_accepts_the_modes_fopen_accepts(): void
    {
        $path = $this->directory . '/file.txt';
        $factory = new StreamFactory();

        foreach (['w', 'w+', 'wb', 'w+b', 'wb+', 'rt', 'r+', 'c+e', 'a+'] as $mode) {
            self::assertInstanceOf(Stream::class, $factory->createStreamFromFile($path, $mode), $mode);
        }
    }

    #[Test]
    public function it_refuses_a_mode_fopen_does_not_know(): void
    {
        try {
            new StreamFactory()->createStreamFromFile($this->directory . '/file.txt', 'z');

            self::fail('Expected an InvalidStreamException.');
        } catch (InvalidStreamException $exception) {
            self::assertSame('The stream mode "z" is invalid.', $exception->getMessage());
            self::assertSame(['mode' => 'z'], $exception->context);
        }
    }

    #[Test]
    public function it_reports_a_file_it_cannot_open(): void
    {
        $path = $this->directory . '/missing.txt';

        try {
            new StreamFactory()->createStreamFromFile($path);

            self::fail('Expected a StreamException.');
        } catch (StreamException $exception) {
            self::assertSame(sprintf('Unable to open "%s" with mode "r".', $path), $exception->getMessage());
            self::assertSame(['filename' => $path, 'mode' => 'r'], $exception->context);
            self::assertNull($exception->getPrevious());
        }
    }

    #[Test]
    public function it_refuses_a_filename_with_a_null_byte(): void
    {
        try {
            new StreamFactory()->createStreamFromFile("file\0.txt");

            self::fail('Expected an InvalidStreamException.');
        } catch (InvalidStreamException $exception) {
            self::assertSame('A stream filename cannot be empty or contain null bytes.', $exception->getMessage());
            self::assertSame(['filename' => "file\0.txt"], $exception->context);
        }
    }

    #[Test]
    public function it_refuses_an_empty_filename(): void
    {
        $this->expectException(InvalidStreamException::class);

        new StreamFactory()->createStreamFromFile('');
    }

    #[Test]
    public function it_refuses_to_open_a_directory(): void
    {
        $this->expectException(StreamException::class);
        $this->expectExceptionMessage(sprintf('Unable to open "%s" with mode "r".', $this->directory));

        new StreamFactory()->createStreamFromFile($this->directory);
    }

    #[Test]
    public function it_escapes_control_characters_in_the_message_for_a_file_it_cannot_open(): void
    {
        $path = $this->directory . "/missing\n.txt";

        try {
            new StreamFactory()->createStreamFromFile($path);

            self::fail('Expected a StreamException.');
        } catch (StreamException $exception) {
            self::assertSame(
                sprintf('Unable to open "%s/missing\\n.txt" with mode "r".', $this->directory),
                $exception->getMessage(),
            );
            self::assertSame(['filename' => $path, 'mode' => 'r'], $exception->context);
        }
    }

    #[Test]
    public function it_creates_a_stream_from_a_resource(): void
    {
        $resource = fopen('php://memory', mode: 'r+b');
        self::assertIsResource($resource);

        self::assertSame(
            $resource,
            new StreamFactory()
                ->createStreamFromResource($resource)
                ->detach(),
        );
    }

    #[Test]
    public function it_refuses_something_that_is_not_a_resource(): void
    {
        $this->expectException(InvalidStreamException::class);

        // @mago-expect analysis:invalid-argument
        new StreamFactory()->createStreamFromResource('php://memory');
    }
}
