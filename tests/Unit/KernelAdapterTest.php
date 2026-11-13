<?php declare(strict_types=1);

namespace Bref\SymfonyBridge\Test\Unit;

use Bref\Context\Context;
use Bref\SymfonyBridge\Http\KernelAdapter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\TerminableInterface;

class KernelAdapterTest extends TestCase
{
    public function test files uploaded with the previous request are deleted()
    {
        $uploadedFile = null;
        $kernel = $this->createMock(HttpKernelInterface::class);
        $kernel->method('handle')->willReturnCallback(function (Request $request) use (&$uploadedFile) {
            $uploadedFile = $request->files->get('document')->getPathname();
            return new Response;
        });
        $handler = new KernelAdapter($kernel);

        $handler->handle($this->uploadEvent(), Context::fake())->contents();
        $previousUpload = $uploadedFile;
        // Symfony moves the file Bref created to a new temporary file
        self::assertSame('Hello', file_get_contents($previousUpload));

        $handler->handle($this->uploadEvent(), Context::fake())->contents();

        self::assertFileNotExists($previousUpload);
        unlink($uploadedFile);
    }

    public function test streamed responses are sent as they are produced()
    {
        $log = [];
        $kernel = $this->kernel(function () use (&$log) {
            return new StreamedResponse(function () use (&$log) {
                echo 'Hello';
                flush();
                $log[] = 'between';
                echo ' world';
            }, 201, ['X-Custom' => 'value']);
        }, $log);

        putenv('BREF_RESPONSE_STREAMING=1');
        try {
            $result = (new KernelAdapter($kernel))->handle($this->event('abc.lambda-url.eu-west-3.on.aws'), Context::fake());
            $chunks = [];
            $result->writeTo(function (string $chunk) use (&$chunks, &$log) {
                $chunks[] = $chunk;
                $log[] = 'chunk';
            }, function () use (&$log) {
                $log[] = 'end';
            });
        } finally {
            putenv('BREF_RESPONSE_STREAMING');
        }

        $metadata = json_decode(substr($chunks[0], 0, -8), true);
        self::assertSame(201, $metadata['statusCode']);
        self::assertSame('value', $metadata['headers']['X-Custom']);
        self::assertSame(['Hello', ' world'], array_slice($chunks, 1));
        // Sent as it is produced, then the client gets the end of the response, then the kernel terminates
        self::assertSame(['chunk', 'chunk', 'between', 'chunk', 'end', 'terminate'], $log);
    }

    public function test responses are buffered for other invokers()
    {
        $log = [];
        $kernel = $this->kernel(fn () => new StreamedResponse(function () {
            echo 'Hello';
            flush();
            echo ' world';
        }), $log);

        $response = json_decode((new KernelAdapter($kernel))->handle($this->event('abc.execute-api.eu-west-3.amazonaws.com'), Context::fake())->contents(), true);

        self::assertSame('Hello world', $response['body']);
        self::assertSame(['terminate'], $log);
    }

    public function test files are sent()
    {
        $file = tempnam(sys_get_temp_dir(), 'bref-test-');
        file_put_contents($file, 'File content');
        $log = [];
        $kernel = $this->kernel(fn () => (new BinaryFileResponse($file))->deleteFileAfterSend(), $log);

        $response = json_decode((new KernelAdapter($kernel))->handle($this->event('abc.execute-api.eu-west-3.amazonaws.com'), Context::fake())->contents(), true);

        self::assertSame('File content', $response['body']);
        self::assertFalse(file_exists($file));
    }

    /**
     * @param callable(): Response $handle
     * @param string[] $log
     */
    private function kernel(callable $handle, array &$log): HttpKernelInterface
    {
        return new class($handle, $log) implements HttpKernelInterface, TerminableInterface {
            /** @var callable */
            private $handle;
            private array $log;

            public function __construct(callable $handle, array &$log)
            {
                $this->handle = $handle;
                $this->log = &$log;
            }

            public function handle(Request $request, int $type = self::MAIN_REQUEST, bool $catch = true): Response
            {
                return ($this->handle)();
            }

            public function terminate(Request $request, Response $response): void
            {
                $this->log[] = 'terminate';
            }
        };
    }

    private function event(string $domain): array
    {
        return [
            'version' => '2.0',
            'rawPath' => '/',
            'requestContext' => [
                'domainName' => $domain,
                'http' => ['method' => 'GET', 'path' => '/', 'protocol' => 'HTTP/1.1', 'sourceIp' => '127.0.0.1'],
            ],
        ];
    }

    public function test the kernel runs in web mode()
    {
        unset($_SERVER['APP_RUNTIME_MODE']);
        try {
            new KernelAdapter($this->createMock(HttpKernelInterface::class));

            // E.g. errors are rendered as error pages, not for the console
            self::assertSame('web=1&worker=1', $_SERVER['APP_RUNTIME_MODE']);
        } finally {
            unset($_SERVER['APP_RUNTIME_MODE']);
        }
    }

    private function uploadEvent(): array
    {
        return [
            'version' => '2.0',
            'rawPath' => '/',
            'headers' => ['content-type' => 'multipart/form-data; boundary=boundary'],
            'body' => "--boundary\r\n"
                . "Content-Disposition: form-data; name=\"document\"; filename=\"document.txt\"\r\n"
                . "Content-Type: text/plain\r\n\r\n"
                . "Hello\r\n"
                . "--boundary--\r\n",
            'isBase64Encoded' => false,
            'requestContext' => [
                'http' => ['method' => 'POST', 'path' => '/', 'protocol' => 'HTTP/1.1', 'sourceIp' => '127.0.0.1'],
            ],
        ];
    }
}
