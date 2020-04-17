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
        $this->assertCommandIsSuccessful($symfonyConsole);
    }

    public function test Symfony compiles the container with an empty cache(): void
    {
        $this->composerInstall();
        $this->clearCache();
        $symfonyConsole = $this->runSymfonyConsole();
        $this->assertCommandIsSuccessful($symfonyConsole);
        $this->assertStringContainsString('Symfony is compiling the container', $symfonyConsole->getOutput());
    }

    public function test Symfony works with a compiled container(): void
    {
        $this->composerInstall();
        $this->clearCache();
        $this->warmupCache();
        $symfonyConsole = $this->runSymfonyConsole();
        $this->assertCommandIsSuccessful($symfonyConsole);
    }

    public function test Symfony does not recompile the container if the cache exists(): void
    {
        $this->composerInstall();
        $this->clearCache();
        $this->warmupCache();
        $symfonyConsole = $this->runSymfonyConsole();
        $this->assertStringNotContainsString('Symfony is compiling the container', $symfonyConsole->getOutput());
    }

    private function composerInstall(): void
    {
        $symfonyRequirement = getenv('SYMFONY_REQUIRE');
        $symfonyRequirement = $symfonyRequirement === false ? '4.4.*' : $symfonyRequirement;
        $composerInstall = new Process([
            'composer',
            'install',
            '--no-dev',
            '--no-interaction',
            '--prefer-dist',
            '--optimize-autoloader',
        ], null, [
            'SYMFONY_REQUIRE' => $symfonyRequirement,
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
            '--version',
        ]);
        $dockerCommand->run();

        return $dockerCommand;
    }

    private function assertCommandIsSuccessful(Process $command): void
    {
        $message = $command->getOutput() . PHP_EOL . $command->getErrorOutput();
        $this->assertTrue($command->isSuccessful(), $message);
        $this->assertStringNotContainsStringIgnoringCase('Warning', $message, $message);
        $this->assertStringNotContainsStringIgnoringCase('Error', $message, $message);
    }
}
