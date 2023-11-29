<?php declare(strict_types=1);

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand('app:ping')]
class PingCommand extends Command
{
    protected static $defaultName = 'app:ping';

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('Ok');

        return 0;
    }
}
