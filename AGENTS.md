# Project instructions

## Ownership
Dirthara owns this package. Attribute copyright, licensing, and authorship to `Dirthara` rather than to an individual
maintainer. The MIT `LICENSE` reads `Copyright (c) <year> Dirthara`, and new files or documents that name an owner use
the same name.

## Coding standards
Read and follow every [Dirthara coding standard](https://github.com/dirthara/coding-standards/tree/main/docs/coding-standards).
The sections below point at the ones that apply to a particular task, and add what is specific to this package.

## Branching
Every supported version has its own branch; there is no `main`. Target a feature at the newest release branch and a fix
at the earliest supported branch that has the bug, then forward-merge upward. Read [CONTRIBUTING.md](CONTRIBUTING.md)
and [CS-4](https://github.com/dirthara/coding-standards/blob/main/docs/coding-standards/cs-4-git-branching.md) before
branching, merging, or releasing.

## Committing
Never run `git commit`, `git push`, `git tag`, or anything else that writes to history or to the remote. Stage nothing
and commit nothing: the maintainer commits and pushes every change themselves. Leave the work in the working tree and
say what is ready.

## Tests
Line coverage of `src` must stay at 100%; `composer coverage` fails below it and lists the uncovered lines. Add tests in
`tests` with every implementation change. The empty scaffold explicitly skips tests and coverage until PHP files exist
in `src` or `tests`; after that, the full checks are required.

## Development
Use the PHP container for Composer and PHP commands; see [README.md](README.md). Use the `Dirthara\Http` namespace for
source and `Dirthara\Http\Tests` for tests. Declare strict types in every PHP file. This package needs no database, so
its image and `compose.yaml` carry none of the template's database drivers or services.

## Exceptions
Read and follow [CS-7](https://github.com/dirthara/coding-standards/blob/main/docs/coding-standards/cs-7-exceptions-error-handling.md)
when creating or modifying exceptions. The package interface is `Dirthara\Http\Exception\HttpException`, and every
exception uses the `HasExceptionContext` trait.

A message that quotes a rejected value passes it through the trait's `printable()`, which escapes control characters, so
a value holding a line break cannot forge a line in a log. The context keeps the value as it was given.

Exceptions carry header names but never header values, and leave out request targets and parsed bodies, which can hold
tokens and form input. Two exceptions knowingly break the rule for now, and the documentation says so:
`InvalidUriException::forInvalidUri()` quotes the whole URI and `StreamException::unableToOpen()` the whole filename,
either of which can hold user info with a password or a query with a token. Leave them as they are until the maintainer
decides whether to redact them.

## Documentation
Read and follow [CS-6](https://github.com/dirthara/coding-standards/blob/main/docs/coding-standards/cs-6-documentation.md)
when writing the README or anything in `docs`.

## Packaging
Read and follow [CS-8](https://github.com/dirthara/coding-standards/blob/main/docs/coding-standards/cs-8-packaging.md)
when changing what a release contains, the actions the CI workflow uses, or the dependency update configuration.
