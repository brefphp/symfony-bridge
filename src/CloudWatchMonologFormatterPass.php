<?php declare(strict_types=1);

namespace Bref\SymfonyBridge;

use Bref\Monolog\CloudWatchFormatter;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Automatically sets the CloudWatch-optimized formatter on all Monolog handlers.
 *
 * Handlers that already have an explicit formatter configured are left unchanged,
 * allowing users to override per-handler in their monolog.yaml configuration.
 */
class CloudWatchMonologFormatterPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (! $container->has('bref.cloudwatch_formatter')) {
            $container->register('bref.cloudwatch_formatter', CloudWatchFormatter::class);
        }

        $formatterRef = new Reference('bref.cloudwatch_formatter');

        foreach ($container->getDefinitions() as $id => $definition) {
            if (! str_starts_with($id, 'monolog.handler.')) {
                continue;
            }

            // Don't override formatter if explicitly set by the user
            foreach ($definition->getMethodCalls() as [$method]) {
                if ($method === 'setFormatter') {
                    continue 2;
                }
            }

            $definition->addMethodCall('setFormatter', [$formatterRef]);
        }
    }
}
