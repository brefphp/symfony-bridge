<?php

namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class PingCommand extends Command
{
    protected static $defaultName = 'app:ping';

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln('Ok');

        return 0;
    }
}
