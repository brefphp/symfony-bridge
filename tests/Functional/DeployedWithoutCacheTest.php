<?php declare(strict_types=1);

namespace Bref\SymfonyBridge\Test\Functional;

/**
 * Test Symfony when it is deployed WITHOUT the `var/cache` directory.
 */
class DeployedWithoutCacheTest extends FunctionalTest
{
    public function setUp(): void
    {
        parent::setUp();
        $this->composerInstall();
        $this->clearCache();
    }

    public function test Symfony works(): void
    {
        $this->assertCommandIsSuccessful($this->runHttpRequest());
    }

    public function test Symfony compiles the container in tmp(): void
    {
        $symfonyConsole = $this->runHttpRequest();
        $this->assertStringContainsString('Symfony is compiling the container', $symfonyConsole->getOutput());
        $this->assertCompiledContainerExistsInTmp();

        // We check that the container is not recompiled again
        $symfonyConsole = $this->runHttpRequest();
        $this->assertStringNotContainsString('Symfony is compiling the container', $symfonyConsole->getOutput());
    }

    public function test Symfony compiles the container in tmp in console(): void
    {
        $symfonyConsole = $this->runSymfonyConsole('app:ping');
        $this->assertStringContainsString('Symfony is compiling the container', $symfonyConsole->getOutput());
        $this->assertCompiledContainerExistsInTmp();

        // We check that the container is not recompiled again
        $symfonyConsole = $this->runSymfonyConsole('app:ping');
        $this->assertStringNotContainsString('Symfony is compiling the container', $symfonyConsole->getOutput());
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
