<?php

declare(strict_types=1);

namespace IServ\ComposerToolsInstaller\Command;

use IServ\ComposerToolsInstaller\Domain\Composer;
use IServ\ComposerToolsInstaller\Domain\Config\CotorConfig;
use IServ\ComposerToolsInstaller\Domain\Config\CotorConfigLoader;
use IServ\ComposerToolsInstaller\Domain\Config\CotorConfigResolver;
use IServ\ComposerToolsInstaller\Domain\Config\RootComposerConfigReader;
use IServ\ComposerToolsInstaller\Domain\Package;
use IServ\ComposerToolsInstaller\Domain\SemVer;
use IServ\ComposerToolsInstaller\Tools\ToolPath;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;

abstract class AbstractComposerCommand extends AbstractToolCommand
{
    private ?CotorConfig $cotorConfig = null;

    public function __construct(
        protected Filesystem $filesystem,
    ) {
        parent::__construct();
    }

    protected function getInstalledPackageVersion(string $toolsDir, Package $package, SymfonyStyle $io, ?Package $basePackage = null): Package
    {
        // Get installed version
        $toolName = null === $basePackage ? $package->getName() : $basePackage->getName();
        $toolComposerLockName = ToolPath::create($toolsDir, $toolName, 'composer.lock');
        if (!$this->filesystem->exists($toolComposerLockName)) {
            $io->warning(sprintf('Could not find composer.lock of %s!', $toolName));

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

    protected function getCotorConfig(): CotorConfig
    {
        if (null !== $this->cotorConfig) {
            return $this->cotorConfig;
        }

        $loader = new CotorConfigLoader($this->filesystem);
        $cotorJsonConfig = $loader->load(getcwd() . DIRECTORY_SEPARATOR . 'cotor.json');

        $rootReader = new RootComposerConfigReader($this->filesystem);
        $rootConfig = $rootReader->read(getcwd() . DIRECTORY_SEPARATOR . 'composer.json');

        $resolver = new CotorConfigResolver();
        $this->cotorConfig = $resolver->resolve($cotorJsonConfig, $rootConfig);

        return $this->cotorConfig;
    }

    protected function prepareToolComposerJson(string $targetDir): void
    {
        $config = $this->getCotorConfig();
        $composerPath = $targetDir . DIRECTORY_SEPARATOR . 'composer.json';

        $json = [];
        if ($this->filesystem->exists($composerPath)) {
            try {
                $composer = new Composer(file_get_contents($composerPath));
                $json = $composer->getJson();
            } catch (\JsonException) {
                // Ignore invalid JSON in tool dir, we will overwrite it
            }
        }

        $updated = false;

        if (!isset($json['require']['php']) && null !== $config->getPhp()) {
            /** @psalm-suppress MixedArrayAssignment */
            $json['require']['php'] = $config->getPhp();
            $updated = true;
        }

        if (!isset($json['repositories']) && !empty($config->getRepositories())) {
            $json['repositories'] = $config->getRepositories();
            $updated = true;
        }

        if (!$updated) {
            return;
        }

        if (!empty($json)) {
            try {
                $composer = new Composer(json_encode($json, JSON_THROW_ON_ERROR));
                $this->filesystem->dumpFile($composerPath, $composer->toPrettyJsonString());
            } catch (\JsonException $e) {
                throw new \RuntimeException('Failed to update composer.json in tool directory', previous: $e);
            }
        }
    }
}
