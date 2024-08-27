<?php

declare(strict_types=1);

namespace IServ\ComposerToolsInstaller\Command;

use IServ\ComposerToolsInstaller\Domain\Composer;
use IServ\ComposerToolsInstaller\Domain\Cotor;
use IServ\ComposerToolsInstaller\Domain\Package;
use IServ\ComposerToolsInstaller\Tools\ToolPath;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

final class ExtendCommand extends AbstractComposerCommand
{
    /** @var string */
    protected static $defaultName = 'extend';

    protected function configure(): void
    {
        $this
            ->setDescription('Installs a tool extension')
            ->setHelp('This command allows you to add an extension to a composer based tool.')
            ->addArgument('name', InputArgument::REQUIRED, 'The short name of the tool or its composer name')
            ->addArgument('extension', InputArgument::REQUIRED, 'The composer package name of the extension to install')
            ->addArgument('version', InputArgument::OPTIONAL, 'The version of the extension to install (default: latest)')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $name = (string)$input->getArgument('name');
        $extension = (string)$input->getArgument('extension');
        try {
            /** @var mixed $version Makes psalm happy */
            $version = $input->getArgument('version');
            if (null !== $version) {
                $version = (string)$version;
            }
        } catch (InvalidArgumentException) {
            $version = null;
        }

        if (str_contains($extension, ':')) {
            [$extension, $version] = explode(':', $extension);
            $io->warning('Do not add a version to the extension name!');
            $io->text(sprintf('Try: cotor extend %s %s %s', $name, $extension, $version));


            return self::FAILURE;
        }

        try {
            $extensionPackage = Package::createFromComposerName($extension, $version);
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
        $targetDir = ToolPath::create($toolsDir, $name);
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
        $extensionParam = $extension;
        if ($version) {
            $extensionParam = $extension . ':' . $version;
        }

        try {
            $this->runComposerWithArguments('require', $targetDir, $extensionParam);
        } catch (ProcessFailedException $processFailedException) {
            $io->error('Failed to run composer: ' . $processFailedException->getProcess()->getErrorOutput());

            return Command::FAILURE;
        }

        // Extract installed extension version and store everything in the project's composer file
        if (null !== $composer) {
            $extensionPackage = $this->getInstalledPackageVersion($toolsDir, $extensionPackage, $io, $package);
            if (null !== $version) {
                $extensionPackage = $extensionPackage->withVersion($version);
            }
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
