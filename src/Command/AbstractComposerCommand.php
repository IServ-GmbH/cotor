<?php

declare(strict_types=1);

namespace IServ\ComposerToolsInstaller\Command;

use IServ\ComposerToolsInstaller\Domain\Composer;
use IServ\ComposerToolsInstaller\Domain\Package;
use IServ\ComposerToolsInstaller\Domain\SemVer;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;

abstract class AbstractComposerCommand extends AbstractToolCommand
{
    /** @var Filesystem */
    protected $filesystem;

    public function __construct(Filesystem $filesystem)
    {
        $this->filesystem = $filesystem;

        parent::__construct();
    }

    protected function getInstalledPackageVersion(string $toolsDir, Package $package, SymfonyStyle $io, ?Package $basePackage = null): Package
    {
        // Get installed version
        $toolName = null === $basePackage ? $package->getName() : $basePackage->getName();
        $toolComposerLockName = $toolsDir . '/' . $toolName . '/composer.lock';
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
}
