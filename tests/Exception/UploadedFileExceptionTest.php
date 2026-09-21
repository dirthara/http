<?php

declare(strict_types=1);

namespace Dirthara\Http\Tests\Exception;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Dirthara\Http\Exception\UploadedFileException;

final class UploadedFileExceptionTest extends TestCase
{
    #[Test]
    public function it_carries_nothing_by_default(): void
    {
        $exception = new UploadedFileException();

        self::assertSame('', $exception->getMessage());
        self::assertSame(0, $exception->getCode());
        self::assertNull($exception->getPrevious());
        self::assertSame([], $exception->context);
    }

    #[Test]
    public function it_merges_what_is_added_to_its_context(): void
    {
        $exception = UploadedFileException::uploadFailed(UPLOAD_ERR_PARTIAL);

        self::assertSame($exception, $exception->addContext(['error' => 0, 'targetPath' => '/tmp/target']));
        self::assertSame(['error' => 0, 'targetPath' => '/tmp/target'], $exception->context);
    }

    #[Test]
    public function it_describes_a_stream_that_is_not_there(): void
    {
        $exception = UploadedFileException::streamUnavailable();

        self::assertSame('No stream is available for the uploaded file.', $exception->getMessage());
        self::assertSame([], $exception->context);
    }

    #[Test]
    public function it_describes_a_move_that_did_not_happen(): void
    {
        $exception = UploadedFileException::unableToMove('/tmp/target');

        self::assertSame('Unable to move uploaded file to "/tmp/target".', $exception->getMessage());
        self::assertSame(['targetPath' => '/tmp/target'], $exception->context);
    }

    #[Test]
    public function it_describes_a_target_that_would_not_close(): void
    {
        $exception = UploadedFileException::unableToCloseTarget('/tmp/target');

        self::assertSame('Unable to close target file "/tmp/target".', $exception->getMessage());
        self::assertSame(['targetPath' => '/tmp/target'], $exception->context);
    }
}
