<?php declare(strict_types=1);

namespace Bref\SymfonyBridge;

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
    }
}
