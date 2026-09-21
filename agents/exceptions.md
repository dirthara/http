# Exceptions

Exceptions in Dirthara follow the same convention as PHP's built-in exceptions.
Each package defines one package-wide exception interface that extends
`Throwable`; in this package that is `Dirthara\Http\Exception\HttpException`.
Every exception the package throws implements it, so a caller catches
everything this package raises with a single type.

There is no base exception class. A concrete exception extends the SPL class
that describes the failure and implements the package interface alongside it:
`InvalidArgumentException` when a caller passed something the package cannot
accept, `RuntimeException` when the operation failed while running. Catching
`InvalidArgumentException` still catches these, and the package never asks a
caller to give up an inheritance slot it does not own.

Concrete exceptions are `final`. The interface is the extension point.

The shared behaviour lives in the `HasExceptionContext` trait, which holds a
protected `array<string, mixed> $context` and implements `getContext(): array`
and `addContext(array $context): static`. Every exception uses the trait and
accepts an optional `array<string, mixed> $context` as the fourth constructor
argument, after message, code, and previous, assigning it after the
`parent::__construct()` call.

Exceptions are built through named static factories, never with `new` at the
throw site, so every message and every piece of context for one failure is
written in one place. A factory passes its arguments by name — `message:`,
`previous:`, `context:` — and attaches context through the constructor rather
than chaining `addContext()`. Reserve `addContext()` for adding what a later
frame knows to an exception that already exists.

Exceptions carry data without logging themselves or depending on a logger package.
The application exception handler can pass `getContext()` to a PSR-3 logger,
adding the caught exception under the `exception` key. That key must contain the
caught exception even if context already contains an `exception` entry.

Include useful diagnostic metadata, such as the operation and what it acted
on. Do not include credentials or sensitive values in context. Make this
concrete for the package: name the identifiers its exceptions should carry and
the values they must never carry.

When a dependency can throw, catch the exception types it documents and rethrow
them as a specialized exception from this package. Pass the original as
`previous` and attach the operation's context. A caller should never need the
dependency in a `catch` block to handle a failure this package caused.

Catch the specific types, not `Throwable`. A `TypeError` or a `LogicException`
is a bug in this package rather than a failure of the operation, and turning one
into a domain exception hides it.

An exception that already implements this package's exception interface is not
rewrapped. Add what you know with `addContext()` and rethrow it, so its specific
type survives for the caller. An exception from another Dirthara package is
wrapped like any other dependency's: which package this one is built on is not
something its callers should have to catch.

A dependency's message can carry sensitive values or credentials. The prohibition on
credentials applies to the message as much as to the context, so do not copy one
verbatim without knowing what it can contain.
