<?php declare(strict_types=1);

namespace Bref\SymfonyBridge\Http;

use Bref\Context\Context;
use Bref\Event\Http\HttpHandler;
use Bref\Event\Http\HttpRequestEvent;
use Bref\Event\Http\HttpResponseWriter;
use Bref\Event\Http\Psr7Bridge;
use Symfony\Bridge\PsrHttpMessage\Factory\HttpFoundationFactory;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\TerminableInterface;

/**
 * Runs a Symfony Kernel as Bref's HTTP handler.
 *
 * The response is written like Symfony's front controller sends it: its content as it is produced
 * (streamed responses, files…), then the kernel terminates.
 */
class KernelAdapter extends HttpHandler
{
    private HttpKernelInterface $kernel;
    // PSR-7 to Symfony converter
    private HttpFoundationFactory $symfonyFactory;

    public function __construct(HttpKernelInterface $kernel)
    {
        $this->kernel = $kernel;
        // The kernel serves HTTP requests in a long-running process, like Symfony's FrankenPHP worker mode.
        // Without it, Symfony runs as a console application (PHP's CLI SAPI): e.g. errors would be rendered
        // for a terminal, with their details, instead of as error pages.
        $_SERVER['APP_RUNTIME_MODE'] ??= 'web=1&worker=1';
        $this->symfonyFactory = new class extends HttpFoundationFactory {
            /**
             * Symfony moves each uploaded file to a new temporary file: name it like Bref names uploads,
             * so that they are deleted when the next request starts.
             */
            protected function getTemporaryPath(): string
            {
                return tempnam(sys_get_temp_dir(), 'bref_upload_');
            }
        };
    }

    protected function respond(HttpRequestEvent $event, Context $context, HttpResponseWriter $writer): void
    {
        // Delete the files uploaded with the previous request: the Lambda instance is reused
        Psr7Bridge::cleanupUploadedFiles();

        $request = $this->symfonyFactory->createRequest(Psr7Bridge::convertRequest($event, $context));

        $response = $this->kernel->handle($request);

        $writer->start($response->getStatusCode(), $response->headers->all());
        $writer->capture(fn () => $response->sendContent());
        // The client gets the response now: kernel.terminate listeners run after
        $writer->end();

        if ($this->kernel instanceof TerminableInterface) {
            $this->kernel->terminate($request, $response);
        }
    }
}
