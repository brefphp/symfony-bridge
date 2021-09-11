<?php declare(strict_types=1);

namespace Bref\SymfonyBridge;

use Symfony\Component\HttpKernel\Kernel;

abstract class BrefKernel extends Kernel
{
    use BrefKernelTrait;
}
