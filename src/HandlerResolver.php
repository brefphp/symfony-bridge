<?php declare(strict_types=1);

namespace Bref\SymfonyBridge;

use Bref\Runtime\FileHandlerLocator;
use Bref\SymfonyBridge\Http\KernelAdapter;
use Exception;
use Psr\Container\ContainerInterface;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * This class resolves handlers.
 *
 * For example, if we configure `handler: xyz` in serverless.yml, then Bref
 * will call this class to resolve `xyz` into the real Lambda handler.
 */
class HandlerResolver implements ContainerInterface
{
    private ?ContainerInterface $symfonyContainer;
    private FileHandlerLocator $fileLocator;

    public function __construct()
    {
        // Bref's default handler resolver
        $this->fileLocator = new FileHandlerLocator;
        $this->symfonyContainer = null;
    }

    /**
     * {@inheritDoc}
     */
    public function get($id)
    {
        // By default we check if the handler is a file name (classic Bref behavior)
        if ($this->fileLocator->has($id)) {
            return $this->fileLocator->get($id);
        }

        // If not, we try to get the handler from the Symfony container
        $handler = $this->symfonyContainer()->get($id);

        // If the kernel was configured as a handler, then we wrap it to make it a valid HTTP handler for Lambda
        if ($handler instanceof HttpKernelInterface) {
            $handler = new KernelAdapter($handler);
        }

        return $handler;
    }

    /**
     * {@inheritDoc}
     */
    public function has($id): bool
    {
        return $this->fileLocator->has($id) || $this->symfonyContainer()->has($id);
    }

    /**
     * Create and return the Symfony container.
     */
    private function symfonyContainer(): ContainerInterface
    {
        // Only create it once
        if (! $this->symfonyContainer) {
            $kernelClass = $_SERVER['SYMFONY_KERNEL_CLASS'] ?? 'App\Kernel';
            if (! class_exists($kernelClass)) {
                throw new Exception(
                    <<<MESSAGE
                    Cannot find class '$kernelClass': the Bref-Symfony bridge needs to instantiate the Symfony kernel.
                    If your Symfony kernel has a class name that is not '$kernelClass', then set your kernel class name in the 'SYMFONY_KERNEL_CLASS' environment variable. Bref will use it to create the Symfony kernel.
                    MESSAGE
                );
            }

            // Sane defaults for running on AWS Lambda: prod and no debug
            // Can be overridden via the environment variables of course
            $env = $_SERVER['APP_ENV'] ?? 'prod';
            $debug = (bool) ($_SERVER['APP_DEBUG'] ?? false);

            // This is where the Symfony Kernel is created and booted
            $kernel = new $kernelClass($env, $debug);
            $kernel->boot();
            $this->symfonyContainer = $kernel->getContainer();
        }

        return $this->symfonyContainer;
    }
}
