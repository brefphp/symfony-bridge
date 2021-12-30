<?php declare(strict_types=1);

namespace Bref\SymfonyBridge\Test\Unit;

use Bref\Bref;
use Bref\SymfonyBridge\HandlerResolver;
use Bref\SymfonyBridge\Http\KernelAdapter;
use Bref\SymfonyBridge\Test\Fixtures\MyService;
use Bref\SymfonyBridge\Test\Fixtures\TestKernel;
use Closure;
use PHPUnit\Framework\TestCase;
use Psr\Http\Server\RequestHandlerInterface;

class HandlerResolverTest extends TestCase
{
    private string $cwd;

    public function setUp(): void
    {
        $this->cwd = getcwd();
        chdir(__DIR__);
    }

    public function tearDown(): void
    {
        chdir($this->cwd);
    }

    public function test Bref uses our handler resolver()
    {
        self::assertInstanceOf(HandlerResolver::class, Bref::getContainer());
    }

    public function test files are resolved()
    {
        $resolver = new HandlerResolver;
        self::assertTrue($resolver->has('fake-handler.php'));
        $fileHandler = $resolver->get('fake-handler.php');
        self::assertInstanceOf(Closure::class, $fileHandler);
        self::assertEquals('hello world', $fileHandler());
    }

    public function test Symfony services can be used as Lambda handlers()
    {
        $resolver = new HandlerResolver;
        self::assertInstanceOf(MyService::class, $resolver->get(MyService::class));
        self::assertTrue($resolver->has(MyService::class));
    }

    public function test the Symfony kernel can be used as a HTTP handler()
    {
        $resolver = new HandlerResolver;
        $handler = $resolver->get(TestKernel::class);
        self::assertInstanceOf(RequestHandlerInterface::class, $handler);
        self::assertInstanceOf(KernelAdapter::class, $handler);
    }
}
