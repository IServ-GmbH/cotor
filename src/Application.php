<?php

declare(strict_types=1);

namespace IServ\ComposerToolsInstaller;

use IServ\ComposerToolsInstaller\Tools\ToolsRegistry;

final class Application extends \Symfony\Component\Console\Application
{
    private const LOGO = <<<'ASCII'

           _             
          | |            
  ___ ___ | |_ ___  _ __ 
 / __/ _ \| __/ _ \| '__|
| (_| (_) | || (_) | |   
 \___\___/ \__\___/|_|   

                         

ASCII;

    /** {@inheritDoc} */
    public function getHelp()
    {
        $help = self::LOGO;
        $help .= parent::getHelp();
        $help .= PHP_EOL . PHP_EOL . 'Registered tool shortcuts: ' . implode(', ', array_keys(ToolsRegistry::getRegisteredTools()));

        return $help;
    }
}
