<?php

declare(strict_types=1);

namespace IServ\ComposerToolsInstaller\Command;

use IServ\ComposerToolsInstaller\Domain\Composer;
use IServ\ComposerToolsInstaller\Domain\Cotor;
use IServ\ComposerToolsInstaller\Domain\Package;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

final class ExtendCommand extends AbstractComposerCommand
{
    protected static $defaultName = 'extend';

    protected function configure(): void
    {
        $this
            ->setDescription('Installs a tool extension')
            ->setHelp('This command allows you to add an extension to a composer based tool.')
            ->addArgument('name', InputArgument::REQUIRED, 'The short name of the tool or its composer name')
            ->addArgument('extension', InputArgument::REQUIRED, 'The composer package name of the extension to install')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $name = (string)$input->getArgument('name');
        $extension = (string)$input->getArgument('extension');

        try {
            $extensionPackage = Package::createFromComposerName($extension);
        } catch (\InvalidArgumentException $e) {
            $io->error($e->getMessage());

            return Command::INVALID;
        }

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
        $targetDir = $toolsDir . '/.' . $name;
        if (!is_dir($targetDir)) {
            if (is_dir($legacyDir)) {
                $targetDir = $legacyDir;
            } else {
                $io->error(sprintf('%s is not installed. You can install it with "cotor.phar install %s".', $name, $name));

                return Command::INVALID;
            }
        }

        // Get project's composer file
        $composer = null;
        $composerPath = getcwd() . '/composer.json';
        if ($this->filesystem->exists($composerPath)) {
            try {
                $composer = new Composer(file_get_contents($composerPath));
            } catch (\JsonException $e) {
                $io->warning('Failed to parse composer.json: ' . $e->getMessage());
            }
        }

        // Install extension to tool
        try {
            $this->runComposerWithArguments('require', $targetDir, $extension);
        } catch (ProcessFailedException $e) {
            /** @var Process $process */
            $process = $e->getProcess(); // Make psalm happy :/
            $io->error('Failed to run composer: ' . $process->getErrorOutput());

            return Command::FAILURE;
        }

        // Extract installed extension version and store everything in the project's composer file
        if (null !== $composer) {
            $extensionPackage = $this->getInstalledPackageVersion($toolsDir, $extensionPackage, $io, $package);
            $this->updateComposerWithExtension($composer, $composerPath, $package, $extensionPackage, $io);
        }

        $io->success(sprintf('Extended %s with %s successfully.', $name, $extension));

        return Command::SUCCESS;
    }

    /**
     * Add extension to composer extra
     */
    private function updateComposerWithExtension(Composer $composer, string $composerPath, Package $package, Package $extension, SymfonyStyle $io): void
    {
        /** @var array{extra?: array{cotor: array<string, array{extensions: array<string, string>}>}} $composerJson */
        $composerJson = $composer->getJson();

        $composerJson['extra'][Cotor::COMPOSER_EXTRA][Cotor::COMPOSER_EXTRA_EXTENSIONS][$package->getComposerName()][$extension->getComposerName()] = $extension->getVersion();
        ksort($composerJson['extra'][Cotor::COMPOSER_EXTRA]);
        ksort($composerJson['extra'][Cotor::COMPOSER_EXTRA][Cotor::COMPOSER_EXTRA_EXTENSIONS]);
        ksort($composerJson['extra'][Cotor::COMPOSER_EXTRA][Cotor::COMPOSER_EXTRA_EXTENSIONS][$package->getComposerName()]);
        try {
            $composer->setJson($composerJson);
            $this->filesystem->dumpFile($composerPath, $composer->toPrettyJsonString());
        } catch (\JsonException $e) {
            $io->warning('Failed to update composer.json: ' . $e->getMessage());
        }
    }
}
