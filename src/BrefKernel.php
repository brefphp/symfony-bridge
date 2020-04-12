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

    /**
     * Return the pre-warmed directories in var/cache/[env] that should be copied to
     * a writable directory in the Lambda environment.
     */
    protected function getWritableCacheDirectories(): array
    {
        return ['pools'];
    }

    protected function prepareCacheDir(string $readOnlyDir, string $writeDir): void
    {
        $startTime = microtime(true);
        $cacheDirectoriesToCopy = $this->getWritableCacheDirectories();

        $filesystem = new Filesystem;
        $filesystem->mkdir($writeDir);

        foreach (scandir($readOnlyDir, SCANDIR_SORT_NONE) as $item) {
            if (in_array($item, ['.', '..'])) {
                continue;
            }

            // Copy directories to a writable space on Lambda.
            if (in_array($item, $cacheDirectoriesToCopy)) {
                $filesystem->mirror("$readOnlyDir/$item", "$writeDir/$item");
                continue;
            }

            // Symlink all other directories
            // This is especially important with the Container* directories since it uses require_once statements
            if (is_dir("$readOnlyDir/$item")) {
                $filesystem->symlink("$readOnlyDir/$item", "$writeDir/$item");
                continue;
            }

            // Copy all other files.
            $filesystem->copy("$readOnlyDir/$item", "$writeDir/$item");
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
