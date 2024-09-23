<?php

declare(strict_types=1);

namespace IServ\ComposerToolsInstaller\Command;

use IServ\ComposerToolsInstaller\Tools\ToolPath;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\Exception\ProcessFailedException;

#[AsCommand(name: 'run-all', description: 'Run composer command on all tools')]
final class RunAllCommand extends AbstractToolCommand
{
    protected function configure(): void
    {
        $this
            ->setHelp('This command allows you to run any composer command on all installed tools.')
            ->addArgument('cmd', InputArgument::REQUIRED, 'Composer command to execute.')
            ->addUsage('validate')
            ->addUsage('check-platform-reqs')
            ->addUsage('update --lock')
        ;

        // Disable validation for dynamic option parameters.
        $this->ignoreValidationErrors();
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

        $argv = $_SERVER['argv'] ?? [];
        $customOptions = array_filter($argv, static fn (string $value): bool => str_starts_with($value, '--'));

        foreach ($targetDirs as $targetDir) {
            /** @var string $cmd */
            $cmd = $input->getArgument('cmd');

            $options = implode(', ', $customOptions);
            $command = "composer " . $cmd;

            try {
                if ('' !== $options) {
                    $this->runComposerWithArguments($cmd, $targetDir, $options);
                } else {
                    $this->runComposer($cmd, $targetDir);
                }
                $io->writeln(sprintf('<info>✓</info> "%s%s" on %s runs successfully.', $command, '' !== $options ? ' ' . $options : $options, ToolPath::path2name($targetDir)));
            } catch (ProcessFailedException $processFailedException) {
                $process = $processFailedException->getProcess();
                $io->writeln(
                    sprintf(
                        '<error>✗</error> Failed to run "%s%s" on %s: %s',
                        $command,
                        '' !== $options ? ' ' . $options : $options,
                        ToolPath::path2name($targetDir),
                        $process->getErrorOutput()
                    )
                );
            }
        }

        return Command::SUCCESS;
    }
}
