---
id: streams
title: Streams
sidebar_position: 7
description: Message bodies backed by PHP stream resources, and what each operation does when it cannot.
---

`Dirthara\Http\Stream` wraps a PHP stream resource and is the body of every message. Create one with the
[stream factory](factories.md), or wrap a resource you already have:

```php
use Dirthara\Http\Stream;

$resource = fopen('php://temp', 'r+b');
$stream = new Stream($resource);
```

Anything other than a stream resource throws `InvalidStreamException::invalidResource()`.

Unlike messages, a stream is not immutable. Writing to it, reading from it, and seeking change the stream, and every
message that holds it sees the change.

## Capabilities

What a stream can do follows from the mode its resource was opened with.

| Method | True when |
| --- | --- |
| `isReadable()` | The mode starts with `r` or contains `+`. |
| `isWritable()` | The mode starts with `w`, `a`, `x`, or `c`, or contains `+`. |
| `isSeekable()` | PHP reports the resource as seekable. |

All three return `false` once the stream is detached or closed.

## Operations

| Method | Behavior |
| --- | --- |
| `read($length)` | Read up to `$length` bytes. `read(0)` returns `''`. |
| `getContents()` | Read everything from the current position to the end. |
| `write($string)` | Write at the current position and return the number of bytes written. |
| `tell()` | The current position. |
| `seek($offset, $whence)` | Move the position, with `SEEK_SET`, `SEEK_CUR`, or `SEEK_END`. |
| `rewind()` | Move to the start. |
| `eof()` | Whether the end has been reached. `true` once detached. |
| `getSize()` | The size in bytes, or `null` when it is unknown or the stream is detached. |
| `getMetadata($key)` | All metadata from `stream_get_meta_data()`, or one entry, or `null` for a missing key. |
| `detach()` | Return the resource and leave the stream unusable, without closing it. |
| `close()` | Detach the resource and close it. |
| `(string) $stream` | Rewind when seekable and return everything, or `''` on any failure. |

:::caution
Converting a stream to a string never throws, as PHP requires, so it returns `''` both for an empty stream and for one
that failed. Use `getContents()` when a failure must surface.
:::

## Failures

| Situation | Exception |
| --- | --- |
| Reading from a stream that is not readable, or a failed read | `InvalidStreamException::notReadable()` |
| Writing to a stream that is not writable, or a failed write | `InvalidStreamException::notWritable()` |
| Seeking in a stream that is not seekable | `InvalidStreamException::notSeekable()` |
| A seek PHP refuses | `InvalidStreamException::unableToSeek()` |
| A position PHP cannot report | `InvalidStreamException::unableToTell()` |
| A negative read length | `InvalidStreamException::invalidReadLength()` |
| Any operation on a detached or closed stream, except those that report it | `InvalidStreamException::detached()` |
