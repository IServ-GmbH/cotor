<?php

declare(strict_types=1);

namespace IServ\ComposerToolsInstaller\Command;

use IServ\ComposerToolsInstaller\Tools\ToolPath;
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

        $toolsDir = $this->getToolsDir();
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
        $legacyDir = $toolsDir . '/' . $name;
        $targetDir = ToolPath::create($toolsDir, $name);

        if (!is_dir($targetDir)) {
            if (is_dir($legacyDir)) {
                $targetDir = $legacyDir;
            } else {
                $io->error(sprintf('%s is not installed. You can install it with "cotor.phar install %s".', $name, $name));

                return Command::INVALID;
            }
        }

        try {
            $this->runComposer('update', $targetDir);
        } catch (ProcessFailedException $processFailedException) {
            $io->error('Failed to run composer: ' . $processFailedException->getProcess()->getErrorOutput());

            return Command::FAILURE;
        }

        $io->success(sprintf('%s updated successfully.', $name));

        return Command::SUCCESS;
    }
}
