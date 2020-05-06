This package configures Symfony to run on AWS Lambda using [Bref](https://bref.sh/).

[![Build Status](https://github.com/brefphp/symfony-bridge/workflows/Tests/badge.svg)](https://github.com/brefphp/symfony-bridge/actions)
[![Latest Version](https://img.shields.io/github/release/bref/symfony-bridge.svg?style=flat-square)](https://packagist.org/packages/bref/symfony-bridge)
[![Total Downloads](https://img.shields.io/packagist/dt/bref/symfony-bridge.svg?style=flat-square)](https://packagist.org/packages/bref/symfony-bridge)

## Installation

```cli
composer req bref/symfony-bridge
```

## Usage

You only need to one one small change To quickly setup Symfony to work with Bref.

```diff
// src/Kernel.php

namespace App;

use App\Repository\Test\TestRepository;
+ Bref\SymfonyBridge\BrefKernel;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\Config\Resource\FileResource;
use Symfony\Component\DependencyInjection\ContainerBuilder;
-use Symfony\Component\HttpKernel\Kernel as BaseKernel;
use Symfony\Component\Routing\RouteCollectionBuilder;

- class Kernel extends BaseKernel
+ class Kernel extends BrefKernel
{
    // ...
```

Now you are up and running.

## Optimize first request

The first HTTP request that hits your application after you deployed a new version
will use a cold cache directory. Symfony now spends time building thc cache. It may
take everything between 1-20 seconds depending on the complexity of the application.

Technically this happens whenever your application run on a new Lambda. That could
be when you get a lot more traffic so AWS increases the resources or when AWS just
decides to kill the lambda function (or server) that you are currently on. It is
normal that this happens at least a handful of times every day.

To optimize the first request, one must deploy the application with a warm cache.
In a simple application it means that the deploy script should include `cache:warmup`
to look something like this:

```bash
# Install dependencies
composer install --classmap-authoritative --no-dev --no-scripts

# Warmup the cache
bin/console cache:warmup --env=prod

# Disable use of Dotenv component
echo "<?php return [];" > .env.local.php

serverless deploy
```

## Optimize cache

When running Symfony on Lambda you should avoid writing to the filesystem. If
you prewarm the cache before deploy you are mostly fine. But you should also make
sure you never write to a filesystem cache like `cache.system` or use a pool like:

```yaml
framework:
    cache:
        pools:
            my_pool:
                adapter: cache.adapter.filesystem
```

If you dont write to such cache pool you can optimize your setup by not copy the
`var/cache/pools` directory. The change below will make sure to symlink the `pools`
directory.

```diff
// src/Kernel.php


class Kernel extends BrefKernel
{
    // ...

+    protected function getWritableCacheDirectories(): array
+    {
+        return [];
+    }
}
```
