<?php declare(strict_types=1);

namespace Bref\SymfonyBridge;

use Symfony\Component\HttpKernel\Kernel;

abstract class BrefKernel extends Kernel
{
    public function isLambda(): bool
    {
        return getenv('LAMBDA_TASK_ROOT') !== false;
    }

    /**
     * {@inheritDoc}
     */
    public function getCacheDir()
    {
        // If we are running on Lambda and the `var/cache` directory does not exist,
        // we move it to `/tmp` because it is a directory we can write to.
        // That will allow us to compile the container there (which we can't do in `var/cache`).
        if ($this->isLambda() && !is_dir(parent::getCacheDir())) {
            return '/tmp/cache/' . $this->environment;
        }

        return parent::getCacheDir();
    }

    /**
     * {@inheritDoc}
     */
    public function getLogDir()
    {
        if ($this->isLambda()) {
            return '/tmp/log/';
        }

        return parent::getLogDir();
    }
}
