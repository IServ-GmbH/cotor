<?php

declare(strict_types=1);

namespace IServ\ComposerToolsInstaller\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

final class InstallCommand extends AbstractToolCommand
{
    private const WRAPPER = <<<BASH
#!/bin/bash
# This file was created automatically by cotor.phar as a tool wrapper.

DIR=$(realpath "$(dirname "\${BASH_SOURCE[0]}")")

composer install --working-dir=\$DIR/%NAME% --quiet
exec \$DIR/%NAME%/vendor/bin/%NAME% "$@"

BASH;

    protected static $defaultName = 'install';

    /** @var Filesystem */
    protected $filesystem;

    public function __construct(Filesystem $filesystem)
    {
        $this->filesystem = $filesystem;

        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Installs a tool')
            ->setHelp('This command allows you to install a composer based tool.')
            ->addArgument('name', InputArgument::REQUIRED, 'The short name of the tool or its composer name')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Force installation of the tool. Will remove current installation.')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $name = (string)$input->getArgument('name');
        $force = (bool)$input->getOption('force');
        $io = new SymfonyStyle($input, $output);

        $toolsDir = $this->getToolsDir();
        if (!is_dir($toolsDir)) {
            /** @var QuestionHelper $helper */
            $helper = $this->getHelper('question');
            $question = new ConfirmationQuestion('There is no tools directory. Do you want to create it now?', false);

            if (!$helper->ask($input, $output, $question)) {
                return Command::SUCCESS;
            }

            $this->filesystem->mkdir($toolsDir);
        }

        try {
            [$vendor, $name] = $this->getVendorAndName($name);
        } catch (\InvalidArgumentException $e) {
            $io->error(sprintf('Unknown tool %s!', $name));

            return Command::INVALID;
        }

        $targetDir = $toolsDir . '/' . $name;

        if (is_dir($targetDir)) {
            if ($force) {
                $this->filesystem->remove($targetDir);
            } else {
                $io->warning(sprintf('%s is already installed. You can update it with "cotor.phar update phpstan" or force a re-installation with the "--force" flag.', $name));

                return Command::INVALID;
            }
        }

        $this->filesystem->mkdir($targetDir);

        try {
            $this->filesystem->dumpFile($targetDir . '/composer.json', '{}');
            $this->runComposerWithArguments('config', $targetDir, 'platform.php', '7.3.19'); // TODO: Remove PHP 8 workaround!
            $this->runComposerWithPackage('require', $targetDir, $vendor, $name);
        } catch (ProcessFailedException $e) {
            /** @var Process $process */
            $process = $e->getProcess(); // Make psalm happy :/
            $io->error('Failed to run composer: ' . $process->getErrorOutput());

            return Command::FAILURE;
        }

        // Create wrapper as tools/$name.phar to replace phive transparently
        $pharPath = sprintf('%s/%s.phar', $toolsDir, $name);
        if ($force && $this->filesystem->exists($pharPath)) {
            $this->filesystem->remove($pharPath);
        }

        if (!$this->filesystem->exists($pharPath)) {
            $this->filesystem->dumpFile($pharPath, str_replace('%NAME%', $name, self::WRAPPER));
            $this->filesystem->chmod($pharPath, 755);
        }

        $io->success(sprintf('%s installed successfully.', $name));

        return Command::SUCCESS;
    }
}
