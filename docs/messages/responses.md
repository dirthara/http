---
id: responses
title: Responses
sidebar_position: 4
description: Create responses and set their status code and reason phrase.
---

## Create a response

```php
use Dirthara\Http\Response;
use Dirthara\Http\StatusCode;
use Dirthara\Http\Factory\StreamFactory;

$response = new Response(
    StatusCode::Ok,
    new StreamFactory()->createStream('<h1>Hello</h1>'),
    ['Content-Type' => 'text/html; charset=utf-8'],
);
```

| Parameter | Type | Default | Meaning |
| --- | --- | --- | --- |
| `statusCode` | `int\|StatusCode` | | The status code. |
| `body` | `StreamInterface` | | The response body. |
| `headers` | `array<string, string\|string[]>` | `[]` | Headers by name. |
| `protocolVersion` | `string` | `'1.1'` | The HTTP protocol version. |
| `reasonPhrase` | `string` | `''` | The reason phrase. Empty means the registered phrase for the code. |

The [response factory](../factories.md) creates a response with an empty body.

## Status code and reason phrase

`getStatusCode()` returns the code as an `int`, and `getReasonPhrase()` returns the reason phrase.
`withStatus($code, $reasonPhrase = '')` changes both, and accepts a [`StatusCode`](../status-codes.md) case or an `int`.

```php
use Dirthara\Http\StatusCode;

$notFound = $response->withStatus(StatusCode::NotFound);
$custom = $response->withStatus(StatusCode::NotFound, 'No Such User');
$unregistered = $response->withStatus(299);
```

The reason phrases are `'Not Found'`, `'No Such User'`, and `''`.

| Reason phrase given | Result |
| --- | --- |
| None, or `''` | The registered phrase for the code, or `''` for a code that is not registered. |
| Any other string | That string. |

Because an empty phrase always resolves to the registered one, changing only the code also replaces a custom phrase:
`withStatus(404)` after a custom `'No Such User'` gives `'Not Found'`.

### Validation

| Input | Rule | Exception |
| --- | --- | --- |
| Status code | From `100` to `599`. A `StatusCode` case is always valid. | `InvalidResponseException::invalidStatusCode()` |
| Reason phrase | No control characters other than a horizontal tab, so no line breaks. | `InvalidResponseException::invalidReasonPhrase()` |

A code does not have to be registered: `299` is accepted and gets an empty reason phrase.
