<?php declare(strict_types=1);

namespace Bref\SymfonyBridge\Test\Functional;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

class FunctionalTest extends TestCase
{
    public function test Symfony works with an empty cache(): void
    {
        $this->composerInstall();
        $this->clearCache();
        $symfonyConsole = $this->runSymfonyConsole();
        $this->assertTrue($symfonyConsole->isSuccessful());
    }

    public function test Symfony works with a compiled container(): void
    {
        $this->composerInstall();
        $this->clearCache();
        $this->warmupCache();
        $symfonyConsole = $this->runSymfonyConsole();
        $this->assertTrue($symfonyConsole->isSuccessful());
    }

    private function composerInstall(): void
    {
        $composerInstall = new Process([
            'composer',
            'install',
            '--no-dev',
            '--no-interaction',
            '--prefer-dist',
            '--optimize-autoloader',
        ]);
        $composerInstall->setWorkingDirectory(__DIR__ . '/App');
        $composerInstall->mustRun();
    }

    private function clearCache(): void
    {
        (new Filesystem)->remove(__DIR__ . '/App/var/cache');
    }

    private function warmupCache(): void
    {
        $composerInstall = new Process([
            'bin/console',
            'cache:warmup',
            '--env=prod',
        ]);
        $composerInstall->setWorkingDirectory(__DIR__ . '/App');
        $composerInstall->mustRun();
    }

    private function runSymfonyConsole(): Process
    {
        $projectRoot = dirname(__DIR__, 2);

        $dockerCommand = new Process([
            'docker',
            'run',
            '--rm',
            '-v',
            // `:ro` means that the project is mounted as read-only
            $projectRoot . ':/var/task:ro',
            '--entrypoint',
            'php',
            'bref/php-74',
            // Run bin/console
            'tests/Functional/App/bin/console',
        ]);
        $dockerCommand->mustRun();

        return $dockerCommand;
    }
}
