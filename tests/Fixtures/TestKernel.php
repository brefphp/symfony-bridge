<?php declare(strict_types=1);

namespace Bref\SymfonyBridge\Test\Fixtures;

use Bref\SymfonyBridge\BrefKernel;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\ContainerBuilder;
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
     * @param ContainerConfigurator|ContainerBuilder $c
     */
    protected function configureContainer($c): void
    {
        $c->services()->set(MyService::class)->public();
    }
}
