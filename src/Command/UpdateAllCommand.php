<?php

declare(strict_types=1);

namespace IServ\ComposerToolsInstaller\Command;

use IServ\ComposerToolsInstaller\Tools\ToolPath;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\Exception\ProcessFailedException;

#[AsCommand(name: 'update-all', description: 'Updates all tools')]
final class UpdateAllCommand extends AbstractToolCommand
{
    protected function configure(): void
    {
        $this
            ->setHelp('This command allows you to update all installed tools.')
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
            try {
                $this->runComposer('update', $targetDir);
                $io->writeln(sprintf('<info>✓</info> %s updated successfully.', ToolPath::path2name($targetDir)));
            } catch (ProcessFailedException) {
                $io->writeln(sprintf('<error>✗</error> Failed to update %s.', ToolPath::path2name($targetDir)));
            }
        }

        return Command::SUCCESS;
    }
}
