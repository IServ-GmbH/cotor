<?php

declare(strict_types=1);

namespace IServ\ComposerToolsInstaller\Command;

use IServ\ComposerToolsInstaller\Tools\ToolPath;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\Exception\ProcessFailedException;

final class RunAllCommand extends AbstractToolCommand
{
    protected static $defaultName = 'run-all';

    protected function configure(): void
    {
        $this
            ->setDescription('Run composer command on all tools')
            ->setHelp('This command allows you to run composer command on all installed tools.')
            ->addArgument('arguments', InputArgument::REQUIRED | InputArgument::IS_ARRAY, 'Arguments (separate multiple arguments with a space).')
            ->addUsage('install')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $toolsDir = getcwd() . '/tools';
        if (!is_dir($toolsDir)) {
            $io->error('There is no tools directory! Did you miss to install something first?');

            return Command::INVALID;
        }

        $targetDirs = ToolPath::glob($toolsDir);

        if ([] === $targetDirs) {
            $io->warning('Could not find any tools! Did you miss to run `install`?');
        }

        foreach ($targetDirs as $targetDir) {
            /** @var list<string> $arguments */
            $arguments = $input->getArgument('arguments');
            $arguments = implode(' ', $arguments);
            $command = "composer " . $arguments;

            try {
                $this->runComposerWithArguments($arguments, $targetDir);
                $io->writeln(sprintf('<info>✓</info> "%s" on %s runs successfully.', $command, ToolPath::path2name($targetDir)));
            } catch (ProcessFailedException $processFailedException) {
                $io->writeln(sprintf('<error>✗</error> Failed to run "%s" on %s.', $command, ToolPath::path2name($targetDir)));
            }
        }

        return Command::SUCCESS;
    }
}
