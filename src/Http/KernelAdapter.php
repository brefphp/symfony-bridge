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
        $symfonyRequest = $this->symfonyFactory->createRequest($request);

        $symfonyResponse = $this->kernel->handle($symfonyRequest);

        if ($this->kernel instanceof TerminableInterface) {
            $this->kernel->terminate($symfonyRequest, $symfonyResponse);
        }

        return $this->psrFactory->createResponse($symfonyResponse);
    }
}
