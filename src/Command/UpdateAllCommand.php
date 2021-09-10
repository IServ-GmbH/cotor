<?php

declare(strict_types=1);

namespace IServ\ComposerToolsInstaller\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\Exception\ProcessFailedException;

final class UpdateAllCommand extends AbstractToolCommand
{
    protected static $defaultName = 'update-all';

    protected function configure(): void
    {
        $this
            ->setDescription('Updates all tools')
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

        foreach (glob($toolsDir . '/*', GLOB_ONLYDIR) as $targetDir) {
            try {
                $this->runComposer('update', $targetDir);
                $io->writeln(sprintf('<info>✓</info> %s updated successfully.', substr(strrchr($targetDir, '/'), 1)));
            } catch (ProcessFailedException $e) {
                $io->writeln(sprintf('<error>✗</error> Failed to update %s.', substr(strrchr($targetDir, '/'), 1)));
            }
        }

        return Command::SUCCESS;
    }
}
