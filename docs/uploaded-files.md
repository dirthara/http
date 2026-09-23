---
id: uploaded-files
title: Uploaded files
sidebar_position: 8
description: Represent uploaded files, read them, and move them to their destination.
---

`Dirthara\Http\UploadedFile` is a file uploaded with a request. A [server request](messages/server-requests.md) holds
them in a tree under `getUploadedFiles()`.

## Create an uploaded file

The [uploaded file factory](factories.md) creates one from a stream. To move the original upload rather than copy its
stream, pass the source path to the constructor:

```php
use Dirthara\Http\UploadedFile;
use Dirthara\Http\Factory\StreamFactory;

$upload = $_FILES['avatar'];

$file = new UploadedFile(
    new StreamFactory()->createStreamFromFile($upload['tmp_name']),
    $upload['size'],
    $upload['error'],
    $upload['name'],
    $upload['type'],
    $upload['tmp_name'],
);
```

| Parameter | Type | Default | Meaning |
| --- | --- | --- | --- |
| `stream` | `?StreamInterface` | | The file's contents. Required when the upload succeeded. |
| `size` | `?int` | `null` | The size in bytes, when known. |
| `error` | `int\|UploadError` | `UploadError::Ok` | An `UploadError` case, or one of PHP's `UPLOAD_ERR_*` constants. |
| `clientFilename` | `?string` | `null` | The filename the client sent. |
| `clientMediaType` | `?string` | `null` | The media type the client sent. |
| `sourcePath` | `?string` | `null` | The path of the file on disk, used by `moveTo()`. |

| Input | Exception |
| --- | --- |
| An error code that is not an `UPLOAD_ERR_*` constant | `InvalidUploadedFileException::invalidError()` |
| A negative size | `InvalidUploadedFileException::invalidSize()` |
| An empty source path | `InvalidUploadedFileException::emptySourcePath()` |
| No stream for a successful upload | `InvalidUploadedFileException::missingStream()` |

:::danger
The client filename and media type come from the client and can be anything. Never use the client filename as a path,
and do not trust the media type to describe the contents.
:::

## Read the file

`getSize()`, `getClientFilename()`, and `getClientMediaType()` return what was given, and `getError()` returns the error
code as an `int`, as PSR-7 requires. `getStream()` returns the contents. It throws
`UploadedFileException::uploadFailed()` when the upload failed, with a message that says why, and
`UploadedFileException::alreadyMoved()` after `moveTo()`.

## Upload errors

`Dirthara\Http\UploadError` is an `int`-backed enum with a case for each of PHP's `UPLOAD_ERR_*` constants, backed by
that constant. Turn the code `getError()` returns into a case with `UploadError::from()`:

```php
use Dirthara\Http\UploadError;

$error = UploadError::from($file->getError());

if (!$error->isOk()) {
    echo $error->description();
}
```

| Case | Constant | `description()` |
| --- | --- | --- |
| `Ok` | `UPLOAD_ERR_OK` | The file was uploaded successfully. |
| `IniSize` | `UPLOAD_ERR_INI_SIZE` | The file exceeds the upload_max_filesize directive in php.ini. |
| `FormSize` | `UPLOAD_ERR_FORM_SIZE` | The file exceeds the MAX_FILE_SIZE directive in the HTML form. |
| `Partial` | `UPLOAD_ERR_PARTIAL` | The file was only partially uploaded. |
| `NoFile` | `UPLOAD_ERR_NO_FILE` | No file was uploaded. |
| `NoTemporaryDirectory` | `UPLOAD_ERR_NO_TMP_DIR` | The temporary folder is missing. |
| `CannotWrite` | `UPLOAD_ERR_CANT_WRITE` | The file could not be written to disk. |
| `Extension` | `UPLOAD_ERR_EXTENSION` | A PHP extension stopped the upload. |

`isOk()` is `true` only for `Ok`.

## Move the file

`moveTo($targetPath)` puts the file at its destination and can only be done once. After it, `getStream()` and `moveTo()`
throw `UploadedFileException::alreadyMoved()`, and the stream is closed.

```php
$file->moveTo('/var/uploads/' . bin2hex(random_bytes(16)));
```

It tries each way in turn until one applies:

1. A source path PHP registered as an upload is moved with `move_uploaded_file()`.
2. Another source path that is a file is renamed.
3. Otherwise the stream is copied to the target, and the source file, when there is one, is removed afterwards.

| Situation | Exception |
| --- | --- |
| An empty target path | `InvalidUploadedFileException::emptyTargetPath()` |
| A target path with a null byte | `InvalidUploadedFileException::targetPathContainsNullByte()` |
| The upload failed | `UploadedFileException::uploadFailed()` |
| The file was already moved | `UploadedFileException::alreadyMoved()` |
| `move_uploaded_file()` failed | `UploadedFileException::unableToMove()` |
| The stream cannot be read, for example because it was detached | `UploadedFileException::unableToReadStream()` |
| The target cannot be opened for writing | `UploadedFileException::unableToOpenTarget()` |
| The stream stopped producing data, or raised a `RuntimeException` | `UploadedFileException::unableToReadStream()`, with the stream's exception as the previous one |
| Writing the target failed | `UploadedFileException::unableToWrite()` |
| The source could not be removed after copying | `UploadedFileException::unableToRemoveSource()` |

When copying fails, the partly written target is removed, and the exception's context holds the `targetPath`. An
exception the stream throws that already belongs to this package, such as an `InvalidStreamException`, is rethrown with
the `targetPath` added rather than wrapped. A wrapped exception's message is not copied, since it can contain anything;
when its code is a string SQLSTATE, as a `PDOException` reports, the context holds it under `sqlState`.
