<?php declare(strict_types=1);

namespace Bref\SymfonyBridge;

use Bref\Runtime\FileHandlerLocator;
use Bref\SymfonyBridge\Http\KernelAdapter;
use Exception;
use Psr\Container\ContainerInterface;
use Symfony\Component\HttpKernel\HttpKernelInterface;

class HandlerResolver implements ContainerInterface
{
    private ?ContainerInterface $symfonyContainer;
    private FileHandlerLocator $fileLocator;

    public function __construct()
    {
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

    private function symfonyContainer(): ContainerInterface
    {
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

            $env = $_SERVER['APP_ENV'] ?? 'prod';
            $debug = (bool) ($_SERVER['APP_DEBUG'] ?? false);

            $kernel = new $kernelClass($env, $debug);
            $kernel->boot();
            $this->symfonyContainer = $kernel->getContainer();
        }

        return $this->symfonyContainer;
    }
}
