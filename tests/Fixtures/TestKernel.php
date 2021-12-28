<?php declare(strict_types=1);

namespace Bref\SymfonyBridge\Test\Fixtures;

use Bref\SymfonyBridge\BrefKernel;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

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

    /**
     * @param ContainerBuilder|ContainerConfigurator $c
     * @param mixed $loader
     */
    protected function configureContainer($c, $loader = null): void
    {
        if ($c instanceof  ContainerBuilder) {
            $definition = new Definition(MyService::class);
            $definition->setPublic(true);
            $c->setDefinition(MyService::class, $definition);
        } else {
            $c->services()->set(MyService::class)->public();
        }
    }

    /**
     * This method is needed only for supporting lower Symfony versions
     *
     * @param mixed $routes
     */
    protected function configureRoutes($routes): void
    {
    }
}
