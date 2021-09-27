<?php declare(strict_types=1);

namespace Bref\SymfonyBridge\Test\Fixtures;

use Bref\SymfonyBridge\BrefKernel;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

class TestKernel extends BrefKernel
{
    use MicroKernelTrait;

    public function __construct()
    {
        parent::__construct('dev', true);
    }

    public function registerBundles(): array
    {
        return [
            new FrameworkBundle,
        ];
    }

    protected function configureContainer(ContainerConfigurator $c): void
    {
        $c->services()->set(MyService::class)->public();
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
    }
}
