---
id: server-requests
title: Server requests
sidebar_position: 3
description: Incoming requests with server parameters, cookies, query parameters, uploaded files, a parsed body, and attributes.
---

A `ServerRequest` is an incoming request as the server received it. It behaves like a [request](requests.md) and adds
the data a server has about it.

## Create a server request

```php
use Dirthara\Http\Uri;
use Dirthara\Http\ServerRequest;
use Dirthara\Http\Factory\StreamFactory;

$request = new ServerRequest(
    'GET',
    new Uri('https://example.com/users?page=2'),
    new StreamFactory()->createStream(),
    ['Accept' => 'text/html'],
    '1.1',
    $_SERVER,
);
```

The constructor takes the same parameters as `Request`, followed by one more:

| Parameter | Type | Default | Meaning |
| --- | --- | --- | --- |
| `serverParams` | `array` | `[]` | The server parameters, typically `$_SERVER`. They cannot be changed afterwards. |

Cookies, query parameters, uploaded files, the parsed body, and attributes start empty. Set them with the methods below.

:::note
Nothing is read from the server parameters or the URI. A server request created from `$_SERVER` does not take its
method, headers, or query parameters from it; pass those in explicitly.
:::

## Server data

| Read | Change | Holds |
| --- | --- | --- |
| `getServerParams()` | | The server parameters given to the constructor. |
| `getCookieParams()` | `withCookieParams(array $cookies)` | Cookies, typically `$_COOKIE`. |
| `getQueryParams()` | `withQueryParams(array $query)` | Query parameters, typically `$_GET`. |
| `getUploadedFiles()` | `withUploadedFiles(array $files)` | [Uploaded files](../uploaded-files.md). |
| `getParsedBody()` | `withParsedBody($data)` | The body after parsing, typically `$_POST` or decoded JSON. |

Query parameters are independent of the URI: `withQueryParams()` does not change the URI's query, and a new URI does not
change the query parameters.

### Uploaded files

The uploaded files are a tree of `UploadedFileInterface` instances, nested in arrays the way the form named them.

```php
use Dirthara\Http\Factory\StreamFactory;
use Dirthara\Http\Factory\UploadedFileFactory;

$files = new UploadedFileFactory();
$streams = new StreamFactory();

$request = $request->withUploadedFiles([
    'avatar' => $files->createUploadedFile($streams->createStreamFromFile('/tmp/php1')),
    'photos' => [
        $files->createUploadedFile($streams->createStreamFromFile('/tmp/php2')),
    ],
]);
```

Any other value in the tree throws `InvalidRequestException::invalidUploadedFile()`, naming the entry the way a form
field would, such as `photos[1]`.

### Parsed body

A parsed body is `null`, an array, or an object. Anything else throws `InvalidRequestException::invalidParsedBody()`,
whose context holds only the type of the value, because a body can hold passwords.

## Attributes

Attributes carry values an application derives from the request, such as route parameters or an authenticated user.

| Method | Behavior |
| --- | --- |
| `getAttributes()` | Every attribute by name. |
| `getAttribute($name, $default = null)` | An attribute, or `$default` when it does not exist. |
| `withAttribute($name, $value)` | Add or replace an attribute. |
| `withoutAttribute($name)` | Remove an attribute. Returns the same request when it does not exist. |

```php
$request = $request->withAttribute('userId', 7)->withAttribute('role', null);

$request->getAttribute('userId');
$request->getAttribute('role', 'guest');
$request->getAttribute('team', 'none');
```

These return `7`, `null`, and `'none'`. An attribute set to `null` exists, so its default is not used.
