---
id: intro
title: Dirthara Http
sidebar_position: 1
description: PSR-7 HTTP messages, PSR-17 factories, and status codes for the Dirthara framework.
---

Dirthara Http implements the [PSR-7](https://www.php-fig.org/psr/psr-7/) HTTP message interfaces and the
[PSR-17](https://www.php-fig.org/psr/psr-17/) factories that create them. Any library that accepts PSR-7 messages can
work with these classes, and code written against the PSR interfaces can swap them for another implementation.

| Class | Implements | Represents |
| --- | --- | --- |
| `Request` | `RequestInterface` | An outgoing request, such as one an HTTP client sends. |
| `ServerRequest` | `ServerRequestInterface` | An incoming request as the server received it, with cookies, query parameters, uploaded files, a parsed body, and attributes. |
| `Response` | `ResponseInterface` | A response with a status code and reason phrase. |
| `Uri` | `UriInterface` | A URI, normalized and percent-encoded. |
| `Stream` | `StreamInterface` | A message body backed by a PHP stream resource. |
| `UploadedFile` | `UploadedFileInterface` | A file uploaded with a request. |

The `StatusCode` enum names every registered HTTP status code, so application code can write `StatusCode::NotFound`
instead of `404`. The `UploadError` enum does the same for PHP's upload error codes.

Every message is immutable. A `with*()` method never changes the object it is called on; it returns a changed copy, or
the same object when nothing changes. Always use the returned value.

The package validates what it is given. Header names and values, methods, request targets, status codes, reason phrases,
and URI components are checked when they are set, and anything that could split a message or inject a header is refused
with an exception.

Start with [installation](installation.md) and the [getting started guide](getting-started.md).
