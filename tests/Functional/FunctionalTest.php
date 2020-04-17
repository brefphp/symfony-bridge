<?php declare(strict_types=1);

namespace Bref\SymfonyBridge\Test\Functional;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

abstract class FunctionalTest extends TestCase
{
    /**
     * This is the directory in which the `/tmp` inside Lambda containers
     * will be stored for the tests. This will let us run successive commands and
     * simulate a persistent `/tmp` directory, just like inside AWS Lambda.
     * This will also let us check the content of that directory.
     */
    private const LOCAL_TMP_DIRECTORY = __DIR__ . '/App/tmp';

    public function setUp(): void
    {
        parent::setUp();
        // Make sure we start with an empty `/tmp` for each test
        (new Filesystem())->remove(self::LOCAL_TMP_DIRECTORY);
    }

    abstract public function test Symfony works(): void;

    protected function composerInstall(): void
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

    protected function clearCache(): void
    {
        (new Filesystem)->remove(__DIR__ . '/App/var/cache');
    }

    protected function warmupCache(): void
    {
        $composerInstall = new Process([
            'bin/console',
            'cache:warmup',
            '--env=prod',
        ]);
        $composerInstall->setWorkingDirectory(__DIR__ . '/App');
        $composerInstall->mustRun();
    }

    protected function runSymfonyConsole(string $command = 'app:ping'): Process
    {
        $projectRoot = dirname(__DIR__, 2);
        $phpVersion = getenv('PHP_VERSION');
        $phpVersion = $phpVersion === false ? '74' : str_replace('.', '', $phpVersion);

        $dockerCommand = new Process([
            'docker',
            'run',
            '--rm',
            '-v',
            // `:ro` means that the project is mounted as read-only
            $projectRoot . ':/var/task:ro',
            // Mount the `/tmp` directory to persist it between commands
            '-v',
            self::LOCAL_TMP_DIRECTORY . ':/tmp',
            '--entrypoint',
            'php',
            'bref/php-' . $phpVersion,
            // Run bin/console
            'tests/Functional/App/bin/console',
            '--env=prod',
            $command,
        ]);
        $dockerCommand->run();

        return $dockerCommand;
    }

    protected function assertCommandIsSuccessful(Process $command): void
    {
        $message = $command->getOutput() . PHP_EOL . $command->getErrorOutput();
        $this->assertTrue($command->isSuccessful(), $message);
        $this->assertStringNotContainsStringIgnoringCase('Warning', $message, $message);
        $this->assertStringNotContainsStringIgnoringCase('Error', $message, $message);
    }

    protected function assertCompiledContainerExistsInTmp(): void
    {
        $this->assertDirectoryExists(self::LOCAL_TMP_DIRECTORY . '/cache/prod');
    }

    protected function assertCompiledContainerDoesNotExistInTmp(): void
    {
        $this->assertDirectoryNotExists(self::LOCAL_TMP_DIRECTORY . '/cache/prod');
    }
}
