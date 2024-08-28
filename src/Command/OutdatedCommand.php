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

#[AsCommand(name: 'outdated', description: 'Check if tools are outdated or not')]
final class OutdatedCommand extends AbstractToolCommand
{
    protected function configure(): void
    {
        $this
            ->setHelp('This command allows you to check if tools are outdated or not')
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
                if ('' === $out = $this->runComposerWithArguments('outdated', $targetDir, '--direct')) {
                    $io->writeln(sprintf('<info>✓</info> %s is up-to-date.', ToolPath::path2name($targetDir)));
                } elseif (preg_match('#^1(?:.+?)/(?:.+?)\s(?P<current>.+?)\s.\s(?P<new>.+?)\s#', $out, $matches)) {
                    $io->writeln(sprintf('<comment>⚠</comment> %s is outdated: %s => %s', ToolPath::path2name($targetDir), $matches['current'], $matches['new']));
                } else {
                    $io->writeln(sprintf('<comment>⚠</comment> %s is outdated: %s', ToolPath::path2name($targetDir), trim($out)));
                }
            } catch (ProcessFailedException) {
                $io->writeln(sprintf('<error>✗</error> Failed to check if %s is outdated.', ToolPath::path2name($targetDir)));
            }
        }

        return Command::SUCCESS;
    }
}
