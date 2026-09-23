---
id: requests
title: Requests
sidebar_position: 2
description: Create outgoing requests, and work with the method, URI, Host header, and request target.
---

A `Request` is an outgoing HTTP request, such as the one an HTTP client sends. For a request the server received, use a
[server request](server-requests.md), which behaves the same way and adds the server-side data.

## Create a request

```php
use Dirthara\Http\Uri;
use Dirthara\Http\Request;
use Dirthara\Http\Factory\StreamFactory;

$request = new Request(
    'POST',
    new Uri('https://api.example.com/users'),
    new StreamFactory()->createStream('{"name":"Ada"}'),
    ['Content-Type' => 'application/json'],
);
```

| Parameter | Type | Default | Meaning |
| --- | --- | --- | --- |
| `method` | `string` | | The request method, such as `'GET'`. |
| `uri` | `UriInterface` | | The URI the request is for. |
| `body` | `StreamInterface` | | The request body. |
| `headers` | `array<string, string\|string[]>` | `[]` | Headers by name. |
| `protocolVersion` | `string` | `'1.1'` | The HTTP protocol version. |

The [request factory](../factories.md) creates a request from a method and a URI or URI string, with an empty body.

## Method

`getMethod()` returns the method, and `withMethod($method)` changes it. The method keeps its case, because methods are
case-sensitive: `'get'` and `'GET'` are different methods. A method must be a token, the same character set as a
[header name](headers.md#validation); anything else, including an empty string, throws
`InvalidRequestException::invalidMethod()`.

## URI and the Host header

When a request is created, a URI with a host sets the `Host` header, including a non-standard port such as
`example.com:8080`, unless the headers already hold a non-empty `Host`. The `Host` header is placed first, as RFC 9112
asks.

`withUri($uri, $preserveHost)` changes the URI and updates `Host` to match:

| URI has a host | `preserveHost` | Request has a non-empty `Host` | Result |
| --- | --- | --- | --- |
| Yes | `false` | Either | `Host` is set from the new URI. |
| Yes | `true` | Yes | `Host` is kept. |
| Yes | `true` | No | `Host` is set from the new URI. |
| No | Either | Either | `Host` is kept. |

A `Host` header set from the URI keeps any case the request already uses for it, such as `host`, and moves to the front
of the headers.

## Request target

The request target is what goes on the request line, such as `/users?page=2`. Unless one was set, `getRequestTarget()`
derives it from the URI: the path, or `/` when the path is empty, followed by `?` and the query when there is one.

`withRequestTarget($target)` sets it explicitly, for the other forms HTTP allows, such as `*` for `OPTIONS` or
`example.com:443` for `CONNECT`. An explicit target stays in place when the URI changes.

```php
$options = $request->withMethod('OPTIONS')->withRequestTarget('*');
```

A target cannot be empty or contain whitespace or control characters; otherwise
`InvalidRequestException::invalidRequestTarget()` is thrown.

:::note
The exception for an invalid request target carries neither the target in its message nor any context, because a
target's query string can hold tokens.
:::
