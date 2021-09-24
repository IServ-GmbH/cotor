<?php

declare(strict_types=1);

namespace IServ\ComposerToolsInstaller\Command;

use IServ\ComposerToolsInstaller\Domain\Composer;
use IServ\ComposerToolsInstaller\Domain\Package;
use IServ\ComposerToolsInstaller\Domain\SemVer;
use Symfony\Component\Console\Command\Command;
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

    private const GITIGNORE = <<<GI
/vendor/
/composer.lock

GI;


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
            ->setDescription('Installs tools')
            ->setHelp('This command allows you to install composer based tools.')
            ->addArgument('name', InputArgument::OPTIONAL, 'The short name of the tool or its composer name. Leave empty to install tools from your composer.json.')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Force installation of the tool. Will remove current installation.')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $toolsDir = $this->getToolsDir();

        if (null !== $exitCode = $this->ensureToolsDir($toolsDir, $io)) {
            return $exitCode;
        }

        $name = (string)$input->getArgument('name');
        $force = (bool)$input->getOption('force');

        $composer = null;
        $composerPath = getcwd() . '/composer.json';
        if ($this->filesystem->exists($composerPath)) {
            try {
                $composer = new Composer(file_get_contents($composerPath));
            } catch (\JsonException $e) {
                $io->warning('Failed to parse composer.json: ' . $e->getMessage());
            }
        }

        // Check single package vs extras mode
        $useVersion = false;
        $tools = [];
        if ('' === $name) {
            if (null === $composer) {
                $io->error('No composer.json found!.');

                return Command::INVALID;
            }

            $composerJson = $composer->getJson();
            if (empty($composerJson['extras']['cotor'])) {
                $io->error('No tools specified in composer.json. Please give the short name of the tool or its composer name for installation.');

                return Command::INVALID;
            }

            /**
             * @var string $name
             * @var string $version
             */
            foreach ($composerJson['extras']['cotor'] as $name => $version) {
                $tools[] = Package::createFromComposerName($name, $version);
            }
            $useVersion = true;

            foreach ($tools as $package) {
                if (null === $this->installTool($package, $toolsDir, $io, $force, $useVersion)) {
                    $io->writeln(sprintf('<info>✓</info> %s installed successfully.', $package->getName()));
                }
            }
        } else {
            try {
                $package = $this->getPackage($name);
            } catch (\InvalidArgumentException $e) {
                $io->error(sprintf('Unknown tool %s!', $name));

                return Command::INVALID;
            }

            if (null !== $exitCode = $this->installTool($package, $toolsDir, $io, $force, $useVersion)) {
                return $exitCode;
            }

            $package = $this->getInstalledPackageVersion($toolsDir, $package, $io);

            if (null !== $composer) {
                $this->updateComposer($composer, $composerPath, $package, $io, $force);
            }

            $io->success(sprintf('%s installed successfully.', $package->getName()));
        }

        return Command::SUCCESS;
    }

    /**
     * Install given tool
     */
    protected function installTool(Package $package, string $toolsDir, SymfonyStyle $io, bool $force, bool $useVersion): ?int
    {
        $name = $package->getName(); // Use normalized name
        $targetDir = $toolsDir . '/' . $name;

        if (is_dir($targetDir)) {
            if ($force) {
                $this->filesystem->remove($targetDir);
            } else {
                $io->warning(sprintf('%s is already installed. You can update it with "cotor.phar update %1$s" or force a re-installation with the "--force" flag.', $name));

                return Command::INVALID;
            }
        }

        $this->filesystem->mkdir($targetDir);
        $this->filesystem->dumpFile($targetDir . '/.gitignore', self::GITIGNORE);

        try {
            $this->runComposerWithPackage('require', $targetDir, $package, $useVersion);
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
    protected function updateComposer(Composer $composer, string $composerPath, Package $package, SymfonyStyle $io, bool $replace = false): void
    {
        /** @var array{extras: array{cotor: array<string, string>}} $composerJson */
        $composerJson = $composer->getJson();
        if (!$replace && isset($composerJson['extras']['cotor'][$package->getComposerName()])) {
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

    /**
     * Checks if tools dir exists or asks user about creation.
     */
    protected function ensureToolsDir(string $toolsDir, SymfonyStyle $io): ?int
    {
        if (!is_dir($toolsDir)) {
            $question = new ConfirmationQuestion('There is no tools directory. Do you want to create it now?', false);

            if (!$io->askQuestion($question)) {
                return Command::SUCCESS;
            }

            $this->filesystem->mkdir($toolsDir);
        }

        return null;
    }

    protected function getInstalledPackageVersion(string $toolsDir, Package $package, SymfonyStyle $io): Package
    {
        // Get installed version
        $toolComposerLockName = $toolsDir . '/' . $package->getName() . '/composer.lock';
        if (!$this->filesystem->exists($toolComposerLockName)) {
            $io->warning(sprintf('Could not find composer.lock of %s!', $package->getName()));

            return $package;
        }

        $toolComposerLock = file_get_contents($toolComposerLockName);
        $toolComposerJson = (new Composer($toolComposerLock))->getJson();
        $toolVersion = '*';

        /** @var array<string, mixed> $installedPackage */
        foreach ($toolComposerJson['packages'] ?? [] as $installedPackage) {
            if ($installedPackage['name'] === $package->getComposerName()) {
                $toolVersion = (string)$installedPackage['version'];
            }
        }

        if ('*' !== $toolVersion) {
            try {
                $toolVersion = (new SemVer($toolVersion))->toMinorConstraint();
                $package = $package->withVersion($toolVersion);
            } catch (\InvalidArgumentException $e) {
                $io->warning(sprintf('Could not parse composer.lock of %s: %s', $package->getName(), $e->getMessage()));
            }
        }

        return $package;
    }
}
