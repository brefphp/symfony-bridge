<?php declare(strict_types=1);

namespace Bref\SymfonyBridge\Test\Functional;

use Symfony\Component\Filesystem\Filesystem;

/**
 * Test /tmp/log is created
 */
class LogDirTest extends FunctionalTest
{
    public function setUp(): void
    {
        parent::setUp();
        $this->composerInstall();
        $this->clearTempLog();
    }

    private function clearTempLog(): void
    {
        (new Filesystem)->remove(self::LOCAL_TMP_DIRECTORY . '/log');
    }

    public function test Symfony works(): void
    {
        $this->assertCommandIsSuccessful($this->runHttpRequest());
    }

    public function test log dir created in tmp on http request(): void
    {
        $this->assertDirectoryNotExists(self::LOCAL_TMP_DIRECTORY . '/log');

        $this->runHttpRequest();
        $this->assertDirectoryExists(self::LOCAL_TMP_DIRECTORY . '/log');
    }

    public function test log dir created in tmp on console command(): void
    {
        $this->assertDirectoryNotExists(self::LOCAL_TMP_DIRECTORY . '/log');

        $this->runSymfonyConsole('about');
        $this->assertDirectoryExists(self::LOCAL_TMP_DIRECTORY . '/log');
    }
}
