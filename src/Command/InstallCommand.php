<?php

declare(strict_types=1);

namespace IServ\ComposerToolsInstaller\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Exception\ProcessFailedException;

final class InstallCommand extends AbstractToolCommand
{
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
            $helper = $this->getHelper('question');
            $question = new ConfirmationQuestion('There is no tools directory. Do you want to create it now?', false);

            if (!$helper->ask($input, $output, $question)) {
                return Command::SUCCESS;
            }

            $this->filesystem->mkdir($toolsDir);
        }

        try {
            [$vendor, $name] = $this->getVendorAndName($name);
        } catch (\InvalidArgumentException $e) {
            $io->error(sprintf('Unknown tool %s!', $name));

            return Command::INVALID;
        }

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

        try {
            $this->runComposerWithPackage('require', $targetDir, $vendor, $name);
        } catch (ProcessFailedException $e) {
            $io->error('Failed to run composer: ' . $e->getProcess()->getErrorOutput());

            return Command::FAILURE;
        }

        // Create symlink from vendor-bin to tools/$name.phar to replace phive transparently
        $binPath = sprintf('%s/vendor/bin/%s', $targetDir, $name);
        if ($this->filesystem->exists($binPath)) {
            $this->filesystem->symlink($binPath, sprintf('%s/%s.phar', $toolsDir, $name));
        } else {
            $io->warning(sprintf('Could not find executable for %s at %s! Skipping symlinking as phar.', $name, $binPath));
        }

        $io->success(sprintf('%s installed successfully.', $name));

        return Command::SUCCESS;
    }
}
