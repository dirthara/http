---
id: headers
title: Headers, protocol version, and body
sidebar_label: Headers and body
sidebar_position: 1
description: What requests, server requests, and responses share, and how headers are validated.
---

`Request`, `ServerRequest`, and `Response` share the same header, protocol version, and body behaviour. Every `with*()`
method on this page returns a changed copy and leaves the original unchanged.

## Headers

| Method | Behavior |
| --- | --- |
| `getHeaders()` | Every header, keyed by the name it was first given, each with a list of values. |
| `hasHeader($name)` | Whether a header exists, ignoring case. |
| `getHeader($name)` | The list of values for a header, or `[]` when it is missing. |
| `getHeaderLine($name)` | The values joined with `', '`, or `''` when it is missing. |
| `withHeader($name, $value)` | Replace a header. The copy stores it under the new name's case. |
| `withAddedHeader($name, $value)` | Append values to a header, keeping its existing case, or add it when it is missing. |
| `withoutHeader($name)` | Remove a header, ignoring case. Returns the same message when it is missing. |

A value is a string or an array of strings. Array keys are dropped.

```php
use Dirthara\Http\Factory\ResponseFactory;

$response = new ResponseFactory()->createResponse()
    ->withHeader('Cache-Control', 'no-store')
    ->withAddedHeader('cache-control', ['private', 'max-age=0'])
    ->withHeader('X-Debug', 'on')
    ->withoutHeader('x-debug');
```

`getHeaders()` returns `['Cache-Control' => ['no-store', 'private', 'max-age=0']]`, and `getHeaderLine('cache-control')`
returns `'no-store, private, max-age=0'`.

### Validation

| Input | Rule | Exception |
| --- | --- | --- |
| Constructor headers | Keyed by name. A list such as `['Accept: text/html']` holds header lines, not headers. | `InvalidMessageException::headersNotKeyedByName()` |
| Name | A token as defined by RFC 9110, listed below. | `InvalidMessageException::invalidHeaderName()` |
| Value type | A string or an array holding only strings. | `InvalidMessageException::invalidHeaderValueType()` |
| Empty array | An array must hold at least one value. | `InvalidMessageException::emptyHeaderValue()` |
| Value content | No control characters other than a horizontal tab, so no line breaks. | `InvalidMessageException::invalidHeaderValue()` |

A token is one or more ASCII letters, digits, or any of these symbols: ``!#$%&'*+-.^_`|~``. It cannot contain spaces,
colons, or anything outside ASCII.

Spaces and tabs at the start or end of a value are trimmed, because RFC 9110 does not count them as part of the value.
Tabs and spaces inside a value are kept.

:::caution
Refusing line breaks is what stops header injection: a value such as `"en\r\nSet-Cookie: admin=1"` would otherwise add a
header of its own. The exception names the header but never carries the rejected value, which may be a credential.
:::

:::note
PHP turns a numeric string array key into an integer, so a header named `"123"` comes back from `getHeaders()` under the
integer key `123`. Lookups by name still work: `getHeaderLine('123')` finds it.
:::

## Protocol version

`getProtocolVersion()` returns the version, `'1.1'` unless another was given. `withProtocolVersion($version)` changes
it. A version is one or more digits, optionally followed by a dot and more digits, such as `'1.0'`, `'2'`, or `'3'`.
Anything else, including `'HTTP/1.1'`, throws `InvalidMessageException::invalidProtocolVersion()`.

## Body

`getBody()` returns the body as a `StreamInterface`, and `withBody($body)` returns a copy with another one, or the same
message when it already has that body. Neither method copies the stream itself.

:::caution
A copy made by a `with*()` method shares its body with the original. Writing to the stream of one is visible through the
other. Give a message its own body with `withBody()` when the two must differ. See [streams](../streams.md).
:::
