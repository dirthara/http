<?php

declare(strict_types=1);

namespace Dirthara\Http\Tests;

use Dirthara\Http\StreamMode;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Dirthara\Http\Exception\InvalidStreamException;

final class StreamModeTest extends TestCase
{
    #[Test]
    public function it_knows_what_each_mode_allows(): void
    {
        $modes = [
            'r' => [true, false],
            'rb' => [true, false],
            'rt' => [true, false],
            'r+' => [true, true],
            'r+b' => [true, true],
            'rb+' => [true, true],
            'w' => [false, true],
            'wb' => [false, true],
            'w+' => [true, true],
            'a' => [false, true],
            'a+' => [true, true],
            'x' => [false, true],
            'x+' => [true, true],
            'c' => [false, true],
            'c+e' => [true, true],
        ];

        foreach ($modes as $mode => [$readable, $writable]) {
            $streamMode = StreamMode::fromString($mode);

            self::assertSame($mode, $streamMode->mode);
            self::assertSame($readable, $streamMode->isReadable(), $mode);
            self::assertSame($writable, $streamMode->isWritable(), $mode);
        }
    }

    #[Test]
    public function it_refuses_a_mode_fopen_does_not_know(): void
    {
        try {
            StreamMode::fromString("z\n");

            self::fail('Expected an InvalidStreamException.');
        } catch (InvalidStreamException $exception) {
            self::assertSame('The stream mode "z\n" is invalid.', $exception->getMessage());
            self::assertSame(['mode' => "z\n"], $exception->context);
        }
    }

    #[Test]
    public function it_takes_a_mode_from_metadata_as_it_is(): void
    {
        $empty = StreamMode::fromMetadata('');

        self::assertSame('', $empty->mode);
        self::assertFalse($empty->isReadable());
        self::assertFalse($empty->isWritable());
    }
}
