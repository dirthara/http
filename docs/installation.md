---
id: installation
title: Installation
sidebar_position: 2
description: PHP requirements, Composer installation, and autoloading.
---

## Requirements

The package requires PHP `^8.5`: PHP 8.5 or a later PHP 8 release, with the `ctype` extension. Composer installs its two
runtime dependencies, the PSR interface packages it implements:

| Package | Provides |
| --- | --- |
| `psr/http-message` `^2.0` | The PSR-7 message interfaces. |
| `psr/http-factory` `^1.1` | The PSR-17 factory interfaces. |

## Install with Composer

For a published release, run:

```sh
composer require dirthara/http
```

Composer installs the package and registers the `Dirthara\Http` namespace with its autoloader. In a standalone
application, load that autoloader before using the package. Framework applications commonly load it during bootstrap.

```php
require 'vendor/autoload.php';

use Dirthara\Http\Factory\ResponseFactory;

$response = new ResponseFactory()->createResponse();
```

:::note
The Composer command requires a release to be available in your configured repositories. To work from a local checkout
before publication, configure a Composer path repository in the consuming application.
:::

Continue with [getting started](getting-started.md).
