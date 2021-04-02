<?php declare(strict_types=1);

namespace Bref\SymfonyBridge\Test;

use Bref\SymfonyBridge\BrefKernel;
use Symfony\Component\Config\Loader\LoaderInterface;

class TestKernel extends BrefKernel
{
    public function __construct()
    {
        parent::__construct('prod', false);
    }

    public function registerBundles(): array
    {
        return [];
    }

    public function registerContainerConfiguration(LoaderInterface $loader)
    {
    }
}
