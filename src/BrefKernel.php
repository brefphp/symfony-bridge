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
    public function boot()
    {
        if ($this->isLambda()) {
            $this->mountOverlay($this->getProjectDir() . '/var', '/tmp/var');
        }

        return parent::boot();
    }

    protected function mountOverlay(string $lowerdir, string $upperdir): void
    {
        // I assume that if the upperdir exists, then it is already mounted
        if (is_dir($upperdir)) {
            return;
        }

        mkdir($upperdir);
        shell_exec(sprintf(
            'mount -t overlayfs -o %s %s',
            escapeshellarg('lowerdir=' . $lowerdir . ',upperdir=' . $upperdir),
            escapeshellarg($lowerdir)
        ));
    }
}
