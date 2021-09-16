<?php

declare(strict_types=1);

namespace IServ\ComposerToolsInstaller\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

final class UpdateCommand extends AbstractToolCommand
{
    protected static $defaultName = 'update';

    protected function configure(): void
    {
        $this
            ->setDescription('Updates a tool')
            ->setHelp('This command allows you to update a composer based tool.')
            ->addArgument('name', InputArgument::REQUIRED, 'The short name of the tool or its composer name')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $name = (string)$input->getArgument('name');

        $toolsDir = getcwd() . '/tools';
        if (!is_dir($toolsDir)) {
            $io->error('There is no tools directory! Did you miss to install something first?');

            return Command::INVALID;
        }

        try {
            $package = $this->getPackage($name);
        } catch (\InvalidArgumentException $e) {
            $io->error(sprintf('Unknown tool %s!', $name));

            return Command::INVALID;
        }

        $name = $package->getName();
        $targetDir = $toolsDir . '/' . $name;

        if (!is_dir($targetDir)) {
            $io->error(sprintf('%s is not installed. You can install it with "cotor.phar install %s".', $name, $name));

            return Command::INVALID;
        }

        try {
            $this->runComposer('update', $targetDir);
        } catch (ProcessFailedException $e) {
            /** @var Process $process */
            $process = $e->getProcess(); // Make psalm happy :/
            $io->error('Failed to run composer: ' . $process->getErrorOutput());

            return Command::FAILURE;
        }

        $io->success(sprintf('%s updated successfully.', $name));

        return Command::SUCCESS;
    }
}
