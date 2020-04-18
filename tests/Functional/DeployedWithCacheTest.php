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
        $this->assertCommandIsSuccessful($this->runSymfonyConsole());
    }

    public function test Symfony does not recompile the container(): void
    {
        $symfonyConsole = $this->runSymfonyConsole();
        $this->assertStringNotContainsString('Symfony is compiling the container', $symfonyConsole->getOutput());
        $this->assertCompiledContainerExistsInTmp();
    }

    public function test that the Symfony system cache can be written to(): void
    {
        $symfonyConsole = $this->runSymfonyConsole('write-to-cache');
        $this->assertCommandIsSuccessful($symfonyConsole);
        $this->assertStringContainsString('The cache was empty, writing entry `foo`', $symfonyConsole->getOutput());

        // On the second run, the cache should already exist
        // We make sure here that the cache is actually written to disk
        // (i.e. that it works instead of silently failing)
        $symfonyConsole = $this->runSymfonyConsole('write-to-cache');
        $this->assertCommandIsSuccessful($symfonyConsole);
        // We check that it does *NOT* contain the message this time
        $this->assertStringNotContainsString('The cache was empty, writing entry `foo`', $symfonyConsole->getOutput());
    }
}
