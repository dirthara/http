---
id: status-codes
title: Status codes
sidebar_position: 5
description: Name status codes with the StatusCode enum, read their reason phrases, and sort them into classes.
---

`Dirthara\Http\StatusCode` is an `int`-backed enum with a case for every code in the IANA HTTP Status Code Registry. Use
it wherever a status code would otherwise be a bare number.

```php
use Dirthara\Http\StatusCode;
use Dirthara\Http\Factory\ResponseFactory;

$response = new ResponseFactory()->createResponse(StatusCode::Created);
$response = $response->withStatus(StatusCode::UnprocessableContent);
```

`Response`, `withStatus()`, and `ResponseFactory::createResponse()` all accept a case or an `int`. `getStatusCode()`
returns an `int`, as PSR-7 requires; turn it back into a case with `StatusCode::from()`, or `StatusCode::tryFrom()` for
a code that may not be registered.

## Cases and reason phrases

Case names are the registered reason phrases in PascalCase, and `reasonPhrase()` returns the phrase itself.

```php
StatusCode::NotFound->value;                     // 404
StatusCode::NotFound->reasonPhrase();            // 'Not Found'
StatusCode::HttpVersionNotSupported->reasonPhrase(); // 'HTTP Version Not Supported'
StatusCode::tryFrom(299);                         // null
```

| Range | Cases |
| --- | --- |
| 1xx | `Continue`, `SwitchingProtocols`, `Processing`, `EarlyHints` |
| 2xx | `Ok`, `Created`, `Accepted`, `NonAuthoritativeInformation`, `NoContent`, `ResetContent`, `PartialContent`, `MultiStatus`, `AlreadyReported`, `ImUsed` |
| 3xx | `MultipleChoices`, `MovedPermanently`, `Found`, `SeeOther`, `NotModified`, `UseProxy`, `TemporaryRedirect`, `PermanentRedirect` |
| 4xx | `BadRequest`, `Unauthorized`, `PaymentRequired`, `Forbidden`, `NotFound`, `MethodNotAllowed`, `NotAcceptable`, `ProxyAuthenticationRequired`, `RequestTimeout`, `Conflict`, `Gone`, `LengthRequired`, `PreconditionFailed`, `ContentTooLarge`, `UriTooLong`, `UnsupportedMediaType`, `RangeNotSatisfiable`, `ExpectationFailed`, `MisdirectedRequest`, `UnprocessableContent`, `Locked`, `FailedDependency`, `TooEarly`, `UpgradeRequired`, `PreconditionRequired`, `TooManyRequests`, `RequestHeaderFieldsTooLarge`, `UnavailableForLegalReasons` |
| 5xx | `InternalServerError`, `NotImplemented`, `BadGateway`, `ServiceUnavailable`, `GatewayTimeout`, `HttpVersionNotSupported`, `VariantAlsoNegotiates`, `InsufficientStorage`, `LoopDetected`, `NotExtended`, `NetworkAuthenticationRequired` |

:::note
The names follow the current registry and RFC 9110, so some differ from older frameworks: 413 is `ContentTooLarge`
rather than Payload Too Large, and 422 is `UnprocessableContent` rather than Unprocessable Entity. The registry marks
418 as unused, so there is no case for it. There are no aliases for the old names.
:::

## Classes

| Method | True for |
| --- | --- |
| `isInformational()` | 1xx |
| `isSuccessful()` | 2xx |
| `isRedirection()` | 3xx |
| `isClientError()` | 4xx |
| `isServerError()` | 5xx |
| `isError()` | 4xx and 5xx |

```php
use Dirthara\Http\StatusCode;

$status = StatusCode::tryFrom($response->getStatusCode());

if ($status?->isServerError()) {
    // retry later
}
```
