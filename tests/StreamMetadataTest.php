<?php

declare(strict_types=1);

namespace Dirthara\Http\Tests;

use PHPUnit\Framework\TestCase;
use Dirthara\Http\StreamMetadata;
use PHPUnit\Framework\Attributes\Test;

use function fopen;
use function fclose;

final class StreamMetadataTest extends TestCase
{
    #[Test]
    public function it_reads_the_mode_and_seekability_of_a_stream(): void
    {
        $resource = fopen('php://memory', mode: 'r+b');
        self::assertIsResource($resource);

        $metadata = StreamMetadata::fromResource($resource);
        fclose($resource);

        self::assertSame('w+b', $metadata->mode->mode);
        self::assertTrue($metadata->mode->isReadable());
        self::assertTrue($metadata->seekable);
    }

    #[Test]
    public function it_keeps_every_entry_including_those_a_wrapper_adds(): void
    {
        $resource = fopen('data://text/plain,hello', mode: 'rb');
        self::assertIsResource($resource);

        $metadata = StreamMetadata::fromResource($resource);
        fclose($resource);

        self::assertSame('text/plain', $metadata->get('mediatype'));
        self::assertSame('text/plain', $metadata->all()['mediatype']);
        self::assertSame('RFC2397', $metadata->get('wrapper_type'));
        self::assertNull($metadata->get('nothing'));
    }
}
