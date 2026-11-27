<?php declare(strict_types=1);

namespace Bref\SymfonyBridge\Http;

use Bref\Context\Context;
use Bref\Event\Http\HttpHandler;
use Bref\Event\Http\HttpRequestEvent;
use Bref\Event\Http\HttpResponseWriter;
use Bref\Event\Http\Psr7Bridge;
use Symfony\Bridge\PsrHttpMessage\Factory\HttpFoundationFactory;
use Symfony\Component\ErrorHandler\ErrorRenderer\HtmlErrorRenderer;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\HttpKernel\TerminableInterface;
use Throwable;

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

    public function handleRequest(HttpRequestEvent $event, Context $context, HttpResponseWriter $responseWriter): void
    {
        // Delete the files uploaded with the previous request: the Lambda instance is reused
        Psr7Bridge::cleanupUploadedFiles();

        $request = $this->symfonyFactory->createRequest(Psr7Bridge::convertRequest($event, $context));

        // Symfony < 6.1 sends streamed responses while the kernel handles the request (StreamedResponseListener):
        // that content is kept for the response, else the client would get an empty body
        ob_start();
        try {
            $response = $this->kernel->handle($request);
        } catch (Throwable $e) {
            ob_end_flush();
            throw $e;
        }
        $contentAlreadySent = (string) ob_get_clean();
        if (! $response instanceof StreamedResponse) {
            // Not the content of the response: printed like without the output buffer
            echo $contentAlreadySent;
            $contentAlreadySent = '';
        }

        $responseWriter->start($response->getStatusCode(), $response->headers->all());
        $responseWriter->write($contentAlreadySent);
        $contentError = null;
        try {
            $responseWriter->capture(fn () => $response->sendContent());
        } catch (Throwable $e) {
            // E.g. a streamed response that fails: too late for the kernel to render an error page.
            // It is too late for any page when part of the response was sent.
            if (! $responseWriter->reset()) {
                $this->terminate($request, $response);
                throw $e;
            }
            $contentError = $e;
            $this->respondWithErrorPage($e, $responseWriter);
        }

        try {
            // The client gets the response now: kernel.terminate listeners run after
            $responseWriter->end();
        } finally {
            // Also when the response could not end (e.g. it is too big)
            $this->terminate($request, $response);
        }

        // Thrown once the client got the error page, so that Bref logs it: the kernel did not see it
        if ($contentError) {
            throw $contentError;
        }
    }

    /**
     * The page Symfony's error handler shows when an exception escapes the kernel (e.g. with PHP-FPM).
     */
    private function respondWithErrorPage(Throwable $error, HttpResponseWriter $responseWriter): void
    {
        $debug = $this->kernel instanceof KernelInterface && $this->kernel->isDebug();
        $page = (new HtmlErrorRenderer($debug))->render($error);

        $responseWriter->start($page->getStatusCode(), $page->getHeaders());
        $responseWriter->write($page->getAsString());
    }

    private function terminate(Request $request, Response $response): void
    {
        if ($this->kernel instanceof TerminableInterface) {
            $this->kernel->terminate($request, $response);
        }
    }
}
