<?php declare(strict_types=1);

namespace Bref\SymfonyBridge\Http;

use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Symfony\Bridge\PsrHttpMessage\Factory\HttpFoundationFactory;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\TerminableInterface;

/**
 * This turns a Symfony Kernel into a PSR-15 handler.
 *
 * That means the Symfony Kernel can now be used by Bref (which supports PSR-15)
 * to handle HTTP requests from API Gateway.
 */
class KernelAdapter implements RequestHandlerInterface
{
    private HttpKernelInterface $kernel;
    // PSR-15 to Symfony converters
    private HttpFoundationFactory $symfonyFactory;
    private PsrHttpFactory $psrFactory;

    public function __construct(HttpKernelInterface $kernel)
    {
        $this->kernel = $kernel;
        $this->symfonyFactory = new HttpFoundationFactory;
        $psr17Factory = new Psr17Factory;
        $this->psrFactory = new PsrHttpFactory($psr17Factory, $psr17Factory, $psr17Factory, $psr17Factory);
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        // From PSR-7 to Symfony
        $symfonyRequest = $this->symfonyFactory->createRequest($request);

        $symfonyResponse = $this->kernel->handle($symfonyRequest);

        if ($this->kernel instanceof TerminableInterface) {
            $this->kernel->terminate($symfonyRequest, $symfonyResponse);
        }

        // From Symfony to PSR-7
        return $this->psrFactory->createResponse($symfonyResponse);
    }
}
