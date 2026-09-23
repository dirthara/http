<?php

declare(strict_types=1);

namespace Dirthara\Http\Tests;

use RuntimeException;
use Dirthara\Http\Stream;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Dirthara\Http\Exception\StreamException;
use Dirthara\Http\Exception\InvalidStreamException;
use Dirthara\Http\Tests\Doubles\FailingStreamWrapper;

final class StreamTest extends TestCase
{
    private string $directory;

    /**
     * @var list<array{resource, resource}>
     */
    private array $processes = [];

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/dirthara-http-' . bin2hex(random_bytes(8));

        mkdir($this->directory);
    }

    protected function tearDown(): void
    {
        FailingStreamWrapper::unregister();

        foreach ($this->processes as [$process, $pipe]) {
            if (is_resource($pipe)) {
                fclose($pipe);
            }

            proc_close($process);
        }

        $this->processes = [];

        $entries = scandir($this->directory);

        foreach (array_diff($entries === false ? [] : $entries, ['.', '..']) as $entry) {
            unlink($this->directory . '/' . $entry);
        }

        rmdir($this->directory);
    }

    #[Test]
    public function it_refuses_anything_that_is_not_a_resource(): void
    {
        try {
            // @mago-expect analysis:invalid-argument
            new Stream('php://memory');

            self::fail('Expected an InvalidStreamException.');
        } catch (InvalidStreamException $exception) {
            self::assertSame('Expected a PHP stream resource, "string" given.', $exception->getMessage());
            self::assertSame(['type' => 'string'], $exception->context);
        }
    }

    #[Test]
    public function it_refuses_a_resource_that_is_not_a_stream(): void
    {
        try {
            new Stream(stream_context_create());

            self::fail('Expected an InvalidStreamException.');
        } catch (InvalidStreamException $exception) {
            self::assertSame(
                'Expected a PHP stream resource, "resource (stream-context)" given.',
                $exception->getMessage(),
            );
            self::assertSame(['type' => 'resource (stream-context)'], $exception->context);
        }
    }

    #[Test]
    public function it_reads_the_mode_to_learn_what_it_may_do(): void
    {
        $readable = $this->stream('rb', 'hello');

        self::assertTrue($readable->isReadable());
        self::assertFalse($readable->isWritable());
        self::assertTrue($readable->isSeekable());

        $writable = $this->stream('wb');

        self::assertFalse($writable->isReadable());
        self::assertTrue($writable->isWritable());

        $both = $this->stream('r+b', 'hello');

        self::assertTrue($both->isReadable());
        self::assertTrue($both->isWritable());
    }

    #[Test]
    public function it_treats_every_writing_mode_as_writable(): void
    {
        foreach (['wb', 'ab', 'cb'] as $mode) {
            self::assertTrue($this->stream($mode)->isWritable(), $mode);
            self::assertFalse($this->stream($mode)->isReadable(), $mode);
        }

        $exclusive = new Stream(fopen($this->directory . '/exclusive', mode: 'xb'));

        self::assertTrue($exclusive->isWritable());
        self::assertFalse($exclusive->isReadable());

        $appendPlus = $this->stream('a+b');

        self::assertTrue($appendPlus->isWritable());
        self::assertTrue($appendPlus->isReadable());
    }

    #[Test]
    public function it_may_do_nothing_when_the_mode_says_nothing(): void
    {
        FailingStreamWrapper::register();

        $stream = new Stream(FailingStreamWrapper::open(''));

        self::assertFalse($stream->isReadable());
        self::assertFalse($stream->isWritable());
    }

    #[Test]
    public function it_rewinds_before_it_is_read_as_a_string(): void
    {
        $stream = $this->stream('r+b', 'hello');
        $stream->read(2);

        self::assertSame('hello', (string) $stream);
    }

    #[Test]
    public function it_reads_a_stream_that_cannot_rewind_from_where_it_stands(): void
    {
        $stream = new Stream($this->pipeFrom('printf hello'));

        self::assertFalse($stream->isSeekable());
        self::assertSame('hello', (string) $stream);
    }

    #[Test]
    public function it_is_empty_as_a_string_when_it_cannot_be_read(): void
    {
        self::assertSame('', (string) $this->stream('wb'));
    }

    #[Test]
    public function it_is_empty_as_a_string_once_detached(): void
    {
        $stream = $this->stream('r+b', 'hello');
        $stream->detach();

        self::assertSame('', (string) $stream);
    }

    #[Test]
    public function it_closes_the_resource_underneath(): void
    {
        $handle = $this->handle('r+b');
        $stream = new Stream($handle);

        $stream->close();

        self::assertFalse(is_resource($handle));
        self::assertFalse($stream->isReadable());
        self::assertFalse($stream->isWritable());
        self::assertFalse($stream->isSeekable());
        self::assertTrue($stream->eof());
    }

    #[Test]
    public function it_survives_being_closed_twice(): void
    {
        $stream = $this->stream('r+b');

        $stream->close();
        $stream->close();

        self::assertTrue($stream->eof());
    }

    #[Test]
    public function it_hands_the_resource_back_when_it_is_detached(): void
    {
        $handle = $this->handle('r+b');
        $stream = new Stream($handle);

        self::assertSame($handle, $stream->detach());
        self::assertNull($stream->detach());
        self::assertTrue(is_resource($handle));

        fclose($handle);
    }

    #[Test]
    public function it_knows_how_large_it_is(): void
    {
        $stream = $this->stream('r+b', 'hello');

        self::assertSame(5, $stream->getSize());

        $stream->detach();

        self::assertNull($stream->getSize());
    }

    #[Test]
    public function it_has_no_size_when_the_resource_cannot_be_inspected(): void
    {
        self::assertNull(new Stream($this->directoryHandle())->getSize());
    }

    #[Test]
    public function it_tracks_where_it_stands(): void
    {
        $stream = $this->stream('r+b');

        self::assertSame(0, $stream->tell());
        self::assertSame(5, $stream->write('hello'));
        self::assertSame(5, $stream->tell());

        $stream->seek(2);

        self::assertSame(2, $stream->tell());

        $stream->rewind();

        self::assertSame(0, $stream->tell());
    }

    #[Test]
    public function it_reports_a_position_it_cannot_determine(): void
    {
        $this->expectException(StreamException::class);
        $this->expectExceptionMessage('Unable to tell stream position.');

        new Stream($this->pipeFrom('true'))->tell();
    }

    #[Test]
    public function it_knows_when_it_reached_the_end(): void
    {
        $stream = $this->stream('r+b', 'hello');

        self::assertFalse($stream->eof());

        $stream->read(5);
        $stream->read(1);

        self::assertTrue($stream->eof());
    }

    #[Test]
    public function it_refuses_to_seek_a_stream_that_cannot_seek(): void
    {
        $stream = new Stream($this->open('php://output', 'wb'));

        self::assertFalse($stream->isSeekable());

        $this->expectException(StreamException::class);
        $this->expectExceptionMessage('Stream is not seekable.');

        $stream->seek(0);
    }

    #[Test]
    public function it_reports_a_seek_that_lands_nowhere(): void
    {
        try {
            $this->stream('r+b', 'hello')->seek(-10);

            self::fail('Expected a StreamException.');
        } catch (StreamException $exception) {
            self::assertSame('Unable to seek to stream position -10 with whence 0.', $exception->getMessage());
            self::assertSame(['offset' => -10, 'whence' => SEEK_SET], $exception->context);
        }
    }

    #[Test]
    public function it_writes_what_it_is_given(): void
    {
        $stream = $this->stream('r+b');

        self::assertSame(0, $stream->write(''));
        self::assertSame(5, $stream->write('hello'));
        self::assertSame('hello', (string) $stream);
    }

    #[Test]
    public function it_refuses_to_write_to_a_stream_it_may_only_read(): void
    {
        $this->expectException(StreamException::class);
        $this->expectExceptionMessage('Unable to write to stream.');

        $this->stream('rb', 'hello')->write('more');
    }

    #[Test]
    public function it_reports_a_write_that_does_not_land(): void
    {
        FailingStreamWrapper::register();

        $this->expectException(StreamException::class);
        $this->expectExceptionMessage('Unable to write to stream.');

        new Stream(FailingStreamWrapper::open('r+b'))->write('hello');
    }

    #[Test]
    public function it_reads_as_much_as_it_is_asked_for(): void
    {
        $stream = $this->stream('rb', 'hello');

        self::assertSame('', $stream->read(0));
        self::assertSame('he', $stream->read(2));
        self::assertSame('llo', $stream->read(10));
    }

    #[Test]
    public function it_refuses_a_negative_read_length(): void
    {
        try {
            $this->stream('rb', 'hello')->read(-1);

            self::fail('Expected an InvalidStreamException.');
        } catch (InvalidStreamException $exception) {
            self::assertSame('Stream read length cannot be negative, "-1" given.', $exception->getMessage());
            self::assertSame(['length' => -1], $exception->context);
        }
    }

    #[Test]
    public function it_refuses_to_read_a_stream_it_may_only_write(): void
    {
        $this->expectException(StreamException::class);
        $this->expectExceptionMessage('Unable to read from stream.');

        $this->stream('wb')->read(1);
    }

    #[Test]
    public function it_reports_a_read_that_fails(): void
    {
        $this->expectException(StreamException::class);
        $this->expectExceptionMessage('Unable to read from stream with length 8.');

        new Stream($this->directoryHandle())->read(8);
    }

    #[Test]
    public function it_returns_everything_that_is_left(): void
    {
        $stream = $this->stream('rb', 'hello');
        $stream->read(2);

        self::assertSame('llo', $stream->getContents());
        self::assertSame('', $stream->getContents());
    }

    #[Test]
    public function it_refuses_to_return_the_contents_of_a_stream_it_may_only_write(): void
    {
        $this->expectException(StreamException::class);
        $this->expectExceptionMessage('Unable to read from stream.');

        $this->stream('wb')->getContents();
    }

    #[Test]
    public function it_describes_itself_through_its_metadata(): void
    {
        $stream = $this->stream('r+b');

        self::assertSame('r+b', $stream->getMetadata('mode'));
        self::assertTrue($stream->getMetadata('seekable'));
        self::assertNull($stream->getMetadata('nothing'));
        $metadata = $stream->getMetadata();

        self::assertIsArray($metadata);
        self::assertArrayHasKey('mode', $metadata);
    }

    #[Test]
    public function it_has_no_metadata_once_detached(): void
    {
        $stream = $this->stream('r+b');
        $stream->detach();

        self::assertSame([], $stream->getMetadata());
        self::assertNull($stream->getMetadata('mode'));
    }

    #[Test]
    public function it_refuses_every_operation_once_detached(): void
    {
        $operations = [
            'tell' => static fn(Stream $stream): int => $stream->tell(),
            'seek' => static fn(Stream $stream): null => $stream->seek(0),
            'rewind' => static fn(Stream $stream): null => $stream->rewind(),
            'read' => static fn(Stream $stream): string => $stream->read(1),
            'write' => static fn(Stream $stream): int => $stream->write('hello'),
            'getContents' => static fn(Stream $stream): string => $stream->getContents(),
        ];

        foreach ($operations as $name => $operation) {
            $stream = $this->stream('r+b', 'hello');
            $stream->detach();

            try {
                $operation($stream);

                self::fail(sprintf('Expected %s() to refuse a detached stream.', $name));
            } catch (StreamException $exception) {
                self::assertSame('Stream has been detached.', $exception->getMessage(), $name);
                self::assertInstanceOf(RuntimeException::class, $exception, $name);
            }
        }
    }

    #[Test]
    public function it_reads_at_most_a_mebibyte_at_a_time_whatever_length_is_asked(): void
    {
        $stream = $this->stream('r+b', str_repeat('a', (1024 * 1024) + 10));

        self::assertSame(1024 * 1024, strlen($stream->read(PHP_INT_MAX)));
        self::assertSame('aaaaaaaaaa', $stream->read(PHP_INT_MAX));
    }

    private function stream(string $mode, string $contents = ''): Stream
    {
        return new Stream($this->handle($mode, $contents));
    }

    /**
     * @return resource
     */
    private function handle(string $mode, string $contents = '')
    {
        $path = $this->directory . '/' . bin2hex(random_bytes(4));

        file_put_contents($path, data: $contents);

        return $this->open($path, $mode);
    }

    /**
     * @return resource
     */
    private function open(string $target, string $mode)
    {
        $handle = fopen($target, mode: $mode);

        if (!is_resource($handle)) {
            throw new RuntimeException(sprintf('Unable to open "%s".', $target));
        }

        return $handle;
    }

    /**
     * @return resource
     */
    private function directoryHandle()
    {
        $handle = opendir($this->directory);

        if (!is_resource($handle)) {
            throw new RuntimeException('Unable to open the temporary directory.');
        }

        return $handle;
    }

    /**
     * @return resource
     */
    private function pipeFrom(string $command)
    {
        $pipes = [];
        $descriptors = [1 => ['pipe', 'w'], 2 => ['file', '/dev/null', 'w']];
        $process = proc_open($command, $descriptors, $pipes);
        $pipe = $pipes[1] ?? null;

        if (!is_resource($process) || !is_resource($pipe)) {
            throw new RuntimeException(sprintf('Unable to run "%s".', $command));
        }

        $this->processes[] = [$process, $pipe];

        return $pipe;
    }
}
