<?php declare(strict_types=1);

namespace Bref\SymfonyBridge\Test\Unit;

use Bref\Context\Context;
use Bref\Event\Http\Psr15Handler;
use Bref\SymfonyBridge\Http\KernelAdapter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;

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
        $handler = new Psr15Handler(new KernelAdapter($kernel));

        $handler->handle($this->uploadEvent(), Context::fake());
        $previousUpload = $uploadedFile;
        // Symfony moves the file Bref created to a new temporary file
        self::assertSame('Hello', file_get_contents($previousUpload));

        $handler->handle($this->uploadEvent(), Context::fake());

        self::assertFileNotExists($previousUpload);
        unlink($uploadedFile);
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
