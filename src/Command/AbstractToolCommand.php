<?php

declare(strict_types=1);

namespace IServ\ComposerToolsInstaller\Command;

use IServ\ComposerToolsInstaller\Domain\Package;
use IServ\ComposerToolsInstaller\Tools\ToolsRegistry;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

abstract class AbstractToolCommand extends Command
{
    protected function getToolsDir(): string
    {
        return getcwd() . '/tools';
    }

    protected function getPackage(string $name): Package
    {
        if (!str_contains($name, '/')) {
            if (!isset(ToolsRegistry::getRegisteredTools()[$name])) {
                throw new \InvalidArgumentException();
            }

            $name = ToolsRegistry::getRegisteredTools()[$name];
        }

        $separatedName = explode('/', $name, 2);

        if (count($separatedName) !== 2) {
            throw new \InvalidArgumentException();
        }

        return new Package($separatedName[0], $separatedName[1]);
    }

    /**
     * @param string[] $arguments
     * @throws ProcessFailedException
     */
    protected function runComposerWithPackage(string $command, string $targetDir, Package $package, bool $useVersion, array $arguments): string
    {
        $name = $package->getComposerName();
        if ($useVersion) {
            $name .= ':' . $package->getVersion();
        }

        return $this->runComposer($command, $targetDir, [$name, ...$arguments]);
    }

    /**
     * @param string[] $arguments
     * @throws ProcessFailedException
     */
    protected function runComposer(string $command, string $targetDir, array $arguments = []): string
    {
        $process = new Process(array_merge(['composer', $command, sprintf('--working-dir=%s', $targetDir)], $arguments));
        $process->mustRun();

        return $process->getOutput();
    }
}
