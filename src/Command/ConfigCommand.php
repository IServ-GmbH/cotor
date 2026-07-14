<?php

declare(strict_types=1);

namespace IServ\ComposerToolsInstaller\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'config', description: 'Shows the current configuration')]
final class ConfigCommand extends AbstractComposerCommand
{
    protected function configure(): void
    {
        $this
            ->addOption('merged', null, InputOption::VALUE_NONE, 'Show the merged configuration')
            ->setHelp('This command shows the current configuration.')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $config = $this->getCotorConfig();

        if ($input->getOption('merged')) {
            $io->title('Merged Configuration');
            $io->table(['Option', 'Value'], [
                ['PHP', $config->getPhp() ?? 'Not set'],
                ['Repositories', json_encode($config->getRepositories(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)],
                ['Sync', $config->isSync() ? 'true' : 'false'],
            ]);
        } else {
            $io->note('Use --merged to see the merged configuration from cotor.json and root composer.json.');
        }

        return Command::SUCCESS;
    }
}
