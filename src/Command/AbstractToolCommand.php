<?php

declare(strict_types=1);

namespace IServ\ComposerToolsInstaller\Command;

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

    /**
     * @return array{0: string, 1: string}
     */
    protected function getVendorAndName(string $name): array
    {
        if (!str_contains($name, '/')) {
            if (!isset(ToolsRegistry::getRegisteredTools()[$name])) {
                throw new \InvalidArgumentException();
            }

            $name = ToolsRegistry::getRegisteredTools()[$name];
        }

        return explode('/', $name);
    }

    /**
     * @throws ProcessFailedException
     */
    protected function runComposerWithPackage(string $command, string $targetDir, string $vendor, string $name): void
    {
        $process = new Process(['composer', $command, sprintf('--working-dir=%s', $targetDir), $vendor . '/' . $name]);
        $process->mustRun();
    }

    /**
     * @throws ProcessFailedException
     */
    protected function runComposer(string $command, string $targetDir): void
    {
        $process = new Process(['composer', $command, sprintf('--working-dir=%s', $targetDir)]);
        $process->mustRun();
    }

    /**
     * @throws ProcessFailedException
     */
    protected function runComposerWithArguments(string $command, string $targetDir, ...$arguments): void
    {
        $process = new Process(array_merge(['composer', $command, sprintf('--working-dir=%s', $targetDir)], $arguments ?? []));
        $process->mustRun();
    }
}
