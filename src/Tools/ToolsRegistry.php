<?php

declare(strict_types=1);

namespace IServ\ComposerToolsInstaller\Tools;

final class ToolsRegistry
{
    /** @return array<string, string> */
    public static function getRegisteredTools(): array
    {
        return [
            'php-cs-fixer' => 'friendsofphp/php-cs-fixer',
            'phpcsfixer' => 'friendsofphp/php-cs-fixer',
            'phpstan' => 'phpstan/phpstan',
            'phpunit' => 'phpunit/phpunit',
            'pslam' => 'vimeo/pslam',
        ];
    }
}
