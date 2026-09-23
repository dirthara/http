# Security Policy

## Supported versions

| Version | Status |
| --- | --- |
| 0.1.x | Active development; unreleased |
| Older | Unsupported |

While the package is pre-1.0, only the latest release line receives fixes.

## Reporting a vulnerability

Report vulnerabilities privately using GitHub's
[Report a vulnerability](https://github.com/dirthara/http/security/advisories/new) form. Do not disclose vulnerabilities
in public issues or pull requests.

Include the affected version or commit, PHP version, a minimal reproduction, and the impact and conditions needed to
trigger the issue. Maintainers will acknowledge and assess the report. Confirmed fixes are published with an advisory
crediting the reporter unless they prefer otherwise.

## Scope

Report security issues in message, URI, stream, uploaded file, or factory behaviour, in exception handling, or in the
development configuration.

The package defends against malformed HTTP messages. It refuses header names that are not tokens, header values and
reason phrases containing line breaks or other control characters, and request targets containing whitespace, including
a target derived from another library's URI, so application input cannot split a message or inject a header. Methods,
protocol versions, status codes, and URI components are validated when they are set, and a parsed URI string gets the
same validation and percent-encoding as the `with*()` methods. Header values, request targets, and parsed bodies are
kept out of exception messages and context, and control characters in a quoted value are escaped. Whole URIs and
filenames are included, for diagnosis, and can hold credentials; see the error handling documentation.

The package does not decide what is safe to send or accept. It does not authenticate or authorize requests, limit their
size, sanitize bodies, or validate uploaded file contents. Client-supplied filenames and media types are returned as
given. Moving an uploaded file writes to the target path the application chooses. Applications remain responsible for
those boundaries.

Bugs in PHP or third-party dependencies should also be reported upstream. Application code and the sensitivity of data
an application chooses to store are the application's responsibility.
