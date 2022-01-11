[Bref](https://bref.sh/) runtime to run Symfony on AWS Lambda.

[![Build Status](https://github.com/brefphp/symfony-bridge/workflows/Tests/badge.svg)](https://github.com/brefphp/symfony-bridge/actions)
[![Latest Version](https://img.shields.io/packagist/v/bref/symfony-bridge?style=flat-square)](https://packagist.org/packages/bref/symfony-bridge)
[![Total Downloads](https://img.shields.io/packagist/dt/bref/symfony-bridge.svg?style=flat-square)](https://packagist.org/packages/bref/symfony-bridge)

## Installation

```cli
composer req bref/symfony-bridge
```

## Usage

You only need to do one small change to quickly setup Symfony to work with Bref.

```diff
// src/Kernel.php

namespace App;

+ use Bref\SymfonyBridge\BrefKernel;
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
bin/console cache:clear --env=prod

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

If you don't write to such cache pool you can optimize your setup by not copy the
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

## Handling requests in a kept-alive process without FPM

> Note: this is an advanced topic. Don't bother with this unless you know what you are doing.

To handle HTTP requests via the Symfony Kernel, without using PHP-FPM, by keeping the process alive:

```diff
# serverless.yml

functions:
    app:
-        handler: public/index.php
+        handler: App\Kernel
        layers:
            # Switch from PHP-FPM to the "function" runtime:
-            - ${bref:layer.php-80-fpm}
+            - ${bref:layer.php-80}
        environment:
            # The Symfony process will restart every 100 requests
            BREF_LOOP_MAX: 100
```

The `App\Kernel` will be retrieved via Symfony Runtime from `public/index.php`. If you don't have a `public/index.php`, read the next sections.

## Class handlers

To handle other events (e.g. [SQS messages with Symfony Messenger](https://github.com/brefphp/symfony-messenger)) via a class name:

```diff
# serverless.yml

functions:
    sqsHandler:
-        handler: bin/consumer.php
+        handler: App\Service\MyService
        layers:
            - ${bref:layer.php-80}
```

The service will be retrieved via Symfony Runtime from the Symfony Kernel returned by `public/index.php`.

> Note: the service must be configured as **public** (`public: true`) in the Symfony configuration.

### Custom bootstrap file

If you do not have a `public/index.php` file, you can create a file that returns the kernel (or any PSR-11 container):

```php
<?php

require_once dirname(__DIR__).'/vendor/autoload_runtime.php';

return function (array $context) {
    return new App\Kernel($context['APP_ENV'], (bool) $context['APP_DEBUG']);
};
```

And configure it in `serverless.yml`:

```diff
# serverless.yml
functions:
    sqsHandler:
        handler: kernel.php:App\Service\MyService
```
