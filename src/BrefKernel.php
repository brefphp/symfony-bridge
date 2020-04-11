<?php declare(strict_types=1);

namespace Bref\SymfonyBridge;

use Symfony\Component\Filesystem\Filesystem;
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
        if ($this->isLambda()) {
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

    /**
     * {@inheritDoc}
     */
    public function boot()
    {
        // When on the lambda, copy the cache dir over to /tmp where it is writable
        if ($this->isLambda() && ! is_dir($this->getCacheDir())) {
            $this->prepareCacheDir(parent::getCacheDir(), $this->getCacheDir());
        }

        return parent::boot();
    }

    protected function prepareCacheDir(string $staticCacheDir, string $tempCacheDir): void
    {
        $startTime = microtime(true);

        $filesystem = new Filesystem;
        $filesystem->mkdir($tempCacheDir);

        foreach (scandir($staticCacheDir, SCANDIR_SORT_NONE) as $item) {
            if (in_array($item, ['.', '..'])) {
                continue;
            }

            // the pools folder needs to be writable so mirror it
            if ($item === 'pools') {
                $filesystem->mirror("$staticCacheDir/$item", "$tempCacheDir/$item");
                continue;
            }

            // symlink all folders other folders
            // this is especially important with the Container* folder since it uses require_once statements
            if (is_dir("$staticCacheDir/$item")) {
                $filesystem->symlink("$staticCacheDir/$item", "$tempCacheDir/$item");
                continue;
            }

            // and copy all other files, i had intermediate problems when linking them
            $filesystem->copy("$staticCacheDir/$item", "$tempCacheDir/$item");
        }

        $this->logToStderr(sprintf(
            'Symfony cache directory prepared in %s ms.',
            number_format((microtime(true) - $startTime) * 1000, 2)
        ));
    }

    /**
     * This method logs to stderr.
     *
     * It must only be used in a lambda environment since all error output will be logged.
     *
     * @param string $message The message to log
     */
    protected function logToStderr(string $message): void
    {
        file_put_contents('php://stderr', date('[c] ') . $message . PHP_EOL, FILE_APPEND);
    }
}
