---
id: error-handling
title: Error handling
sidebar_position: 10
description: The exception interface, the exception classes, diagnostic context, and logging.
---

## Catching exceptions

Every exception the package throws implements `Dirthara\Http\Exception\HttpException`, so one `catch` covers them all.
Each also extends the SPL exception that describes the failure: invalid input throws an `InvalidArgumentException`, and
an operation that failed while running throws a `RuntimeException`.

```php
use Dirthara\Http\Exception\HttpException;
use Dirthara\Http\Exception\InvalidRequestException;

try {
    $request = $request->withMethod($method);
} catch (InvalidRequestException $exception) {
    // the method was invalid
} catch (HttpException $exception) {
    // anything else this package raised
}
```

| Exception | Extends | Thrown for |
| --- | --- | --- |
| `InvalidMessageException` | `InvalidArgumentException` | Invalid header names and values, and protocol versions. |
| `InvalidRequestException` | `InvalidArgumentException` | Invalid methods, request targets, uploaded file trees, and parsed bodies. |
| `InvalidResponseException` | `InvalidArgumentException` | Invalid status codes and reason phrases. |
| `InvalidUriException` | `InvalidArgumentException` | URIs and components that are not valid. |
| `InvalidStreamException` | `InvalidArgumentException` | Invalid resources, modes, and filenames, and stream operations that cannot be done. |
| `InvalidUploadedFileException` | `InvalidArgumentException` | Invalid uploaded file details and target paths. |
| `StreamException` | `RuntimeException` | A file the stream factory cannot open. |
| `UploadedFileException` | `RuntimeException` | Uploaded files that failed, were already moved, or could not be moved. |

The pages for each class list which situation throws which exception. All exception classes are `final`; catch them by
class or by `HttpException`.

## Context

Every exception carries diagnostic metadata in its public, read-only `context` property, an `array<string, mixed>`.

```php
use Dirthara\Http\Exception\InvalidResponseException;

try {
    $response->withStatus(600);
} catch (InvalidResponseException $exception) {
    $context = $exception->context;
}
```

`$context` is `['statusCode' => 600]`. Code that catches an exception and knows more can add to it with `addContext()`,
which merges the given array into the context, replacing matching keys, and returns the exception:

```php
throw $exception->addContext(['route' => 'users.store']);
```

| Constructor parameter | Type | Default | Meaning |
| --- | --- | --- | --- |
| `message` | `string` | `''` | The exception message. |
| `code` | `int` | `0` | The exception code. |
| `previous` | `?Throwable` | `null` | The exception this one wraps. |
| `context` | `array<string, mixed>` | `[]` | The diagnostic context. |

## Sensitive values

Some values an HTTP message carries are credentials or personal data, so the package leaves them out:

- Header values are never in a message or context, only the header name.
- Request targets are left out entirely, because a query string can hold tokens.
- Parsed bodies are reduced to their type.

Other exceptions do include what they rejected. `InvalidUriException::forInvalidUri()` includes the whole URI, which can
hold a password or a token, and the stream and uploaded file exceptions include file paths.

## Logging

Exceptions do not log themselves or depend on a logger. An application handler can pass the context to a PSR-3 logger.
Add the caught exception under the `exception` key after reading the context, so an `exception` entry already in the
context cannot replace it.

```php
use Dirthara\Http\Exception\HttpException;

try {
    $response = $response->withHeader($name, $value);
} catch (HttpException $exception) {
    $context = $exception->context;
    $context['exception'] = $exception;

    $logger->warning($exception->getMessage(), $context);
}
```
