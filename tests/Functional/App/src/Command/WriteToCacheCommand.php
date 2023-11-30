<?php declare(strict_types=1);

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * This command tests writing to the system cache.
 */
#[AsCommand('write-to-cache')]
class WriteToCacheCommand extends Command
{
    /** @var CacheInterface */
    private $systemCache;

    public function __construct(CacheInterface $systemCache)
    {
        $this->systemCache = $systemCache;

        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->systemCache->get('foo', function () use ($output) {
            $output->writeln('The cache was empty, writing entry `foo`');
            return 'Hello world';
        });
        $this->systemCache->get('foo', function () {
            // Just to be sure the cache write did not silently fail above
            throw new \Exception('The item should have been cached');
        });

        return 0;
    }
}
