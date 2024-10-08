<?php

declare(strict_types=1);

namespace IServ\ComposerToolsInstaller\Command;

use IServ\ComposerToolsInstaller\Domain\Composer;
use IServ\ComposerToolsInstaller\Domain\Cotor;
use IServ\ComposerToolsInstaller\Domain\Package;
use IServ\ComposerToolsInstaller\Tools\ToolPath;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

#[AsCommand(name: 'install', description: 'Installs tools')]
final class InstallCommand extends AbstractComposerCommand
{
    private const WRAPPER = <<<BASH_WRAP
#!/bin/bash
# This file was created automatically by cotor as a tool wrapper.

DIR=\$(realpath "\$(dirname "\${BASH_SOURCE[0]}")")

composer install --working-dir=\$DIR/.%NAME% --quiet
exec \$DIR/.%NAME%/vendor/bin/%NAME% "\$@"

BASH_WRAP;

    private const GITIGNORE = <<<GI
/vendor/

GI;

    private const GITIGNORE_NO_LOCK = <<<GI
/vendor/
/composer.lock

GI;

    protected function configure(): void
    {
        $this
            ->setHelp('This command allows you to install composer based tools.')
            ->addArgument('name', InputArgument::OPTIONAL, 'The short name of the tool or its composer name. Leave empty to install tools from your composer.json.')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Force installation of the tool. Will remove current installation.')
            ->addOption('no-lock', null, InputOption::VALUE_NONE, 'Install without Composer\'s lock file. The tool will always use the latest bugfix release.')
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
        $noLock = (bool)$input->getOption('no-lock');

        $composer = null;
        $composerPath = getcwd() . '/composer.json';
        if ($this->filesystem->exists($composerPath)) {
            try {
                $composer = new Composer(file_get_contents($composerPath));
            } catch (\JsonException $e) {
                $io->warning('Failed to parse composer.json: ' . $e->getMessage());
            }
        }

        // Fix stupid earlier me
        if (null !== $composer) {
            $composerJson = $composer->getJson();
            /** @var array{extras?: array{cotor: array<string, string>}} $composerJson */
            if (isset($composerJson['extras'][Cotor::COMPOSER_EXTRA])) {
                $composerJson['extra'][Cotor::COMPOSER_EXTRA] = $composerJson['extras'][Cotor::COMPOSER_EXTRA];
                unset($composerJson['extras']);
                try {
                    $composer->setJson($composerJson);
                    $this->filesystem->dumpFile($composerPath, $composer->toPrettyJsonString());
                } catch (\JsonException $e) {
                    $io->warning('Failed to fix composer.json: ' . $e->getMessage());
                }
            }
            unset($composerJson);
        }

        // Check single package vs extra mode
        $tools = [];
        if ('' === $name) {
            if (null === $composer) {
                $io->error('No composer.json found!.');

                return Command::INVALID;
            }

            $composerJson = $composer->getJson();
            if (empty($composerJson['extra'][Cotor::COMPOSER_EXTRA])) {
                $io->error('No tools specified in composer.json. Please give the short name of the tool or its composer name for installation.');

                return Command::INVALID;
            }

            /**
             * @var string $name
             * @var string $version
             */
            foreach ($composerJson['extra'][Cotor::COMPOSER_EXTRA] as $name => $version) {
                if (Cotor::COMPOSER_EXTRA_EXTENSIONS === $name) {
                    continue;
                }

                $tools[] = Package::createFromComposerName($name, $version);
            }

            foreach ($tools as $package) {
                if (null === $this->installTool($package, $toolsDir, $io, $force, true, $noLock)) {
                    $io->writeln(sprintf('<info>✓</info> %s installed successfully.', $package->getName()));
                }
            }

            // Install extensions
            /** @var array{extra: array{cotor: array{extensions?: array<string, array<string, string>>}}} $composerJson */
            if (!empty($composerJson['extra'][Cotor::COMPOSER_EXTRA][Cotor::COMPOSER_EXTRA_EXTENSIONS])) {
                /**
                 * Psalm why ya so nit-picky??!
                 *
                 * @psalm-suppress MixedArrayAccess
                 * @var string $toolName
                 * @var array<string, string> $packages
                 */
                foreach ($composerJson['extra'][Cotor::COMPOSER_EXTRA][Cotor::COMPOSER_EXTRA_EXTENSIONS] as $toolName => $packages) {
                    if (empty($packages)) {
                        $io->writeln(sprintf('<alert>❗</alert> %s has empty extensions configuration.', $toolName));

                        continue;
                    }

                    $package = Package::createFromComposerName($toolName);
                    foreach ($packages as $name => $version) {
                        $extension = Package::createFromComposerName($name, $version);
                        if (null === $this->installExtension($package, $extension, $toolsDir, $io)) {
                            $io->writeln(sprintf('<info>✓</info> Extension %s for %s installed successfully.', $extension->getName(), $package->getVendor()));
                        }
                    }
                }
            }

            return Command::SUCCESS;
        }

        // Single Package Mode
        try {
            $package = $this->getPackage($name);
        } catch (\InvalidArgumentException) {
            $io->error(sprintf('Unknown tool %s!', $name));

            return Command::INVALID;
        }

        if (null !== $exitCode = $this->installTool($package, $toolsDir, $io, $force, false, $noLock)) {
            return $exitCode;
        }

        $package = $this->getInstalledPackageVersion($toolsDir, $package, $io);

        if (null !== $composer) {
            $this->updateComposer($composer, $composerPath, $package, $io, $force);
        }

        $io->success(sprintf('%s installed successfully.', $package->getName()));

        return Command::SUCCESS;
    }

    /**
     * Install given tool
     */
    private function installTool(Package $package, string $toolsDir, SymfonyStyle $io, bool $force, bool $useVersion, bool $noLock): ?int
    {
        $name = $package->getName(); // Use normalized name
        $legacyDir = $toolsDir . DIRECTORY_SEPARATOR . $name;
        $targetDir = ToolPath::create($toolsDir, $name);

        // Remove legacy dirs on demand
        if (is_dir($legacyDir)) {
            $this->filesystem->remove($legacyDir);
        }

        if (is_dir($targetDir)) {
            if ($force) {
                $this->filesystem->remove($targetDir);
            } else {
                $io->warning(sprintf('%s is already installed. You can update it with "cotor.phar update %1$s" or force a re-installation with the "--force" flag.', $name));

                return Command::INVALID;
            }
        }

        $this->filesystem->mkdir($targetDir);
        if ($noLock) {
            $this->filesystem->dumpFile($targetDir . DIRECTORY_SEPARATOR . '.gitignore', self::GITIGNORE_NO_LOCK);
        } else {
            $this->filesystem->dumpFile($targetDir . DIRECTORY_SEPARATOR . '.gitignore', self::GITIGNORE);
        }

        try {
            $this->runComposerWithPackage('require', $targetDir, $package, $useVersion);
        } catch (ProcessFailedException $processFailedException) {
            $io->error('Failed to run composer: ' . $processFailedException->getProcess()->getErrorOutput());

            return Command::FAILURE;
        }

        // Create executable as tools/$name
        $xPath = ToolPath::createExecutable($toolsDir, $name);
        if ($force && $this->filesystem->exists($xPath)) {
            $this->filesystem->remove($xPath);
        }

        if (!$this->filesystem->exists($xPath)) {
            $this->filesystem->dumpFile($xPath, str_replace('%NAME%', $name, self::WRAPPER));
            $this->filesystem->chmod($xPath, 0755);
        }

        return null;
    }

    /**
     * Install given extension
     */
    private function installExtension(Package $package, Package $extension, string $toolsDir, SymfonyStyle $io): ?int
    {
        $name = $package->getName(); // Use normalized name
        $legacyDir = $toolsDir . DIRECTORY_SEPARATOR . $name;
        $targetDir = ToolPath::create($toolsDir, $name);

        if (!is_dir($targetDir)) {
            if (is_dir($legacyDir)) {
                $targetDir = $legacyDir;
            } else {
                $io->warning(sprintf('%s is not installed. You can install it with "cotor.phar install %1$s"', $name));

                return Command::FAILURE;
            }
        }

        // Check if extension is installed
        $extensionDir = $targetDir . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . $extension->getComposerName();
        if (is_dir($extensionDir)) {
            $io->writeln(sprintf('[INFO] %s is already installed. Skipping...', $extension->getComposerName()), OutputInterface::VERBOSITY_VERBOSE);

            return Command::FAILURE;
        }

        try {
            $this->runComposerWithPackage('require', $targetDir, $extension, true);
        } catch (ProcessFailedException $processFailedException) {
            $io->warning('Failed to run composer: ' . $processFailedException->getProcess()->getErrorOutput());
        }

        return null;
    }

    /**
     * Add tool to composer extra
     */
    private function updateComposer(Composer $composer, string $composerPath, Package $package, SymfonyStyle $io, bool $replace = false): void
    {
        /** @var array{extra?: array{cotor: array<string, string>}} $composerJson */
        $composerJson = $composer->getJson();
        if (!$replace && isset($composerJson['extra'][Cotor::COMPOSER_EXTRA][$package->getComposerName()])) {
            return;
        }

        $composerJson['extra'][Cotor::COMPOSER_EXTRA][$package->getComposerName()] = $package->getVersion();
        ksort($composerJson['extra'][Cotor::COMPOSER_EXTRA]);
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
    private function ensureToolsDir(string $toolsDir, SymfonyStyle $io): ?int
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
}
