<?php declare(strict_types=1);

namespace Bref\SymfonyBridge;

use Bref\Runtime\FileHandlerLocator;
use Bref\SymfonyBridge\Http\KernelAdapter;
use Bref\SymfonyBridge\Runtime\BrefRuntime;
use Exception;
use Psr\Container\ContainerInterface;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelInterface;

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
        $isComposed = strpos($id, ':') !== false;

        // By default we check if the handler is a file name (classic Bref behavior)
        if (! $isComposed && $this->fileLocator->has($id)) {
            return $this->fileLocator->get($id);
        }

        $service = $id;

        $bootstrapFile = null;
        if ($isComposed) {
            [$bootstrapFile, $service] = explode(':', $id, 2);
        }

        // If not, we try to get the handler from the Symfony container
        $handler = $this->symfonyContainer($bootstrapFile)->get($service);

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
        $isComposed = strpos($id, ':') !== false;

        // By default we check if the handler is a file name (classic Bref behavior)
        if (! $isComposed && $this->fileLocator->has($id)) {
            return true;
        }

        $service = $id;

        $bootstrapFile = null;
        if ($isComposed) {
            [$bootstrapFile, $service] = explode(':', $id, 2);
        }

        // If not, we try to get the handler from the Symfony container
        return $this->symfonyContainer($bootstrapFile)->has($service);
    }

    /**
     * Create and return the Symfony container.
     */
    private function symfonyContainer(?string $bootstrapFile = null): ContainerInterface
    {
        // Only create it once
        if (! $this->symfonyContainer) {
            $bootstrapFile = $bootstrapFile ?: 'public/index.php';

            if (! file_exists($bootstrapFile)) {
                throw new Exception(
                    "Cannot find file '$bootstrapFile': the Bref-Symfony bridge tried to require that file to get the Symfony kernel. If your application does not have that file, follow the Bref-Symfony documentation to create and configure a file that returns the Symfony Kernel."
                );
            }

            $app = require $bootstrapFile;

            if (! is_object($app)) {
                throw new Exception(sprintf(
                    "The '%s' file must return an anonymous function (that returns the Symfony Kernel). Instead it returned '%s'. Either edit the file to return an anonymous function, or create a separate file (follow the online documentation to do so).",
                    $bootstrapFile,
                    // @phpstan-ignore-next-line
                    is_object($app) ? get_class($app) : gettype($app),
                ));
            }

            $projectDir = getenv('LAMBDA_TASK_ROOT') ?: null;

            // Use the Symfony Runtime component to resolve the closure and get the PSR-11 container
            $options = $_SERVER['APP_RUNTIME_OPTIONS'] ?? [];
            if ($projectDir) {
                $options['project_dir'] = $projectDir;
            }
            $runtime = new BrefRuntime($options);

            [$app, $args] = $runtime
                ->getResolver($app)
                ->resolve();

            $container = $app(...$args);

            if ($container instanceof KernelInterface) {
                $container->boot();
                $container = $container->getContainer();
            }

            if (! $container instanceof ContainerInterface) {
                throw new Exception(sprintf(
                    "The closure returned by '%s' must return either a Symfony Kernel or a PSR-11 container. Instead it returned '%s'",
                    $bootstrapFile,
                    is_object($container) ? get_class($container) : gettype($container),
                ));
            }

            $this->symfonyContainer = $container;
        }

        return $this->symfonyContainer;
    }
}
