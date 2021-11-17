<?php

declare(strict_types=1);

namespace IServ\ComposerToolsInstaller\Tools;

final class ToolPath
{
    public static function create(string $toolsDir, string $name, ?string $file = null): string
    {
        if (null === $file) {
            return sprintf('%s/.%s', $toolsDir, $name);
        }

        return sprintf('%s/.%s/%s', $toolsDir, $name, $file);
    }
}
