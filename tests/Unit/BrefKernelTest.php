<?php declare(strict_types=1);

namespace Bref\SymfonyBridge\Test\Unit;

use Bref\SymfonyBridge\Test\Fixtures\TestKernel;
use PHPUnit\Framework\TestCase;

class BrefKernelTest extends TestCase
{
    /** @var string|false */
    private $lambdaTaskRoot;

    /** @var string|false */
    private $appCacheDir;

    /** @var string|null */
    private $serverAppCacheDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->lambdaTaskRoot = getenv('LAMBDA_TASK_ROOT');
        $this->appCacheDir = getenv('APP_CACHE_DIR');
        $this->serverAppCacheDir = $_SERVER['APP_CACHE_DIR'] ?? null;

        putenv('LAMBDA_TASK_ROOT');
        putenv('APP_CACHE_DIR');
        unset($_SERVER['APP_CACHE_DIR']);
    }

    protected function tearDown(): void
    {
        $this->restoreEnvironmentVariable('LAMBDA_TASK_ROOT', $this->lambdaTaskRoot);
        $this->restoreEnvironmentVariable('APP_CACHE_DIR', $this->appCacheDir);

        if ($this->serverAppCacheDir === null) {
            unset($_SERVER['APP_CACHE_DIR']);
        } else {
            $_SERVER['APP_CACHE_DIR'] = $this->serverAppCacheDir;
        }

        parent::tearDown();
    }

    public function testIsLambda()
    {
        $kernel = new TestKernel;
        self::assertFalse($kernel->isLambda());

        putenv('LAMBDA_TASK_ROOT=/var/task');
        self::assertTrue($kernel->isLambda());
    }

    public function testItConfiguresSymfonyCacheDirOnLambda(): void
    {
        putenv('LAMBDA_TASK_ROOT=/var/task');

        new TestKernel;

        self::assertSame('/tmp/cache', $_SERVER['APP_CACHE_DIR']);
    }

    public function testItDoesNotConfigureSymfonyCacheDirOutsideLambda(): void
    {
        new TestKernel;

        self::assertArrayNotHasKey('APP_CACHE_DIR', $_SERVER);
    }

    public function testItDoesNotOverrideSymfonyCacheDir(): void
    {
        putenv('LAMBDA_TASK_ROOT=/var/task');
        $_SERVER['APP_CACHE_DIR'] = '/custom/cache';

        new TestKernel;

        self::assertSame('/custom/cache', $_SERVER['APP_CACHE_DIR']);
    }

    public function testItUsesConfiguredSymfonyCacheDirEnvironmentVariable(): void
    {
        putenv('LAMBDA_TASK_ROOT=/var/task');
        $_SERVER['APP_CACHE_DIR'] = '/custom/cache';

        new TestKernel;

        self::assertSame('/custom/cache', $_SERVER['APP_CACHE_DIR']);
    }

    /**
     * @param string|false $value
     */
    private function restoreEnvironmentVariable(string $name, $value): void
    {
        if ($value === false) {
            putenv($name);
        } else {
            putenv($name . '=' . $value);
        }
    }
}
