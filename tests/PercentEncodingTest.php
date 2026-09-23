<?php

declare(strict_types=1);

namespace Dirthara\Http\Tests;

use PHPUnit\Framework\TestCase;
use Dirthara\Http\PercentEncoding;
use PHPUnit\Framework\Attributes\Test;

final class PercentEncodingTest extends TestCase
{
    #[Test]
    public function it_leaves_unreserved_characters_alone(): void
    {
        self::assertSame('AZaz09-._~', PercentEncoding::encode('AZaz09-._~', ''));
    }

    #[Test]
    public function it_leaves_the_extra_allowed_characters_alone(): void
    {
        self::assertSame('a/b:c', PercentEncoding::encode('a/b:c', '/:'));
        self::assertSame('a%2Fb%3Ac', PercentEncoding::encode('a/b:c', ''));
    }

    #[Test]
    public function it_encodes_everything_else_in_upper_case_hexadecimal(): void
    {
        self::assertSame('a%20b%0D%0A%C3%A9', PercentEncoding::encode("a b\r\né", ''));
    }

    #[Test]
    public function it_keeps_an_existing_triplet_and_encodes_a_stray_percent(): void
    {
        self::assertSame('%2f%20%25zz%25', PercentEncoding::encode('%2f %zz%', ''));
    }
}
