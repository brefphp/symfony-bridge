<?php declare(strict_types=1);

namespace Bref\SymfonyBridge;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\Kernel;

abstract class BrefKernel extends Kernel
{
    public function isLambda(): bool
    {
        return getenv('LAMBDA_TASK_ROOT') !== false;
    }

    public function getCacheDir(): string
    {
        if ($this->isLambda()) {
            return '/tmp/cache/' . $this->environment;
        }

        return parent::getCacheDir();
    }

    public function getLogDir(): string
    {
        if ($this->isLambda()) {
            return '/tmp/log/';
        }

        return parent::getLogDir();
    }

    /**
     * {@inheritDoc}
     *
     * When on the lambda, copy the cache dir over to /tmp where it is writable
     * We have to do this before Symfony does anything else with the Kernel
     * otherwise it might prematurely warm the cache before we can copy it
     *
     * @see https://github.com/brefphp/symfony-bridge/pull/37
     */
    public function handle($request, $type = HttpKernelInterface::MASTER_REQUEST, $catch = true): Response
    {
        $this->prepareCacheDir(parent::getCacheDir(), $this->getCacheDir());

        return parent::handle($request, $type, $catch);
    }

    /**
     * {@inheritdoc}
     *
     * We also need to prepare the Cache dir in Kernel::boot in case we are in a Console or worker context
     * in which Kernel::handle is not called.
     */
    public function boot()
    {
        $this->prepareCacheDir(parent::getCacheDir(), $this->getCacheDir());
        $this->ensureLogDir($this->getLogDir());

        parent::boot();
    }

    /**
     * Return the pre-warmed directories in var/cache/[env] that should be copied to
     * a writable directory in the Lambda environment.
     *
     * For optimal performance one should prewarm the cache folder and override this
     * function to return an empty array.
     */
    protected function getWritableCacheDirectories(): array
    {
        return ['pools'];
    }

    protected function prepareCacheDir(string $readOnlyDir, string $writeDir): void
    {
        if (! $this->isLambda() || is_dir($writeDir) || ! is_dir($readOnlyDir)) {
            return;
        }

        $startTime = microtime(true);
        $cacheDirectoriesToCopy = $this->getWritableCacheDirectories();
        $filesystem = new Filesystem;
        $filesystem->mkdir($writeDir);

        $scandir = scandir($readOnlyDir, SCANDIR_SORT_NONE);
        if ($scandir === false) {
            return;
        }

        foreach ($scandir as $item) {
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
     * Even though applications should never write into it on Lambda, there are parts of Symfony
     * (like "about" CLI command) that expect the log dir exists, so we have to make sure of it.
     *
     * @see https://github.com/brefphp/symfony-bridge/issues/42
     */
    private function ensureLogDir(string $writeLogDir): void
    {
        if (! $this->isLambda() || is_dir($writeLogDir)) {
            return;
        }

        $filesystem = new Filesystem;
        $filesystem->mkdir($writeLogDir);
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
