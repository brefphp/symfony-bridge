<?php declare(strict_types=1);

namespace Bref\SymfonyBridge\Test\Unit;

use Bref\Monolog\CloudWatchFormatter;
use Bref\SymfonyBridge\CloudWatchMonologFormatterPass;
use Monolog\Handler\StreamHandler;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class CloudWatchMonologFormatterPassTest extends TestCase
{
    public function testSetsFormatterOnMonologHandlers()
    {
        $container = new ContainerBuilder;
        $container->register('monolog.handler.main', StreamHandler::class);
        $container->register('monolog.handler.console', StreamHandler::class);

        $pass = new CloudWatchMonologFormatterPass;
        $pass->process($container);

        self::assertTrue($container->has('bref.cloudwatch_formatter'));
        self::assertSame(CloudWatchFormatter::class, $container->getDefinition('bref.cloudwatch_formatter')->getClass());

        // Both handlers should have the formatter set
        $mainCalls = $container->getDefinition('monolog.handler.main')->getMethodCalls();
        self::assertCount(1, $mainCalls);
        self::assertSame('setFormatter', $mainCalls[0][0]);
        self::assertInstanceOf(Reference::class, $mainCalls[0][1][0]);
        self::assertSame('bref.cloudwatch_formatter', (string) $mainCalls[0][1][0]);

        $consoleCalls = $container->getDefinition('monolog.handler.console')->getMethodCalls();
        self::assertCount(1, $consoleCalls);
        self::assertSame('setFormatter', $consoleCalls[0][0]);
    }

    public function testDoesNotOverrideExplicitFormatter()
    {
        $container = new ContainerBuilder;

        $handler = $container->register('monolog.handler.main', StreamHandler::class);
        $handler->addMethodCall('setFormatter', [new Reference('my_custom_formatter')]);

        $pass = new CloudWatchMonologFormatterPass;
        $pass->process($container);

        // Should still have only the original formatter
        $calls = $container->getDefinition('monolog.handler.main')->getMethodCalls();
        self::assertCount(1, $calls);
        self::assertSame('my_custom_formatter', (string) $calls[0][1][0]);
    }

    public function testDoesNotTouchNonMonologServices()
    {
        $container = new ContainerBuilder;
        $container->register('app.my_service', StreamHandler::class);

        $pass = new CloudWatchMonologFormatterPass;
        $pass->process($container);

        self::assertEmpty($container->getDefinition('app.my_service')->getMethodCalls());
    }

    public function testDoesNothingWhenNoMonologHandlersExist()
    {
        $container = new ContainerBuilder;
        $container->register('app.my_service', StreamHandler::class);

        $pass = new CloudWatchMonologFormatterPass;
        $pass->process($container);

        // Formatter service should still be registered (it's harmless)
        self::assertTrue($container->has('bref.cloudwatch_formatter'));
    }
}
