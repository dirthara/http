---
id: uris
title: URIs
sidebar_position: 6
description: Parse, build, and normalize URIs, and what each component accepts.
---

`Dirthara\Http\Uri` parses a URI string into its components and builds one back with `(string) $uri`. Like the messages,
it is immutable: every `with*()` method returns a changed copy, or the same URI when nothing changes.

```php
use Dirthara\Http\Uri;

$uri = new Uri('HTTPS://user:secret@Example.COM:443/a b?q=1#top');

$uri->getScheme();    // 'https'
$uri->getAuthority(); // 'user:secret@example.com'
$uri->getHost();      // 'example.com'
$uri->getPort();      // null
$uri->getPath();      // '/a%20b'
(string) $uri;        // 'https://user:secret@example.com/a%20b?q=1#top'
```

`new Uri()` without an argument is an empty URI. A string `parse_url()` cannot parse throws
`InvalidUriException::forInvalidUri()`.

## Components

| Component | Read | Change | Normalization and rules |
| --- | --- | --- | --- |
| Scheme | `getScheme()` | `withScheme()` | Lowercased. Starts with a letter, then letters, digits, `+`, `-`, or `.`. |
| User info | `getUserInfo()` | `withUserInfo($user, $password)` | Percent-encoded. An empty user removes the user info, password included. |
| Host | `getHost()` | `withHost()` | Lowercased. A registered name, or an IPv6 or IPvFuture literal in brackets. |
| Port | `getPort()` | `withPort()` | From `1` to `65535`, or `null` for none. |
| Path | `getPath()` | `withPath()` | Percent-encoded. |
| Query | `getQuery()` | `withQuery()` | Percent-encoded, without the leading `?`. |
| Fragment | `getFragment()` | `withFragment()` | Percent-encoded, without the leading `#`. |

`getAuthority()` combines the user info, host, and port, and is empty when the URI has no host.

### Standard ports

`getPort()` returns `null` when the port is the standard one for the scheme, `80` for `http` and `443` for `https`, and
the authority and string leave it out. The port is kept, so changing the scheme can make it visible again.

```php
$uri = new Uri('http://example.com:443/');

$uri->getPort();                     // 443
$uri->withScheme('https')->getPort(); // null
```

### Percent-encoding

Characters a component may not contain are percent-encoded, and an existing encoded triplet such as `%2F` is kept as it
is, so encoding never happens twice. A `%` that does not start a triplet is encoded as `%25`.

### Building a string

When there is an authority, a path without a leading slash gets one. When there is none, a path starting with `//` is
reduced to a single slash, so it cannot be mistaken for an authority.

## Validation

| Input | Exception |
| --- | --- |
| A string `parse_url()` cannot parse | `InvalidUriException::forInvalidUri()` |
| An invalid scheme | `InvalidUriException::invalidScheme()` |
| An invalid host | `InvalidUriException::invalidHost()` |
| A bracketed host that is not IPv6 or IPvFuture, or is not closed | `InvalidUriException::invalidIpLiteralHost()` |
| A port outside `1` to `65535` | `InvalidUriException::invalidPort()` |

:::caution
`forInvalidUri()` puts the whole URI in its message and context, and a URI can carry a password in its user info or a
token in its query. Take that into account before logging these exceptions. `withUserInfo()` marks the password as a
sensitive parameter, so it does not appear in stack traces.
:::
