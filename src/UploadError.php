<?php

declare(strict_types=1);

namespace Dirthara\Http;

/**
 * The error codes PHP reports for a file upload, one case per UPLOAD_ERR_* constant.
 */
enum UploadError: int
{
    case Ok = UPLOAD_ERR_OK;
    case IniSize = UPLOAD_ERR_INI_SIZE;
    case FormSize = UPLOAD_ERR_FORM_SIZE;
    case Partial = UPLOAD_ERR_PARTIAL;
    case NoFile = UPLOAD_ERR_NO_FILE;
    case NoTemporaryDirectory = UPLOAD_ERR_NO_TMP_DIR;
    case CannotWrite = UPLOAD_ERR_CANT_WRITE;
    case Extension = UPLOAD_ERR_EXTENSION;

    public function isOk(): bool
    {
        return $this === self::Ok;
    }

    public function description(): string
    {
        return match ($this) {
            self::Ok => 'The file was uploaded successfully.',
            self::IniSize => 'The file exceeds the upload_max_filesize directive in php.ini.',
            self::FormSize => 'The file exceeds the MAX_FILE_SIZE directive in the HTML form.',
            self::Partial => 'The file was only partially uploaded.',
            self::NoFile => 'No file was uploaded.',
            self::NoTemporaryDirectory => 'The temporary folder is missing.',
            self::CannotWrite => 'The file could not be written to disk.',
            self::Extension => 'A PHP extension stopped the upload.',
        };
    }
}
