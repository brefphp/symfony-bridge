<?php declare(strict_types=1);

namespace Bref\SymfonyBridge;

use Bref\SymfonyBridge\Doctrine\ConnectTimeoutMiddleware;
use Doctrine\DBAL\Driver\Middleware\AbstractDriverMiddleware;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * Named after the package so that Symfony Flex registers it in `config/bundles.php` when the package is installed:
 * without a recipe, Flex looks for `<Vendor><Package>Bundle`.
 */
class BrefSymfonyBridgeBundle extends Bundle
{
    public function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new CloudWatchMonologFormatterPass);

        // Registered when Doctrine DBAL 3.3+ is installed, the middleware only acts on Lambda (the container can be compiled before deploying)
        if (class_exists(AbstractDriverMiddleware::class)) {
            $container->register('bref.doctrine.connect_timeout_middleware', ConnectTimeoutMiddleware::class)
                ->addTag('doctrine.middleware');
        }
    }
}
