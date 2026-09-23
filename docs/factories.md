---
id: factories
title: Factories
sidebar_position: 9
description: The PSR-17 factories that create every message, URI, stream, and uploaded file.
---

The factories in `Dirthara\Http\Factory` implement the PSR-17 interfaces. Type against the interfaces in code that
creates messages, so another PSR-17 implementation can take their place. Every factory method returns the PSR interface.

| Factory | Implements | Creates |
| --- | --- | --- |
| `RequestFactory` | `RequestFactoryInterface` | [Requests](messages/requests.md) |
| `ServerRequestFactory` | `ServerRequestFactoryInterface` | [Server requests](messages/server-requests.md) |
| `ResponseFactory` | `ResponseFactoryInterface` | [Responses](messages/responses.md) |
| `StreamFactory` | `StreamFactoryInterface` | [Streams](streams.md) |
| `UriFactory` | `UriFactoryInterface` | [URIs](uris.md) |
| `UploadedFileFactory` | `UploadedFileFactoryInterface` | [Uploaded files](uploaded-files.md) |

## Requests and responses

```php
use Dirthara\Http\StatusCode;
use Dirthara\Http\Factory\RequestFactory;
use Dirthara\Http\Factory\ResponseFactory;
use Dirthara\Http\Factory\ServerRequestFactory;

$request = new RequestFactory()->createRequest('GET', 'https://example.com/');
$serverRequest = new ServerRequestFactory()->createServerRequest('POST', '/users', $_SERVER);
$response = new ResponseFactory()->createResponse(StatusCode::NoContent);
```

| Method | Parameters | Result |
| --- | --- | --- |
| `createRequest()` | A method, and a `UriInterface` or URI string. | A request with an empty body. |
| `createServerRequest()` | A method, a `UriInterface` or URI string, and server parameters, `[]` by default. | A server request with an empty body. The server parameters are stored, not read. |
| `createResponse()` | A status code as an `int` or `StatusCode`, `200` by default, and a reason phrase, `''` by default. | A response with an empty body. |

The empty body is a writable `php://temp` stream. These factories take the URI and stream factories they use as
constructor arguments, which default to the ones in this package:

```php
use Dirthara\Http\Factory\RequestFactory;

$factory = new RequestFactory(uriFactory: $otherUriFactory, streamFactory: $otherStreamFactory);
```

`ResponseFactory` takes only a stream factory.

## Streams

| Method | Result |
| --- | --- |
| `createStream($content = '')` | A readable, writable, seekable `php://temp` stream holding `$content`, positioned at the start. |
| `createStreamFromFile($filename, $mode = 'r')` | A stream for a file or stream URI, opened with `$mode`. |
| `createStreamFromResource($resource)` | A stream wrapping an existing resource. |

| Situation | Exception |
| --- | --- |
| A mode `fopen()` does not accept | `InvalidStreamException::invalidMode()` |
| An empty filename, or one with a null byte | `InvalidStreamException::invalidFilename()` |
| A file that cannot be opened, or a directory | `StreamException::unableToOpen()` |
| Something other than a stream resource | `InvalidStreamException::invalidResource()` |

A mode starts with `r`, `w`, `a`, `x`, or `c`, and can add `+` and the flags `b`, `t`, and `e`, such as `'r+b'` or
`'wb+'`.

## URIs and uploaded files

`createUri($uri = '')` parses a URI string, and throws `InvalidUriException` for one it cannot parse.

`createUploadedFile($stream, $size, $error, $clientFilename, $clientMediaType)` creates an uploaded file from a stream.
The size defaults to the stream's size, and the error to `UPLOAD_ERR_OK`. A stream that is not readable throws
`InvalidUploadedFileException::streamNotReadable()`.

:::note
A file created this way has no source path, so `moveTo()` copies its stream rather than moving the original upload.
Construct an [`UploadedFile`](uploaded-files.md) directly to move the file PHP stored.
:::
