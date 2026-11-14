<?php declare(strict_types=1);

namespace Bref\SymfonyBridge\Test\Functional;

/**
 * Test Symfony when it is deployed WITH the `var/cache` directory.
 */
class DeployedWithCacheTest extends FunctionalTest
{
    public function setUp(): void
    {
        parent::setUp();
        $this->composerInstall();
        $this->clearCache();
        // In this scenario, the cache is warmed up (and deployed in Lambda)
        $this->warmupCache();
    }

    public function test Symfony works(): void
    {
        $this->assertCommandIsSuccessful($this->runHttpRequest());
    }

    public function test Symfony does not recompile the container(): void
    {
        $symfonyConsole = $this->runHttpRequest();
        $this->assertStringNotContainsString('Symfony is compiling the container', $symfonyConsole->getOutput());
        $this->assertCompiledContainerExistsInTmp();
    }

    public function test Symfony does not recompile the container in console(): void
    {
        $symfonyConsole = $this->runSymfonyConsole('app:ping');
        $this->assertStringNotContainsString('Symfony is compiling the container', $symfonyConsole->getOutput());
        $this->assertCompiledContainerExistsInTmp();
    }

    public function test that the Symfony system cache can be written to(): void
    {
        $this->assertCacheCanBeWrittenTo('system');
    }

    public function test that the Symfony app cache can be written to(): void
    {
        $this->assertCacheCanBeWrittenTo('app');
    }
}
