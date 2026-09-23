---
id: getting-started
title: Getting started
sidebar_position: 3
description: Create requests and responses, change them, and read them back.
---

## Create messages with the factories

The [factories](factories.md) are the shortest way to a message. They give each message an empty, writable body.

```php
use Dirthara\Http\Factory\RequestFactory;
use Dirthara\Http\Factory\ResponseFactory;

$request = new RequestFactory()->createRequest('GET', 'https://example.com/users?page=2');
$response = new ResponseFactory()->createResponse();
```

`$request` has a `Host` header of `example.com` taken from the URI, and `$response` has status `200` with the reason
phrase `OK`.

## Change a message

Messages are immutable. Every `with*()` method returns a changed copy and leaves the original as it was.

```php
use Dirthara\Http\StatusCode;
use Dirthara\Http\Factory\ResponseFactory;

$response = new ResponseFactory()->createResponse();

$created = $response
    ->withStatus(StatusCode::Created)
    ->withHeader('Location', '/users/7')
    ->withHeader('Content-Type', 'application/json');

$created->getBody()->write('{"id":7}');
```

`$response` still has status `200` and no headers. `$created` has status `201`, the reason phrase `Created`, and both
headers. A body is a [stream](streams.md) rather than a value, so writing to it changes it for every message that shares
it.

## Read headers

Header names are case-insensitive.

```php
$type = $created->getHeaderLine('content-type');
$values = $created->getHeader('Location');
$present = $created->hasHeader('LOCATION');
```

`$type` is `'application/json'`, `$values` is `['/users/7']`, and `$present` is `true`.
[Headers, protocol version, and body](messages/headers.md) covers multiple values, adding, and removing.

## Use status codes by name

```php
use Dirthara\Http\StatusCode;

$status = StatusCode::from($created->getStatusCode());

$status->isSuccessful();
$status->reasonPhrase();
```

`$status` is `StatusCode::Created`, `isSuccessful()` is `true`, and `reasonPhrase()` is `'Created'`. See
[status codes](status-codes.md).

## Handle invalid input

Every exception the package throws implements `Dirthara\Http\Exception\HttpException`.

```php
use Dirthara\Http\Exception\HttpException;

try {
    $response->withHeader('X-Name', "value\r\nX-Injected: yes");
} catch (HttpException $exception) {
    $context = $exception->context;
}
```

`$context` is `['name' => 'X-Name']`. See [error handling](error-handling.md).
