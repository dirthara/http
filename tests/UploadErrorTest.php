<?php

declare(strict_types=1);

namespace Dirthara\Http\Tests;

use Dirthara\Http\UploadError;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;

use const UPLOAD_ERR_OK;
use const UPLOAD_ERR_NO_FILE;
use const UPLOAD_ERR_PARTIAL;
use const UPLOAD_ERR_INI_SIZE;
use const UPLOAD_ERR_EXTENSION;
use const UPLOAD_ERR_FORM_SIZE;
use const UPLOAD_ERR_CANT_WRITE;
use const UPLOAD_ERR_NO_TMP_DIR;

final class UploadErrorTest extends TestCase
{
    #[Test]
    public function it_is_backed_by_the_php_upload_error_constants(): void
    {
        self::assertSame(UPLOAD_ERR_OK, UploadError::Ok->value);
        self::assertSame(UPLOAD_ERR_INI_SIZE, UploadError::IniSize->value);
        self::assertSame(UPLOAD_ERR_FORM_SIZE, UploadError::FormSize->value);
        self::assertSame(UPLOAD_ERR_PARTIAL, UploadError::Partial->value);
        self::assertSame(UPLOAD_ERR_NO_FILE, UploadError::NoFile->value);
        self::assertSame(UPLOAD_ERR_NO_TMP_DIR, UploadError::NoTemporaryDirectory->value);
        self::assertSame(UPLOAD_ERR_CANT_WRITE, UploadError::CannotWrite->value);
        self::assertSame(UPLOAD_ERR_EXTENSION, UploadError::Extension->value);
        self::assertNull(UploadError::tryFrom(5));
    }

    #[Test]
    public function it_is_ok_only_for_a_successful_upload(): void
    {
        foreach (UploadError::cases() as $error) {
            self::assertSame($error === UploadError::Ok, $error->isOk(), $error->name);
        }
    }

    #[Test]
    public function it_describes_every_case(): void
    {
        self::assertSame('No file was uploaded.', UploadError::NoFile->description());

        foreach (UploadError::cases() as $error) {
            self::assertStringEndsWith('.', $error->description(), $error->name);
        }
    }
}
