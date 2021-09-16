<?php

declare(strict_types=1);

namespace IServ\ComposerToolsInstaller\Command;

use IServ\ComposerToolsInstaller\Domain\Composer;
use IServ\ComposerToolsInstaller\Domain\Package;
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

        $composer = null;
        $composerPath = getcwd() . '/composer.json';
        if ($this->filesystem->exists($composerPath)) {
            try {
                $composer = new Composer(file_get_contents($composerPath));
            } catch (\JsonException $e) {
                $io->warning('Failed to parse composer.json: ' . $e->getMessage());
            }
        }

        try {
            $package = $this->getPackage($name);
        } catch (\InvalidArgumentException $e) {
            $io->error(sprintf('Unknown tool %s!', $name));

            return Command::INVALID;
        }

        if (null !== $exitCode = $this->installTool($package, $toolsDir, $io, $force)) {
            return $exitCode;
        }

        if (null !== $composer) {
            $this->updateComposer($composer, $composerPath, $package, $io);
        }

        $io->success(sprintf('%s installed successfully.', $name));

        return Command::SUCCESS;
    }

    /**
     * Install given tool
     */
    protected function installTool(Package $package, string $toolsDir, SymfonyStyle $io, bool $force): ?int
    {
        $name = $package->getName(); // Use normalized name
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
        $this->filesystem->dumpFile($targetDir . '/.gitignore', "/vendor\n");

        try {
            $this->filesystem->dumpFile($targetDir . '/composer.json', '{}');
            $this->runComposerWithArguments('config', $targetDir, 'platform.php', '7.3.19'); // TODO: Remove PHP 8 workaround!
            $this->runComposerWithPackage('require', $targetDir, $package);
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

        return null;
    }

    /**
     * Add tool to composer extras
     */
    protected function updateComposer(Composer $composer, string $composerPath, Package $package, SymfonyStyle $io): void
    {
        /** @var array{extras: array{cotor: array<string, string>}} $composerJson */
        $composerJson = $composer->getJson();
        if (isset($composerJson['extras']['cotor'][$package->getComposerName()])) {
            return;
        }

        $composerJson['extras']['cotor'][$package->getComposerName()] = $package->getVersion();
        ksort($composerJson['extras']['cotor']);
        try {
            $composer->setJson($composerJson);
            $this->filesystem->dumpFile($composerPath, $composer->toPrettyJsonString());
        } catch (\JsonException $e) {
            $io->warning('Failed to update composer.json: ' . $e->getMessage());
        }
    }
}
