<?php

namespace Bref\SymfonyBridge;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

abstract class Kernel extends BaseKernel
{
    public function isLambda(): bool
    {
        return getenv('LAMBDA_TASK_ROOT') !== false;
    }

    public function getCacheDir()
    {
        if ($this->isLambda()) {
            return '/tmp/cache/' . $this->environment;
        }

        return parent::getCacheDir();
    }

    public function getLogDir()
    {
        if ($this->isLambda()) {
            return '/tmp/log/';
        }

        return parent::getLogDir();
    }

    public function boot()
    {
        // When on the lambda, copy the cache dir over to /tmp where it is writable
        if ($this->isLambda() && !is_dir($this->getCacheDir())) {
            $this->prepareCacheDir(parent::getCacheDir(), $this->getCacheDir());
        }

        return parent::boot();
    }

    protected function prepareCacheDir(string $staticCacheDir, string $tempCacheDir): void
    {
        $filesystem = new Filesystem();
        $filesystem->mkdir($tempCacheDir);

        foreach (scandir($staticCacheDir, SCANDIR_SORT_NONE) as $item) {
            if (in_array($item, ['.', '..'])) {
                continue;
            }

            // then container must be linked because it uses require_once statements into the vendor folder
            if (substr($item, 0, 9) === 'Container') {
                $filesystem->symlink("$staticCacheDir/$item", "$tempCacheDir/$item");
            }

            // copy everything else

            if (is_dir("$staticCacheDir/$item")) {
                $filesystem->mirror("$staticCacheDir/$item", "$tempCacheDir/$item");
                continue;
            }

            $filesystem->copy("$staticCacheDir/$item", "$tempCacheDir/$item");
        }
    }
}
